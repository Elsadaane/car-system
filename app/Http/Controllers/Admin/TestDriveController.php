<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\TestDrive;
use App\Models\Vehicle;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * تجربة القيادة:
 *   تبدأ "جارية" لحظة ما العميل ياخد العربية ← تتقفل لما يرجع (العداد + رأيه + مهتم ولا لأ)
 */
class TestDriveController extends Controller
{
    public const STATUSES = ['scheduled' => 'جارية', 'completed' => 'انتهت', 'cancelled' => 'ملغاة'];

    public const DEFAULT_TERMS_AR = "المركبة بحالة جيدة وأتحمل كافة مسؤولياتها من المخالفات أو الحوادث أو خلفيات أخرى من وإلى المدة المذكورة أعلاه.\n"
        ."أقر أنا الموقع أدناه بأني قمت بفحص المركبة ولا توجد لدي أي ملاحظات تجاه المركبة.";

    public const DEFAULT_TERMS_EN = "The vehicle is in good condition and I bear full responsibility for any violations or accidents during the period stated above.\n"
        .'I, the undersigned, confirm that I have inspected the vehicle and have no remarks regarding it.';

    private const MESSAGES = [
        'required' => 'الحقل ده مطلوب.',
        'exists'   => 'الاختيار مش صحيح.',
        'integer'  => 'لازم يكون رقم صحيح.',
        'min'      => 'أقل من المسموح.',
        'date'     => 'لازم يكون تاريخ.',
        'vehicle_id.exists' => 'العربية دي مش متاحة للتجربة.',
    ];

