<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\SalesContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * طرق الدفع + الرصيد الحالي في كل طريقة:
 *   الرصيد = اللي دخل من العملاء − اللي طلع (للملاك والموردين)
 */
class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $in = (new SalesContract)->getMorphClass();
        $sums = DB::table('payments')->whereNull('deleted_at')->where('status', 'completed')
            ->groupBy('payment_method_id')
            ->select('payment_method_id',
                DB::raw('sum(case when payable_type = '.DB::getPdo()->quote($in).' then amount else 0 end) as total_in'),
                DB::raw('sum(case when payable_type <> '.DB::getPdo()->quote($in).' then amount else 0 end) as total_out'),
                DB::raw('count(*) as cnt'),
                DB::raw('max(payment_date) as last_date'))
            ->get()->keyBy('payment_method_id');

        $methods = PaymentMethod::orderBy('sort_order')->orderBy('id')->get()->map(function ($m) use ($sums) {
            $s = $sums[$m->id] ?? null;
            $m->total_in = (float) ($s->total_in ?? 0);
            $m->total_out = (float) ($s->total_out ?? 0);
            $m->balance = $m->total_in - $m->total_out;
            $m->payments_count = (int) ($s->cnt ?? 0);
            $m->last_date = $s->last_date ?? null;

            return $m;
        });

        return view('admin.payment-methods.index', ['methods' => $methods]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new PaymentMethod);

        return back()->with('status', 'تم إضافة طريقة الدفع');
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->save($request, $paymentMethod);

        return back()->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        if (DB::table('payments')->where('payment_method_id', $paymentMethod->id)->exists()) {
            return back()->with('error', 'فيه دفعات متسجلة بالطريقة دي. اقفلها بدل المسح.');
        }

        $paymentMethod->delete();

        return back()->with('status', 'تم المسح');
    }

    private function save(Request $request, PaymentMethod $method): void
    {
        $data = $request->validate([
            'name_ar'        => ['required', 'string', 'max:100', Rule::unique('payment_methods', 'name_ar')->ignore($method->id)],
            'name_en'        => ['nullable', 'string', 'max:100'],
            'description_ar' => ['nullable', 'string', 'max:500'],
            'sort_order'     => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['required' => 'الحقل ده مطلوب.', 'unique' => 'الاسم ده موجود قبل كده.']);

        $method->forceFill([
            'name_ar'        => $data['name_ar'],
            'name_en'        => $data['name_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'sort_order'     => (int) ($data['sort_order'] ?? 0),
            'is_active'      => $request->boolean('is_active'),
        ])->save();
    }
}
