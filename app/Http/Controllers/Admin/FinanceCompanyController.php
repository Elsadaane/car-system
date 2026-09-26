<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * شركات التمويل (البنوك وشركات التقسيط) اللي المعرض بيتعامل معاها.
 */
class FinanceCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        $companies = DB::table('finance_companies')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->select('finance_companies.*',
                DB::raw('(select count(*) from finance_requests fr where fr.finance_company_id = finance_companies.id and fr.deleted_at is null) as requests_count'),
                DB::raw("(select count(*) from finance_requests fr where fr.finance_company_id = finance_companies.id and fr.deleted_at is null and fr.status in ('approved','paid_off')) as approved_count"),
                DB::raw("(select coalesce(sum(approved_amount), 0) from finance_requests fr where fr.finance_company_id = finance_companies.id and fr.deleted_at is null and fr.status in ('approved','paid_off')) as approved_total"))
            ->orderBy('name_ar')
            ->get();

        return view('admin.finance.companies', ['companies' => $companies, 'q' => $q]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::table('finance_companies')->insert($this->data($request, null) + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تم إضافة الشركة');
    }

    public function update(Request $request, int $company): RedirectResponse
    {
        abort_unless(DB::table('finance_companies')->where('id', $company)->exists(), 404);
        DB::table('finance_companies')->where('id', $company)->update($this->data($request, $company) + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(int $company): RedirectResponse
    {
        if (DB::table('finance_requests')->where('finance_company_id', $company)->exists()) {
            return back()->with('error', 'الشركة عليها طلبات تمويل. اقفلها بدل المسح.');
        }

        DB::table('finance_companies')->where('id', $company)->delete();

        return back()->with('status', 'تم المسح');
    }

    private function data(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'name_ar'  => ['required', 'string', 'max:150', Rule::unique('finance_companies', 'name_ar')->ignore($id)],
            'name_en'  => ['nullable', 'string', 'max:150'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'email'    => ['nullable', 'email', 'max:150'],
            'website'  => ['nullable', 'string', 'max:255'],
            'address'  => ['nullable', 'string', 'max:1000'],
            'terms_ar' => ['nullable', 'string', 'max:5000'],
        ], ['required' => 'الحقل ده مطلوب.', 'unique' => 'الشركة دي موجودة قبل كده.', 'email' => 'الإيميل مش صحيح.']);

        return [
            'name_ar'   => $data['name_ar'],
            'name_en'   => $data['name_en'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'email'     => $data['email'] ?? null,
            'website'   => $data['website'] ?? null,
            'address'   => $data['address'] ?? null,
            'terms_ar'  => $data['terms_ar'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