    public function index(Request $request): View
    {
        $f = [
            'q'        => trim((string) $request->query('q')),
            'vehicle'  => $request->integer('vehicle') ?: null,
            'status'   => array_key_exists((string) $request->query('status'), self::STATUSES) ? $request->query('status') : null,
            'interest' => in_array($request->query('interest'), ['1', '0'], true) ? $request->query('interest') : null,
            'from'     => $request->date('from')?->toDateString(),
            'to'       => $request->date('to')?->toDateString(),
        ];

        $drives = $this->baseQuery()
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('customers.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('customers.phone', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.vehicle_code', 'like', "%{$f['q']}%")))
            ->when($f['vehicle'], fn ($q, $v) => $q->where('test_drives.vehicle_id', $v))
            ->tap(fn ($q) => \App\Support\Access::scopeVia($q, 'test_drives.vehicle_id', 'vehicles'))
            ->when($f['status'], fn ($q, $v) => $q->where('test_drives.status', $v))
            ->when($f['interest'] !== null, fn ($q) => $q->where('test_drives.is_interested', $f['interest'] === '1'))
            ->when($f['from'], fn ($q, $v) => $q->whereDate('test_drives.start_time', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->whereDate('test_drives.start_time', '<=', $v))
            ->latest('test_drives.start_time')
            ->paginate(15)
            ->withQueryString();

        $completed = TestDrive::where('status', 'completed');
        $totals = [
            'ongoing'    => TestDrive::where('status', 'scheduled')->count(),
            'month'      => TestDrive::where('start_time', '>=', now()->startOfMonth())->count(),
            'interested' => (clone $completed)->where('is_interested', true)->count(),
            'completed'  => (clone $completed)->count(),
            // كام تجربة اتحولت لبيعة لنفس العميل على نفس العربية
            'converted'  => DB::table('test_drives')
                ->join('sales_contracts', fn ($j) => $j->on('sales_contracts.customer_id', '=', 'test_drives.customer_id')
                    ->on('sales_contracts.vehicle_id', '=', 'test_drives.vehicle_id'))
                ->whereNull('sales_contracts.deleted_at')
                ->distinct()->count('test_drives.id'),
        ];

        return view('admin.test-drives.index', [
            'drives'   => $drives,
            'totals'   => $totals,
            'filters'  => $f,
            'statuses' => self::STATUSES,
            'vehicles' => $this->vehicleOptions(false),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.test-drives.form', [
            'drive'     => (new TestDrive)->forceFill([
                'customer_id' => $request->integer('customer') ?: null,
                'vehicle_id'  => $request->integer('vehicle') ?: null,
                'terms_ar'    => \App\Support\Company::get('test_drive_terms_ar', self::DEFAULT_TERMS_AR),
                'terms_en'    => \App\Support\Company::get('test_drive_terms_en', self::DEFAULT_TERMS_EN),
            ]),
            'customers' => Customer::where('status', '!=', 'blocked')->orderBy('name_ar')->get(['id', 'name_ar', 'phone', 'national_id']),
            'vehicles'  => $this->vehicleOptions(true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id'    => ['required', Rule::exists('customers', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'vehicle_id'     => ['required', Rule::exists('vehicles', 'id')->whereIn('status', ['available', 'reserved'])->whereNull('deleted_at')],
            'license_number' => ['nullable', 'string', 'max:50'],
            'start_mileage'  => ['nullable', 'integer', 'min:0'],
            'terms_ar'       => ['nullable', 'string', 'max:10000'],
            'terms_en'       => ['nullable', 'string', 'max:10000'],
        ], self::MESSAGES + ['customer_id.exists' => 'العميل ده مش نشط.']);
        foreach (['terms_ar', 'terms_en'] as $k) {
            $data[$k] = \App\Support\RichText::clean($data[$k] ?? null, 'terms');
        }

        $busy = TestDrive::where('vehicle_id', $data['vehicle_id'])->where('status', 'scheduled')->exists();
        if ($busy) {
            throw ValidationException::withMessages(['vehicle_id' => 'العربية دي في تجربة قيادة دلوقتي. اقفل التجربة المفتوحة الأول.']);
        }

        $drive = new TestDrive;
        $drive->forceFill($data + [
            'start_time'         => now(),
            'status'             => 'scheduled',
            'user_id'            => $request->user()->id,
            'employee_id'        => null,
            'customer_signature' => Media::storeSignature($request->input('customer_signature_data')),
            'showroom_signature' => Media::storeSignature($request->input('showroom_signature_data')),
        ])->save();

        return redirect()->route('admin.test-drives.show', $drive)->with('status', 'بدأت تجربة القيادة. اقفلها لما العربية ترجع.');
    }

    public function show(TestDrive $testDrive): View
    {
        return view('admin.test-drives.show', [
            'drive'    => $this->baseQuery()->where('test_drives.id', $testDrive->id)->firstOrFail(),
            'model'    => $testDrive,
            'statuses' => self::STATUSES,
        ]);
    }

    public function print(TestDrive $testDrive): View
    {
        return view('admin.test-drives.print', [
            'drive'   => $this->baseQuery()->where('test_drives.id', $testDrive->id)
                ->addSelect('customers.national_id', 'vehicles.chassis_number', 'vehicles.color')->firstOrFail(),
            'company' => \App\Support\Company::info(),
        ]);
    }

    /** العربية رجعت: نقفل التجربة */
    public function complete(Request $request, TestDrive $testDrive): RedirectResponse
    {
        if ($testDrive->status !== 'scheduled') {
            return back()->with('error', 'التجربة دي مقفولة بالفعل.');
        }

        $data = $request->validate([
            'end_mileage'   => ['nullable', 'integer', 'min:'.((int) $testDrive->start_mileage)],
            'is_interested' => ['required', Rule::in(['1', '0'])],
            'feedback_ar'   => ['nullable', 'string', 'max:2000'],
        ], self::MESSAGES + ['end_mileage.min' => 'قراءة العداد لازم تكون أكبر من قراءة البداية ('.(int) $testDrive->start_mileage.').']);

        $distance = ($testDrive->start_mileage !== null && isset($data['end_mileage']))
            ? (int) $data['end_mileage'] - (int) $testDrive->start_mileage : null;

        $testDrive->forceFill([
            'end_time'      => now(),
            'end_mileage'   => $data['end_mileage'] ?? null,
            'distance_km'   => $distance,
            'is_interested' => $data['is_interested'] === '1',
            'feedback_ar'   => $data['feedback_ar'] ?? null,
            'status'        => 'completed',
        ])->save();

        if (isset($data['end_mileage'])) {
            Vehicle::whereKey($testDrive->vehicle_id)->where('mileage', '<', $data['end_mileage'])->update(['mileage' => $data['end_mileage']]);
        }

        return back()->with('status', 'تم قفل التجربة'.($distance !== null ? ' — المسافة '.$distance.' كم' : ''));
    }

    public function cancel(TestDrive $testDrive): RedirectResponse
    {
        if ($testDrive->status !== 'scheduled') {
            return back()->with('error', 'التجربة دي مقفولة بالفعل.');
        }

        $testDrive->forceFill(['status' => 'cancelled', 'end_time' => now()])->save();

        return back()->with('status', 'تم إلغاء التجربة');
    }

    public function sign(Request $request, TestDrive $testDrive, string $party): RedirectResponse
    {
        abort_unless(in_array($party, ['customer', 'showroom'], true), 404);

        $path = Media::storeSignature($request->input('signature_data'));
        if (! $path) {
            return back()->with('error', 'ارسم التوقيع الأول.');
        }

        Media::delete($testDrive->{$party.'_signature'});
        $testDrive->forceFill([$party.'_signature' => $path])->save();

        return back()->with('status', 'تم حفظ التوقيع');
    }

    public function destroy(TestDrive $testDrive): RedirectResponse
    {
        Media::delete($testDrive->customer_signature);
        Media::delete($testDrive->showroom_signature);
        $testDrive->delete();

        return redirect()->route('admin.test-drives.index')->with('status', 'تم مسح التجربة');
    }

    // =========================================================
    private function baseQuery()
    {
        return TestDrive::query()
            ->leftJoin('customers', 'customers.id', '=', 'test_drives.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'test_drives.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('users', 'users.id', '=', 'test_drives.user_id')
            ->select('test_drives.*', \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone',
                'vehicles.vehicle_code', 'vehicles.plate_number', 'vehicles.manufacturing_year', 'vehicles.images as vehicle_images',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'), 'users.name as user_name');
    }

    private function vehicleOptions(bool $onlyAvailable)
    {
        return Vehicle::query()
            ->when($onlyAvailable, fn ($q) => $q->whereIn('vehicles.status', ['available', 'reserved'])
                ->whereNotIn('vehicles.id', TestDrive::where('status', 'scheduled')->select('vehicle_id')))
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->select('vehicles.id', 'vehicles.vehicle_code', 'vehicles.plate_number', 'vehicles.manufacturing_year', 'vehicles.mileage',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->orderBy('car_brands.name_ar')
            ->get();
    }
}
