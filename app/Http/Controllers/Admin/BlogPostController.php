<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Blog;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('q'));
        $now = now();

        $posts = DB::table('blog_posts')->whereNull('blog_posts.deleted_at')
            ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_posts.category_id')
            ->when($status === 'draft', fn ($w) => $w->where('blog_posts.status', 'draft'))
            ->when($status === 'published', fn ($w) => $w->where('blog_posts.status', 'published')->where('blog_posts.published_at', '<=', $now))
            ->when($status === 'scheduled', fn ($w) => $w->where('blog_posts.status', 'published')->where('blog_posts.published_at', '>', $now))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title_ar', 'like', "%$q%")->orWhere('title_en', 'like', "%$q%")->orWhere('focus_keyword', 'like', "%$q%")))
            ->select('blog_posts.*', \App\Support\Lang::sel('blog_categories.name_ar as category'))
            ->orderByDesc(DB::raw('COALESCE(blog_posts.published_at, blog_posts.created_at)'))
            ->paginate(20)->withQueryString();

        $base = DB::table('blog_posts')->whereNull('deleted_at');

        return view('admin.blog.index', [
            'posts'      => $posts,
            'status'     => $status,
            'categories' => DB::table('blog_categories')->orderBy('sort')->get()->map(function ($c) {
                $c->posts = DB::table('blog_posts')->whereNull('deleted_at')->where('category_id', $c->id)->count();

                return $c;
            }),
            'counts'     => [
                'all'       => (clone $base)->count(),
                'published' => (clone $base)->where('status', 'published')->where('published_at', '<=', $now)->count(),
                'scheduled' => (clone $base)->where('status', 'published')->where('published_at', '>', $now)->count(),
                'draft'     => (clone $base)->where('status', 'draft')->count(),
                'views'     => (int) (clone $base)->sum('views_count'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', $this->formData(null));
    }

    public function edit(int $post): View
    {
        $row = DB::table('blog_posts')->whereNull('deleted_at')->find($post);
        abort_unless($row, 404);

        return view('admin.blog.form', $this->formData($row));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request, null);
        $id = DB::table('blog_posts')->insertGetId($data + ['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('admin.blog.edit', $id)->with('status', $data['status'] === 'published' ? 'تم نشر المقال 🎉' : 'تم حفظ المسودة');
    }

    public function update(Request $request, int $post): RedirectResponse
    {
        $row = DB::table('blog_posts')->whereNull('deleted_at')->find($post);
        abort_unless($row, 404);
        DB::table('blog_posts')->where('id', $post)->update($this->data($request, $row) + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ المقال');
    }

    public function destroy(int $post): RedirectResponse
    {
        DB::table('blog_posts')->where('id', $post)->update(['deleted_at' => now()]);

        return redirect()->route('admin.blog.index')->with('status', 'تم مسح المقال');
    }

    /** رفع صورة من جوه المحرّر */
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096']]);

        return response()->json(['url' => Media::url(Media::store($request->file('image'), 'blog'))]);
    }

    private function formData(?object $row): array
    {
        $vehicleIds = $row ? Media::list($row->vehicle_ids) : [];

        return [
            'post'       => $row,
            'categories' => DB::table('blog_categories')->orderBy('sort')->pluckLocal('name', 'id'),
            'brands'     => DB::table('car_brands')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'models'     => DB::table('car_models')->orderBy('name_ar')->get(['id', 'brand_id', 'name_ar']),
            'vehicles'   => DB::table('vehicles')->join('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')->join('car_models', 'car_models.id', '=', 'vehicles.model_id')
                ->whereNull('vehicles.deleted_at')->where(fn ($w) => $w->whereIn('vehicles.status', ['available', 'reserved'])->orWhereIn('vehicles.id', array_map('intval', $vehicleIds)))
                ->orderByDesc('vehicles.id')->limit(500)
                ->get(['vehicles.id', 'vehicles.vehicle_code', 'vehicles.manufacturing_year', 'vehicles.status', \App\Support\Lang::sel('car_brands.name_ar as brand'), \App\Support\Lang::sel('car_models.name_ar as model')]),
            'vehicleIds' => array_map('intval', $vehicleIds),
            'faq'        => $row && $row->faq ? (json_decode($row->faq, true) ?: []) : [],
        ];
    }

    private function data(Request $request, ?object $row): array
    {
        $request->merge(['slug' => Blog::slugify($request->input('slug') ?: $request->input('title_en') ?: $request->input('title_ar'))]);

        $v = $request->validate([
            'title_ar'            => ['required', 'string', 'max:190'],
            'title_en'            => ['nullable', 'string', 'max:190'],
            'slug'                => ['required', 'string', 'max:190', Rule::unique('blog_posts', 'slug')->ignore($row?->id)],
            'category_id'         => ['nullable', 'exists:blog_categories,id'],
            'excerpt_ar'          => ['nullable', 'string', 'max:500'],
            'excerpt_en'          => ['nullable', 'string', 'max:500'],
            'body_ar'             => ['nullable', 'string', 'max:300000'],
            'body_en'             => ['nullable', 'string', 'max:300000'],
            'cover'               => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_alt'           => ['nullable', 'string', 'max:190'],
            'focus_keyword'       => ['nullable', 'string', 'max:100'],
            'meta_title_ar'       => ['nullable', 'string', 'max:190'],
            'meta_title_en'       => ['nullable', 'string', 'max:190'],
            'meta_description_ar' => ['nullable', 'string', 'max:300'],
            'meta_description_en' => ['nullable', 'string', 'max:300'],
            'brand_id'            => ['nullable', 'exists:car_brands,id'],
            'model_id'            => ['nullable', 'exists:car_models,id'],
            'vehicle_ids'         => ['nullable', 'array', 'max:8'],
            'vehicle_ids.*'       => ['integer', 'exists:vehicles,id'],
            'faq'                 => ['nullable', 'array', 'max:15'],
            'faq.*.q_ar'          => ['nullable', 'string', 'max:300'],
            'faq.*.a_ar'          => ['nullable', 'string', 'max:2000'],
            'faq.*.q_en'          => ['nullable', 'string', 'max:300'],
            'faq.*.a_en'          => ['nullable', 'string', 'max:2000'],
            'action'              => ['required', 'in:draft,publish,schedule'],
            'publish_at'          => ['nullable', 'required_if:action,schedule', 'date'],
        ], [
            'title_ar.required' => 'العنوان بالعربي مطلوب.',
            'slug.unique'       => 'الرابط ده مستخدم في مقال تاني — غيّره.',
            'publish_at.required_if' => 'حدد ميعاد النشر.',
            'cover.image'       => 'الغلاف لازم يكون صورة.',
        ]);

        $bodyAr = Blog::sanitize($v['body_ar'] ?? '');
        $bodyEn = Blog::sanitize($v['body_en'] ?? '');
        $faq = array_values(array_filter(array_map(fn ($f) => array_map(fn ($x) => trim(strip_tags((string) $x)), $f), $v['faq'] ?? []), fn ($f) => ($f['q_ar'] ?? '') !== '' && ($f['a_ar'] ?? '') !== ''));

        $data = [
            'title_ar'      => strip_tags($v['title_ar']),
            'title_en'      => isset($v['title_en']) ? strip_tags($v['title_en']) : null,
            'slug'          => $v['slug'],
            'category_id'   => $v['category_id'] ?? null,
            'excerpt_ar'    => isset($v['excerpt_ar']) ? strip_tags($v['excerpt_ar']) : null,
            'excerpt_en'    => isset($v['excerpt_en']) ? strip_tags($v['excerpt_en']) : null,
            'body_ar'       => $bodyAr,
            'body_en'       => $bodyEn ?: null,
            'cover_alt'     => $v['cover_alt'] ?? null,
            'focus_keyword' => $v['focus_keyword'] ?? null,
            'meta_title_ar' => $v['meta_title_ar'] ?? null,
            'meta_title_en' => $v['meta_title_en'] ?? null,
            'meta_description_ar' => $v['meta_description_ar'] ?? null,
            'meta_description_en' => $v['meta_description_en'] ?? null,
            'brand_id'      => $v['brand_id'] ?? null,
            'model_id'      => $v['model_id'] ?? null,
            'vehicle_ids'   => json_encode(array_values(array_unique(array_map('intval', $v['vehicle_ids'] ?? [])))),
            'faq'           => $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE) : null,
            'is_featured'   => $request->boolean('is_featured'),
            'reading_minutes' => Blog::readingMinutes($bodyAr),
        ];

        if ($request->hasFile('cover')) {
            Media::delete($row?->cover_image);
            $data['cover_image'] = Media::store($request->file('cover'), 'blog');
        } elseif ($request->boolean('remove_cover')) {
            Media::delete($row?->cover_image);
            $data['cover_image'] = null;
        }

        match ($v['action']) {
            'draft'    => $data += ['status' => 'draft'],
            'publish'  => $data += ['status' => 'published', 'published_at' => $row?->published_at && $row->status === 'published' && \Illuminate\Support\Carbon::parse($row->published_at)->lte(now()) ? $row->published_at : now()],
            'schedule' => $data += ['status' => 'published', 'published_at' => \Illuminate\Support\Carbon::parse($v['publish_at'])],
        };

        return $data;
    }
}
