<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * تصنيفات (رئيسي + فرعي) للمصروفات والإيرادات والأصول.
 */
class FinanceCategoryController extends Controller
{
    public const KINDS = ['expense' => 'المصروفات', 'revenue' => 'الإيرادات', 'asset' => 'الأصول'];

    public function index(string $kind): View
    {
        $table = $kind === 'asset' ? 'assets' : 'cash_entries';
        $col = $kind === 'asset' ? 'purchase_cost' : 'total_amount';

        $all = DB::table('finance_categories')->where('kind', $kind)->orderBy('name_ar')->get()->map(function ($c) use ($table, $col) {
            $q = DB::table($table)->whereNull('deleted_at')->where(fn ($w) => $w->where('category_id', $c->id)->orWhere('sub_category_id', $c->id));
            $c->uses = (clone $q)->count();
            $c->total = (float) (clone $q)->sum($col);

            return $c;
        });

        return view('admin.finance.categories', [
            'kind'  => $kind,
            'mains' => $all->whereNull('parent_id')->values(),
            'subs'  => $all->whereNotNull('parent_id')->groupBy('parent_id'),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        DB::table('finance_categories')->insert($this->data($request, $kind) + ['kind' => $kind, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تم إضافة التصنيف');
    }

    public function update(Request $request, string $kind, int $category): RedirectResponse
    {
        $data = $this->data($request, $kind);
        if ((int) ($data['parent_id'] ?? 0) === $category) {
            $data['parent_id'] = null;
        }
        DB::table('finance_categories')->where('id', $category)->where('kind', $kind)->update($data + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(string $kind, int $category): RedirectResponse
    {
        $table = $kind === 'asset' ? 'assets' : 'cash_entries';
        $ids = DB::table('finance_categories')->where('id', $category)->orWhere('parent_id', $category)->pluck('id');
        if (DB::table($table)->where(fn ($w) => $w->whereIn('category_id', $ids)->orWhereIn('sub_category_id', $ids))->exists()) {
            return back()->with('error', 'التصنيف ده مستخدم. اقفله بدل المسح.');
        }

        DB::table('finance_categories')->where('id', $category)->delete();

        return back()->with('status', 'تم المسح');
    }

    private function data(Request $request, string $kind): array
    {
        $data = $request->validate([
            'name_ar'   => ['required', 'string', 'max:120'],
            'name_en'   => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', Rule::exists('finance_categories', 'id')->where('kind', $kind)->whereNull('parent_id')],
        ], ['required' => 'الاسم مطلوب.', 'exists' => 'التصنيف الرئيسي مش صحيح.']);

        return $data + ['is_active' => $request->boolean('is_active', true)];
    }
}
