<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Support\Importer;
use App\Support\Money;
use App\Support\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * استيراد وتصدير Excel:
 *   1) ارفع الملف  2) اربط الأعمدة (أوتوماتيك غالباً) وشوف المعاينة  3) استيراد + تقرير بالأخطاء
 */
class DataController extends Controller
{
    /** التصدير: key => [الاسم, الأيقونة, الصلاحية] */
    public const EXPORTS = [
        'vehicles'     => ['العربيات', '🚗', 'vehicles.view'],
        'customers'    => ['العملاء', '👥', 'customers.view'],
        'installments' => ['الأقساط المفتوحة', '📅', 'payments.view'],
        'sales'        => ['عقود البيع', '📝', 'sales.view'],
        'inquiries'    => ['طلبات الموقع', '📥', 'inquiries.view'],
    ];

    private function dir(): string
    {
        $dir = storage_path('app/private/imports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public function index(): View
    {
        return view('admin.data.index', [
            'imports' => collect(Importer::TYPES)->filter(fn ($t) => Access::can($t['module'].'.create') && Access::can('data.create')),
            'exports' => collect(self::EXPORTS)->filter(fn ($e) => Access::can($e[2])),
            'result'  => session('import_result'),
        ]);
    }

    /** ملف Excel فاضي بالأعمدة الصح + صف مثال */
    public function template(string $type): StreamedResponse
    {
        abort_unless(isset(Importer::TYPES[$type]), 404);
        $fields = Importer::fields($type);

        return Spreadsheet::download('نموذج-'.Importer::TYPES[$type]['label'],
            array_map(fn ($f) => $f[0].($f[1] ? ' *' : ''), array_values($fields)),
            [array_map(fn ($f) => $f[3], array_values($fields))]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Importer::TYPES))],
            'file' => ['required', 'file', 'max:10240'],
        ], ['file.required' => 'اختار الملف.', 'file.max' => 'الملف أكبر من 10 ميجا.']);
        $this->authorizeImport($data['type']);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return back()->with('error', $ext === 'xls'
                ? 'الملف ده بصيغة Excel القديمة (xls). افتحه في Excel واعمل «حفظ باسم» واختار Excel Workbook (.xlsx).'
                : 'ارفع ملف Excel (.xlsx) أو CSV.');
        }

        // تنضيف الملفات القديمة
        foreach (glob($this->dir().'/*') ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }

        $token = Str::random(24);
        $request->file('file')->move($this->dir(), $token.'.'.$ext);
        try {
            $rows = Spreadsheet::read($this->dir().'/'.$token.'.'.$ext, $ext);
        } catch (Throwable $e) {
            @unlink($this->dir().'/'.$token.'.'.$ext);

            return back()->with('error', $e->getMessage());
        }
        if (count($rows) < 2) {
            @unlink($this->dir().'/'.$token.'.'.$ext);

            return back()->with('error', 'الملف فاضي — لازم أول صف يبقى أسماء الأعمدة وبعده البيانات.');
        }

        session(['import.'.$token => ['type' => $data['type'], 'ext' => $ext, 'name' => $request->file('file')->getClientOriginalName(), 'user' => $request->user()->id]]);

        return redirect()->route('admin.data.map', $token);
    }

    public function map(Request $request, string $token): View|RedirectResponse
    {
        [$meta, $rows] = $this->load($request, $token);
        if (! $meta) {
            return redirect()->route('admin.data.index')->with('error', 'الملف ده انتهى — ارفعه تاني.');
        }
        $headers = array_shift($rows);
        $map = $this->mapping($request, $meta['type'], $headers);

        $importer = new Importer($meta['type'], $request->user());
        $stats = ['valid' => 0, 'invalid' => 0, 'existing' => 0];
        $preview = [];
        $errorsByField = [];
        foreach ($rows as $i => $row) {
            [$d, $errors] = $importer->check($row, $map);
            $exists = ! $errors && $importer->existing($d);
            $errors ? $stats['invalid']++ : $stats['valid']++;
            if ($exists) {
                $stats['existing']++;
            }
            foreach ($errors as $e) {
                $errorsByField[$e] = ($errorsByField[$e] ?? 0) + 1;
            }
            if (count($preview) < 8 || ($errors && count($preview) < 15)) {
                $preview[] = ['n' => $i + 2, 'row' => $row, 'errors' => $errors, 'exists' => $exists];
            }
        }

        return view('admin.data.map', [
            'token' => $token, 'meta' => $meta, 'type' => Importer::TYPES[$meta['type']], 'fields' => Importer::fields($meta['type']),
            'headers' => $headers, 'map' => $map, 'preview' => $preview, 'stats' => $stats, 'total' => count($rows),
            'topErrors' => collect($errorsByField)->sortDesc()->take(6),
        ]);
    }

    public function run(Request $request, string $token): RedirectResponse
    {
        [$meta, $rows] = $this->load($request, $token);
        if (! $meta) {
            return redirect()->route('admin.data.index')->with('error', 'الملف ده انتهى — ارفعه تاني.');
        }
        @set_time_limit(600);
        $headers = array_shift($rows);
        $map = $this->mapping($request, $meta['type'], $headers);
        $update = $request->input('mode') === 'update';

        $importer = new Importer($meta['type'], $request->user());
        $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $failed = [];
        foreach ($rows as $i => $row) {
            [$d, $errors] = $importer->check($row, $map);
            if (! $errors) {
                try {
                    $res[$importer->import($d, $update)]++;

                    continue;
                } catch (Throwable $e) {
                    report($e);
                    $errors = [$this->friendly($e)];
                }
            }
            $res['failed']++;
            $failed[] = ['n' => $i + 2, 'row' => $row, 'errors' => $errors];
        }

        if ($failed) {
            file_put_contents($this->dir().'/'.$token.'-errors.json', json_encode(['headers' => $headers, 'rows' => $failed], JSON_UNESCAPED_UNICODE));
        }
        @unlink($this->dir().'/'.$token.'.'.$meta['ext']);

        return redirect()->route('admin.data.index')->with('import_result', $res + [
            'type' => $meta['type'], 'file' => $meta['name'], 'token' => $failed ? $token : null,
            'errors' => array_slice($failed, 0, 50), 'extra' => $importer->created,
        ]);
    }

    /** الصفوف اللي فشلت + عمود بالسبب — يصلّحها ويرفعها تاني */
    public function errors(string $token): StreamedResponse
    {
        $path = $this->dir().'/'.preg_replace('/[^A-Za-z0-9]/', '', $token).'-errors.json';
        abort_unless(is_file($path), 404);
        $data = json_decode((string) file_get_contents($path), true);

        return Spreadsheet::download('صفوف-محتاجة-تصليح', array_merge(['سبب المشكلة'], $data['headers']),
            array_map(fn ($f) => array_merge([implode(' — ', $f['errors'])], $f['row']), $data['rows']));
    }

    // =========================================================
    //  التصدير
    // =========================================================

    public function export(string $type): StreamedResponse
    {
        abort_unless(isset(self::EXPORTS[$type]) && Access::can(self::EXPORTS[$type][2]), 404);
        $date = now()->format('Y-m-d');
        $n = fn ($v) => $v === null ? null : (float) $v;
        $d = fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d') : null;

        switch ($type) {
            case 'vehicles':
                $cost = Access::can('vehicles.cost');
                $q = Access::scope(DB::table('vehicles as v')->whereNull('v.deleted_at'), 'v.branch_id')
                    ->leftJoin('car_brands as b', 'b.id', '=', 'v.brand_id')->leftJoin('car_models as m', 'm.id', '=', 'v.model_id')
                    ->leftJoin('branches as br', 'br.id', '=', 'v.branch_id')
                    ->select('v.*', 'b.name_ar as brand', 'm.name_ar as model', 'br.name_ar as branch')->orderBy('v.id');
                $status = ['available' => 'متاح', 'reserved' => 'محجوز', 'sold' => 'مباع', 'damaged' => 'تالف', 'maintenance' => 'صيانة', 'returned' => 'مرتجع'];
                $headers = array_merge(['الكود', 'الماركة', 'الموديل', 'سنة الصنع', 'اللون', 'رقم الشاسيه', 'رقم اللوحة', 'رقم الموتور', 'الكيلومترات'],
                    $cost ? ['سعر الشراء'] : [], ['سعر البيع', 'سعر العرض', 'الفرع', 'الحالة', 'منشورة في الموقع', 'تاريخ الإضافة', 'ملاحظات']);
                $rows = fn () => (function () use ($q, $cost, $status, $n, $d) {
                    foreach ($q->lazy(500) as $v) {
                        yield array_merge([$v->vehicle_code, $v->brand, $v->model, (int) $v->manufacturing_year, $v->color, $v->chassis_number, $v->plate_number, $v->engine_number, (int) $v->mileage],
                            $cost ? [$n($v->total_cost)] : [], [$n($v->selling_price), $n($v->discount_price), $v->branch, $status[$v->status] ?? $v->status, $v->is_published ? 'نعم' : 'لا', $d($v->created_at), $v->notes]);
                    }
                })();
                break;

            case 'customers':
                $q = DB::table('customers as c')->whereNull('c.deleted_at')
                    ->select('c.*', DB::raw('(select count(*) from sales_contracts s where s.customer_id = c.id and s.deleted_at is null) as contracts'),
                        DB::raw('(select coalesce(sum(remaining_amount),0) from sales_contracts s where s.customer_id = c.id and s.deleted_at is null) as remaining'))
                    ->orderBy('c.id');
                $st = ['active' => 'نشط', 'inactive' => 'غير نشط', 'blocked' => 'محظور'];
                $headers = ['الكود', 'الاسم', 'الاسم بالإنجليزي', 'الموبايل', 'الرقم المدني', 'الإيميل', 'العنوان', 'الوظيفة', 'الجنسية', 'عدد العقود', 'إجمالي المشتريات', 'المتبقي عليه', 'الحالة', 'عميل من', 'ملاحظات'];
                $rows = fn () => (function () use ($q, $st, $n, $d) {
                    foreach ($q->lazy(500) as $c) {
                        yield [$c->customer_code, $c->name_ar, $c->name_en, $c->phone, $c->national_id, $c->email, $c->address, $c->job_title, $c->nationality,
                            (int) $c->contracts, $n($c->total_purchases), $n($c->remaining), $st[$c->status] ?? $c->status, $d($c->created_at), $c->notes];
                    }
                })();
                break;

            case 'installments':
                $q = Access::scopeVia(DB::table('installments as i')->whereNull('i.deleted_at')->where('i.is_paid', false), 'i.sales_contract_id', 'sales_contracts')
                    ->join('sales_contracts as s', 's.id', '=', 'i.sales_contract_id')->whereNull('s.deleted_at')
                    ->join('customers as c', 'c.id', '=', 'i.customer_id')
                    ->leftJoin('vehicles as v', 'v.id', '=', 's.vehicle_id')->leftJoin('car_brands as b', 'b.id', '=', 'v.brand_id')->leftJoin('car_models as m', 'm.id', '=', 'v.model_id')
                    ->select('i.*', 's.contract_number', 'c.name_ar as customer', 'c.phone', 'b.name_ar as brand', 'm.name_ar as model', 'v.manufacturing_year as year')
                    ->orderBy('i.due_date');
                $headers = ['تاريخ الاستحقاق', 'العميل', 'الموبايل', 'العقد', 'العربية', 'رقم القسط', 'قيمة القسط', 'المدفوع منه', 'الباقي', 'متأخر كام يوم', 'الحالة'];
                $rows = fn () => (function () use ($q, $n, $d) {
                    $today = today();
                    foreach ($q->lazy(500) as $i) {
                        $due = Carbon::parse($i->due_date);
                        $late = $due->lt($today) ? (int) $due->diffInDays($today) : 0;
                        yield [$d($i->due_date), $i->customer, $i->phone, $i->contract_number, trim($i->brand.' '.$i->model.' '.$i->year), $i->installment_sequence.' / '.$i->total_installments,
                            $n($i->amount), $n($i->paid_amount), Money::round((float) $i->amount - (float) $i->paid_amount), $late, $late ? 'متأخر' : ((float) $i->paid_amount > 0 ? 'مدفوع جزء' : 'لسه')];
                    }
                })();
                break;

            case 'sales':
                $q = Access::scope(DB::table('sales_contracts as s')->whereNull('s.deleted_at'), 's.branch_id')
                    ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')->leftJoin('vehicles as v', 'v.id', '=', 's.vehicle_id')
                    ->leftJoin('car_brands as b', 'b.id', '=', 'v.brand_id')->leftJoin('car_models as m', 'm.id', '=', 'v.model_id')
                    ->leftJoin('branches as br', 'br.id', '=', 's.branch_id')->leftJoin('users as u', 'u.id', '=', 's.user_id')
                    ->select('s.*', 'c.name_ar as customer', 'c.phone', 'b.name_ar as brand', 'm.name_ar as model', 'v.manufacturing_year as year', 'v.vehicle_code', 'br.name_ar as branch', 'u.name as seller')
                    ->orderByDesc('s.contract_date');
                $headers = ['رقم العقد', 'التاريخ', 'العميل', 'الموبايل', 'العربية', 'كود العربية', 'الإجمالي', 'الخصم', 'الضريبة', 'طريقة الدفع', 'المقدم', 'عدد الأقساط', 'المدفوع', 'المتبقي', 'الفرع', 'البائع'];
                $rows = fn () => (function () use ($q, $n, $d) {
                    foreach ($q->lazy(500) as $s) {
                        yield [$s->contract_number, $d($s->contract_date), $s->customer, $s->phone, trim($s->brand.' '.$s->model.' '.$s->year), $s->vehicle_code,
                            $n($s->total_amount), $n($s->discount_amount), $n($s->tax_amount), $s->payment_type === 'installments' ? 'تقسيط' : 'كاش',
                            $n($s->down_payment), $s->installments_count, $n($s->paid_amount), $n($s->remaining_amount), $s->branch, $s->seller];
                    }
                })();
                break;

            default: // inquiries
                $q = Access::scope(DB::table('vehicle_inquiries as i')->whereNull('i.deleted_at'), 'i.branch_id')
                    ->leftJoin('vehicles as v', 'v.id', '=', 'i.vehicle_id')->leftJoin('car_brands as b', 'b.id', '=', 'v.brand_id')->leftJoin('car_models as m', 'm.id', '=', 'v.model_id')
                    ->leftJoin('users as u', 'u.id', '=', 'i.assigned_to')
                    ->select('i.*', 'b.name_ar as brand', 'm.name_ar as model', 'u.name as assignee')->orderByDesc('i.id');
                $stages = collect(InquiryController::STAGES)->map(fn ($s) => trim(preg_replace('/^\S+\s/u', '', $s[0])))->all();
                $headers = ['رقم الطلب', 'التاريخ', 'الاسم', 'الموبايل', 'الإيميل', 'النوع', 'العربية', 'المرحلة', 'مع مين', 'المصدر', 'الحملة', 'الرسالة', 'ملاحظات الموظف'];
                $rows = fn () => (function () use ($q, $stages, $d) {
                    foreach ($q->lazy(500) as $i) {
                        yield [$i->inquiry_number, $d($i->created_at), $i->name, $i->phone, $i->email, InquiryController::TYPES[$i->type] ?? $i->type,
                            trim($i->brand.' '.$i->model), $stages[$i->status] ?? $i->status, $i->assignee, $i->source, $i->utm_campaign, $i->message, $i->staff_notes];
                    }
                })();
        }

        return Spreadsheet::download(self::EXPORTS[$type][0].'-'.$date, $headers, $rows());
    }

    // =========================================================

    private function authorizeImport(string $type): void
    {
        abort_unless(Access::can(Importer::TYPES[$type]['module'].'.create'), 403);
    }

    private function load(Request $request, string $token): array
    {
        $meta = session('import.'.$token);
        $path = $this->dir().'/'.preg_replace('/[^A-Za-z0-9]/', '', $token).'.'.($meta['ext'] ?? 'x');
        if (! $meta || (int) $meta['user'] !== (int) $request->user()->id || ! is_file($path)) {
            return [null, []];
        }
        $this->authorizeImport($meta['type']);

        return [$meta, Spreadsheet::read($path, $meta['ext'])];
    }

    private function mapping(Request $request, string $type, array $headers): array
    {
        $given = $request->input('map');
        if (! is_array($given)) {
            return Importer::guessMapping($type, $headers);
        }
        $out = [];
        foreach (array_keys(Importer::fields($type)) as $key) {
            $v = $given[$key] ?? '';
            if ($v !== '' && $v !== null && ctype_digit((string) $v) && (int) $v < count($headers)) {
                $out[$key] = (int) $v;
            }
        }

        return $out;
    }

    private function friendly(Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, 'Duplicate entry') => 'قيمة مكررة موجودة قبل كده (رقم شاسيه / لوحة / رقم مدني)',
            str_contains($m, 'Data too long') => 'في خانة الكلام فيها أطول من المسموح',
            default => 'حصلت مشكلة في الصف ده: '.Str::limit($m, 120),
        };
    }
}
