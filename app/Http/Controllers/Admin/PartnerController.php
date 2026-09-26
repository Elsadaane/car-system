<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Partners;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** الشركاء في العربيات: القايمة، كشف الحساب، الحركات، ونصيبهم في كل عربية */
class PartnerController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $partners = DB::table('partners')->whereNull('deleted_at')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->orderBy('name')->get()
            ->map(function ($p) {
                $p->st = Partners::statement($p->id)['totals'];
                $p->cars = DB::table('vehicle_partners')->where('partner_id', $p->id)->count();

                return $p;
            });

        return view('admin.partners.index', [
            'partners' => $partners,
            'q'        => $q,
            'totals'   => [
                'active'  => $partners->sum(fn ($p) => $p->st['active']),
                'balance' => $partners->sum(fn ($p) => max(0, $p->st['balance'])),
                'profit'  => $partners->sum(fn ($p) => $p->st['profit']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $id = DB::table('partners')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('admin.partners.show', $id)->with('status', 'اتضاف الشريك. ضيفه على عربياته من صفحة كل عربية.');
    }

    public function show(int $partner): View
    {
        $p = DB::table('partners')->where('id', $partner)->whereNull('deleted_at')->first() ?? abort(404);

        return view('admin.partners.show', ['partner' => $p] + Partners::statement($p->id) + [
            'accounts' => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->orderBy('name_ar')->pluckLocal('name', 'id'),
        ]);
    }

    public function update(Request $request, int $partner): RedirectResponse
    {
        DB::table('partners')->where('id', $partner)->update($this->validated($request) + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ بيانات الشريك');
    }

    public function destroy(int $partner): RedirectResponse
    {
        $t = Partners::statement($partner)['totals'];
        if (abs($t['balance']) > 0.0005 || $t['active'] > 0) {
            return back()->with('error', 'الشريك ليه رصيد أو داخل في عربيات لسه متباعتش — صفّي حسابه الأول.');
        }
        DB::table('partners')->where('id', $partner)->update(['deleted_at' => now()]);

        return redirect()->route('admin.partners.index')->with('status', 'اتمسح الشريك');
    }

    /** استلام رأس مال منه / دفعة له */
    public function storeTransaction(Request $request, int $partner): RedirectResponse
    {
        $data = $request->validate([
            'kind'                 => ['required', 'in:capital_in,payout'],
            'amount'               => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'entry_date'           => ['required', 'date', 'before_or_equal:today'],
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')->whereNull('deleted_at')],
            'vehicle_id'           => ['nullable', Rule::exists('vehicle_partners', 'vehicle_id')->where('partner_id', $partner)],
            'reference'            => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ], ['financial_account_id.required' => 'اختار الخزنة.', 'vehicle_id.exists' => 'الشريك مش داخل في العربية دي.']);

        if ($data['kind'] === 'payout') {
            $bal = Partners::statement($partner)['totals']['balance'];
            if ((float) $data['amount'] > $bal + 0.0005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من رصيد الشريك المستحق ('.Money::format($bal).'). رصيده بيتحسب من العربيات اللي اتباعت بس.']);
            }
        }

        DB::table('partner_transactions')->insert($data + [
            'partner_id' => $partner,
            'amount'     => Money::round($data['amount']),
            'user_id'    => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', ($data['kind'] === 'payout' ? 'اتسجلت دفعة للشريك ' : 'اتسجل استلام من الشريك ').Money::format($data['amount']));
    }

    public function destroyTransaction(int $partner, int $tx): RedirectResponse
    {
        DB::table('partner_transactions')->where('id', $tx)->where('partner_id', $partner)->update(['deleted_at' => now()]);

        return back()->with('status', 'اتمسحت الحركة');
    }

    /** إضافة/تعديل نصيب شريك في عربية (من صفحة العربية) */
    public function attach(Request $request, int $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'partner_id'     => ['nullable', Rule::exists('partners', 'id')->whereNull('deleted_at')],
            'new_name'       => ['nullable', 'required_without:partner_id', 'string', 'max:150'],
            'new_phone'      => ['nullable', 'string', 'max:30'],
            'capital'        => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'profit_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:255'],
        ], ['new_name.required_without' => 'اختار شريك أو اكتب اسم شريك جديد.', 'profit_percent.required' => 'اكتب نسبة الشريك من الربح.']);

        abort_unless(DB::table('vehicles')->where('id', $vehicle)->whereNull('deleted_at')->exists(), 404);

        $partnerId = $data['partner_id'] ?? DB::table('partners')->insertGetId([
            'name' => trim($data['new_name']), 'phone' => $data['new_phone'] ?? null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $others = (float) DB::table('vehicle_partners')->where('vehicle_id', $vehicle)->where('partner_id', '!=', $partnerId)->sum('profit_percent');
        if ($others + (float) $data['profit_percent'] > 100.0001) {
            throw ValidationException::withMessages(['profit_percent' => 'مجموع نسب الشركاء في العربية دي هيعدّي 100% (الباقي '.(100 - $others).'%).']);
        }

        DB::table('vehicle_partners')->updateOrInsert(
            ['vehicle_id' => $vehicle, 'partner_id' => $partnerId],
            ['capital' => Money::round($data['capital'] ?? 0), 'profit_percent' => $data['profit_percent'], 'notes' => $data['notes'] ?? null, 'updated_at' => now(), 'created_at' => now()]
        );

        return back()->with('status', 'اتحفظ نصيب الشريك في العربية');
    }

    public function detach(int $vehicle, int $partner): RedirectResponse
    {
        if (\App\Models\SalesContract::where('vehicle_id', $vehicle)->exists()) {
            return back()->with('error', 'العربية اتباعت ونصيب الشريك دخل في حسابه — مينفعش تشيله دلوقتي.');
        }
        DB::table('vehicle_partners')->where('vehicle_id', $vehicle)->where('partner_id', $partner)->delete();

        return back()->with('status', 'اتشال الشريك من العربية');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ], ['name.required' => 'اكتب اسم الشريك.']);

        return $data + ['is_active' => true];
    }
}
