<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Blog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlogCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        DB::table('blog_categories')->insert($this->data($request, null) + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تم إضافة التصنيف');
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        DB::table('blog_categories')->where('id', $category)->update($this->data($request, $category) + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ التصنيف');
    }

    public function destroy(int $category): RedirectResponse
    {
        DB::table('blog_posts')->where('category_id', $category)->update(['category_id' => null]);
        DB::table('blog_categories')->where('id', $category)->delete();

        return back()->with('status', 'تم مسح التصنيف (المقالات اللي فيه بقت من غير تصنيف)');
    }

    private function data(Request $request, ?int $id): array
    {
        $request->merge(['slug' => Blog::slugify($request->input('slug') ?: $request->input('name_en') ?: $request->input('name_ar'))]);

        return $request->validate([
            'name_ar'        => ['required', 'string', 'max:120'],
            'name_en'        => ['nullable', 'string', 'max:120'],
            'slug'           => ['required', 'string', 'max:120', Rule::unique('blog_categories', 'slug')->ignore($id)],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'sort'           => ['nullable', 'integer', 'min:0'],
        ], ['name_ar.required' => 'الاسم مطلوب.', 'slug.unique' => 'الرابط مستخدم.']) + ['sort' => (int) $request->input('sort', 0)];
    }
}
