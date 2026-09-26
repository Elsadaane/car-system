<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * طلبات الموقع كـ CRM:
 *  - لوحة مراحل (Kanban): جديد ← اتكلّم ← مهتم جداً ← اتباع / اتقفل — بالسحب والإفلات
 *  - توزيع الطلبات على الموظفين + ملاحظات + تنبيه لو الرد اتأخر
 *  - أداء الحملات: الطلبات والمبيعات الفعلية وتكلفة الإعلان ← CPL و ROAS حقيقي
 */
class InquiryController extends Controller
{
    public const STAGES = [
        'new'       => ['🆕 جديد', 'amber'],
        'contacted' => ['📞 اتكلّمنا', 'sky'],
        'qualified' => ['🔥 مهتم جداً', 'violet'],
        'converted' => ['✅ اتباع', 'emerald'],
        'closed'    => ['🗂️ اتقفل', 'slate'],
        'spam'      => ['🚫 سبام', 'rose'],
    ];

    public const TYPES = [
        'inquiry' => 'استفسار', 'test_drive' => 'تجربة قيادة', 'reservation' => 'حجز',
        'finance' => 'تقسيط', 'trade_in' => 'بدل / تقييم', 'contact' => 'تواصل',
    ];

    public const SOURCES = ['website' => '🌐 الموقع', 'facebook' => '📘 فيسبوك/إنستجرام', 'instagram' => '📸 إنستجرام', 'google' => '🔍 جوجل', 'tiktok' => '🎵 تيك توك', 'snapchat' => '👻 سناب شات'];

    private function base()
    {
        return Access::scope(DB::table('vehicle_inquiries as i')->whereNull('i.deleted_at'), 'i.branch_id');
    }

    public function index(Request $request): View
    {
        $view = in_array($request->query('view'), ['board', 'list', 'campaigns'], true) ? $request->query('view') : 'board';
        $f = [
            'q'        => trim((string) $request->query('q')),
            'type'     => array_key_exists((string) $request->query('type'), self::TYPES) ? $request->query('type') : null,
            'source'   => $request->query('source') ?: null,
            'assigned' => $request->query('assigned') ?: null,
            'status'   => array_key_exists((string) $request->query('status'), self::STAGES) ? $request->query('status') : null,
        ];
        $me = $request->user()->id;

        $q = $this->base()
            ->leftJoin('vehicles as v', 'v.id', '=', 'i.vehicle_id')
            ->leftJoin('car_brands as b', 'b.id', '=', 'v.brand_id')
            ->leftJoin('car_models as m', 'm.id', '=', 'v.model_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.assigned_to')
            ->when($f['q'] !== '', fn ($w) => $w->where(fn ($s) => $s->where('i.name', 'like', "%{$f['q']}%")->orWhere('i.phone', 'like', "%{$f['q']}%")
                ->orWhere('i.inquiry_number', 'like', "%{$f['q']}%")->orWhere('i.email', 'like', "%{$f['q']}%")))
            ->when($f['type'], fn ($w, $v) => $w->where('i.type', $v))
            ->when($f['source'], fn ($w, $v) => $w->where(fn ($s) => $s->where('i.source', $v)->orWhere('i.utm_campaign', $v)))
            ->when($f['assigned'] === 'me', fn ($w) => $w->where('i.assigned_to', $me))
            ->when($f['assigned'] === 'none', fn ($w) => $w->whereNull('i.assigned_to'))
            ->when(is_numeric($f['assigned']), fn ($w) => $w->where('i.assigned_to', (int) $f['assigned']))
            ->select('i.*', \App\Support\Lang::sel('b.name_ar as brand'), \App\Support\Lang::sel('m.name_ar as model'), 'v.manufacturing_year as year', 'v.selling_price', 'v.discount_price', 'u.name as assignee');

        $data = [
            'view' => $view, 'filters' => $f, 'stats' => $this->stats(),
            'staff' => Access::scope(DB::table('users')->whereIn('role', ['staff', 'admin'])->where('is_active', true), 'branch_id')->orderBy('name')->pluck('name', 'id'),
            'sources' => $this->base()->whereNotNull('i.source')->distinct()->pluck('i.source')->filter()->values(),
        ];

        if ($view === 'board') {
            $cols = [];
            foreach (array_keys(self::STAGES) as $st) {
                if ($st === 'spam') {
                    continue;
                }
                $c = (clone $q)->where('i.status', $st)->when($st === 'closed' || $st === 'converted', fn ($w) => $w->where('i.updated_at', '>=', now()->subDays(30)));
                $cols[$st] = ['count' => (clone $c)->count(), 'rows' => (clone $c)->orderByDesc('i.id')->limit(60)->get()];
            }
            $data['columns'] = $cols;
        } elseif ($view === 'list') {
            $data['rows'] = (clone $q)->when($f['status'], fn ($w, $v) => $w->where('i.status', $v), fn ($w) => $w->where('i.status', '!=', 'spam'))
                ->orderByDesc('i.id')->paginate(30)->withQueryString();
        } else {
            $data += $this->campaigns($request);
        }

        return view('admin.inquiries.index', $data);
    }

    private function stats(): array
    {
        $q = $this->base();
        $d30 = now()->subDays(30);
        $total90 = (clone $q)->where('i.created_at', '>=', now()->subDays(90))->where('i.status', '!=', 'spam')->count();
        $won90 = (clone $q)->where('i.created_at', '>=', now()->subDays(90))->where('i.status', 'converted')->count();
        $resp = (clone $q)->where('i.created_at', '>=', $d30)->whereNotNull('i.contacted_at')->get(['i.created_at', 'i.contacted_at'])
            ->map(fn ($r) => Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($r->contacted_at)));

        return [
            'new'        => (clone $q)->where('i.status', 'new')->count(),
            'late'       => (clone $q)->where('i.status', 'new')->where('i.created_at', '<', now()->subDay())->count(),
            'working'    => (clone $q)->whereIn('i.status', ['contacted', 'qualified'])->count(),
            'won_month'  => (clone $q)->where('i.status', 'converted')->where('i.converted_at', '>=', now()->startOfMonth())->count(),
            'conversion' => $total90 ? round($won90 / $total90 * 100, 1) : 0,
            'response'   => $resp->isNotEmpty() ? (int) round($resp->avg()) : null,
            'unassigned' => (clone $q)->whereIn('i.status', ['new', 'contacted', 'qualified'])->whereNull('i.assigned_to')->count(),
        ];
    }

    /** أداء الحملات: طلبات، مبيعات فعلية (بتتربط بالعميل أو برقم التليفون)، صرف، CPL، ROAS */
    private function campaigns(Request $request): array
    {
        $period = in_array($request->query('period'), ['month', 'last_month', '30', '90'], true) ? $request->query('period') : 'month';
        [$from, $to] = match ($period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            '30' => [now()->subDays(30)->startOfDay(), now()],
            '90' => [now()->subDays(90)->startOfDay(), now()],
            default => [now()->startOfMonth(), now()],
        };

        $leads = $this->base()->whereBetween('i.created_at', [$from, $to])->where('i.status', '!=', 'spam')
            ->get(['i.id', 'i.phone', 'i.source', 'i.utm_campaign', 'i.status', 'i.customer_id', 'i.created_at']);

        // المبيعات من أول المدة (العميل ممكن يشتري بعد الطلب بكام يوم)
        $digits = fn ($p) => substr(preg_replace('/\D/', '', (string) $p), -8);
        $sales = DB::table('sales_contracts as s')->whereNull('s.deleted_at')->where('s.contract_date', '>=', $from->toDateString())
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->tap(fn ($w) => Access::scope($w, 's.branch_id'))
            ->get(['s.id', 's.customer_id', 's.total_amount', 's.contract_date', 'c.phone']);
        $byCustomer = $sales->groupBy('customer_id');
        $byPhone = $sales->filter(fn ($s) => $s->phone)->groupBy(fn ($s) => $digits($s->phone));

        $rows = [];
        $usedContracts = [];
        foreach ($leads as $l) {
            $src = strtolower($l->source ?: 'website');
            $key = $src.'|'.($l->utm_campaign ?: '');
            $rows[$key] ??= ['source' => $src, 'campaign' => $l->utm_campaign, 'leads' => 0, 'qualified' => 0, 'won' => 0, 'revenue' => 0.0, 'sales' => 0, 'spend' => 0.0];
            $rows[$key]['leads']++;
            if (in_array($l->status, ['qualified', 'converted'], true)) {
                $rows[$key]['qualified']++;
            }
            if ($l->status === 'converted') {
                $rows[$key]['won']++;
            }
            $matches = collect($l->customer_id ? ($byCustomer[$l->customer_id] ?? []) : [])
                ->merge($digits($l->phone) ? ($byPhone[$digits($l->phone)] ?? []) : [])
                ->filter(fn ($s) => $s->contract_date >= substr((string) $l->created_at, 0, 10) && ! isset($usedContracts[$s->id]));
            foreach ($matches->unique('id') as $s) {
                $usedContracts[$s->id] = true;
                $rows[$key]['revenue'] += (float) $s->total_amount;
                $rows[$key]['sales']++;
            }
        }

        $spends = collect();
        if (Access::branchIds() === null) {
            $spends = DB::table('ad_spends')->whereBetween('month', [$from->format('Y-m'), $to->format('Y-m')])->orderByDesc('month')->get();
            foreach ($spends as $sp) {
                $key = strtolower($sp->source).'|'.($sp->campaign ?? '');
                $rows[$key] ??= ['source' => strtolower($sp->source), 'campaign' => $sp->campaign, 'leads' => 0, 'qualified' => 0, 'won' => 0, 'revenue' => 0.0, 'sales' => 0, 'spend' => 0.0];
                $rows[$key]['spend'] += (float) $sp->amount;
            }
        }

        $rows = collect($rows)->map(function ($r) {
            $r['cpl'] = $r['spend'] > 0 && $r['leads'] ? Money::round($r['spend'] / $r['leads']) : null;
            $r['cps'] = $r['spend'] > 0 && $r['sales'] ? Money::round($r['spend'] / $r['sales']) : null;
            $r['roas'] = $r['spend'] > 0 ? round($r['revenue'] / $r['spend'], 1) : null;
            $r['rate'] = $r['leads'] ? round($r['won'] / $r['leads'] * 100, 1) : 0;

            return $r;
        })->sortByDesc(fn ($r) => [$r['revenue'], $r['leads']])->values();

        $tot = ['leads' => $rows->sum('leads'), 'won' => $rows->sum('won'), 'sales' => $rows->sum('sales'), 'revenue' => $rows->sum('revenue'), 'spend' => $rows->sum('spend')];
        $tot['roas'] = $tot['spend'] > 0 ? round($tot['revenue'] / $tot['spend'], 1) : null;
        $tot['cpl'] = $tot['spend'] > 0 && $tot['leads'] ? Money::round($tot['spend'] / $tot['leads']) : null;

        return ['campaignRows' => $rows, 'totals' => $tot, 'period' => $period, 'spends' => $spends, 'canSpend' => Access::branchIds() === null,
            'campaignNames' => DB::table('vehicle_inquiries')->whereNotNull('utm_campaign')->distinct()->limit(200)->pluck('utm_campaign')];
    }

    /** حفظ من نافذة التفاصيل */
    public function updateStatus(Request $request, int $inquiry): RedirectResponse
    {
        $row = $this->base()->where('i.id', $inquiry)->first();
        abort_unless($row, 404);
        $data = $request->validate([
            'status'      => ['required', Rule::in(array_keys(self::STAGES))],
            'staff_notes' => ['nullable', 'string', 'max:3000'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereIn('role', ['staff', 'admin'])],
        ]);
        DB::table('vehicle_inquiries')->where('id', $inquiry)->update($this->stageFields($row, $data['status']) + [
            'staff_notes' => $data['staff_notes'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'updated_at'  => now(),
        ]);

        return back()->with('status', 'تم حفظ الطلب');
    }

    /** السحب والإفلات بين المراحل */
    public function move(Request $request, int $inquiry): JsonResponse
    {
        $row = $this->base()->where('i.id', $inquiry)->first();
        abort_unless($row, 404);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STAGES))]]);
        DB::table('vehicle_inquiries')->where('id', $inquiry)->update($this->stageFields($row, $data['status']) + [
            'assigned_to' => $row->assigned_to ?? ($data['status'] !== 'new' ? $request->user()->id : null), // اللي حرّكه يمسكه
            'updated_at'  => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function stageFields(object $row, string $status): array
    {
        $out = ['status' => $status];
        if ($status !== 'new' && ! $row->contacted_at) {
            $out['contacted_at'] = now();
        }
        if ($status === 'converted') {
            $out['converted_at'] = $row->converted_at ?? now();
            // نربطه بالعميل لو موجود بنفس رقم التليفون
            if (! $row->customer_id) {
                $tail = substr(preg_replace('/\D/', '', (string) $row->phone), -8);
                if (strlen($tail) >= 7) {
                    $out['customer_id'] = DB::table('customers')->whereNull('deleted_at')
                        ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ['%'.$tail])->value('id');
                }
            }
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    public function storeSpend(Request $request): RedirectResponse
    {
        abort_unless(Access::branchIds() === null, 403);
        $data = $request->validate([
            'source'   => ['required', 'string', 'max:60'],
            'campaign' => ['nullable', 'string', 'max:190'],
            'month'    => ['required', 'date_format:Y-m'],
            'amount'   => ['required', 'numeric', 'min:0.001'],
            'notes'    => ['nullable', 'string', 'max:190'],
        ], ['required' => 'الحقل ده مطلوب.', 'month.date_format' => 'اختار الشهر.']);
        DB::table('ad_spends')->insert($data + ['source' => strtolower($data['source']), 'amount' => Money::round($data['amount']), 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'اتسجّل صرف الإعلان');
    }

    public function destroySpend(int $spend): RedirectResponse
    {
        abort_unless(Access::branchIds() === null, 403);
        DB::table('ad_spends')->where('id', $spend)->delete();

        return back()->with('status', 'اتمسح');
    }
}
