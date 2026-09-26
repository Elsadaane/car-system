<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Blog;
use App\Support\Media;
use App\Support\Showroom;
use App\Support\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlogController extends Controller
{
    /** المقالات المنشورة فعلاً (المجدولة مش بتظهر قبل ميعادها) */
    public static function published()
    {
        return DB::table('blog_posts')->whereNull('blog_posts.deleted_at')
            ->where('blog_posts.status', 'published')->where('blog_posts.published_at', '<=', now());
    }

    public function index(Request $request, ?string $category = null): View
    {
        Site::captureUtm($request);
        $cat = $category ? DB::table('blog_categories')->where('slug', $category)->first() : null;
        abort_if($category && ! $cat, 404);
        $q = trim((string) $request->query('q'));

        $posts = self::published()
            ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_posts.category_id')
            ->when($cat, fn ($w) => $w->where('blog_posts.category_id', $cat->id))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title_ar', 'like', "%$q%")->orWhere('excerpt_ar', 'like', "%$q%")->orWhere('title_en', 'like', "%$q%")))
            ->select('blog_posts.id', 'blog_posts.slug', 'title_ar', 'title_en', 'excerpt_ar', 'excerpt_en', 'cover_image', 'cover_alt', 'published_at', 'reading_minutes', 'views_count', 'is_featured',
                'blog_categories.name_ar as category_ar', 'blog_categories.name_en as category_en', 'blog_categories.slug as category_slug')
            ->orderByDesc('is_featured')->orderByDesc('published_at')
            ->paginate(12)->withQueryString();

        return view('storefront.blog.index', [
            'posts'      => $posts,
            'category'   => $cat,
            'q'          => $q,
            'categories' => DB::table('blog_categories')->orderBy('sort')->get()->map(function ($c) {
                $c->count = self::published()->where('category_id', $c->id)->count();

                return $c;
            })->where('count', '>', 0)->values(),
            'popular'    => self::published()->orderByDesc('views_count')->limit(5)->get(['slug', 'title_ar', 'title_en', 'cover_image', 'published_at']),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        Site::captureUtm($request);
        $post = self::published()->where('blog_posts.slug', $slug)->first();
        abort_unless($post, 404);

        $seen = $request->session()->get('seen_posts', []);
        if (! in_array($post->id, $seen, true)) {
            DB::table('blog_posts')->where('id', $post->id)->increment('views_count');
            $request->session()->put('seen_posts', array_slice(array_merge($seen, [$post->id]), -100));
        }

        [$body, $toc] = Blog::withToc((string) Blog::t($post, 'body'));

        // العربيات: المختارة يدوي الأول، وبعدين أوتوماتيك بالموديل ثم الماركة
        $manual = array_map('intval', Media::list($post->vehicle_ids));
        $cars = collect();
        if ($manual) {
            $cars = Showroom::query()->whereIn('vehicles.id', $manual)->get()
                ->sortBy(fn ($v) => array_search($v->id, $manual, true))->values();
        }
        if ($cars->count() < 4 && ($post->model_id || $post->brand_id)) {
            $auto = Showroom::query()->whereNotIn('vehicles.id', $cars->pluck('id')->all() ?: [0])
                ->where(fn ($w) => $w->when($post->model_id, fn ($x) => $x->orWhere('vehicles.model_id', $post->model_id))
                    ->when($post->brand_id, fn ($x) => $x->orWhere('vehicles.brand_id', $post->brand_id)))
                ->orderByRaw('vehicles.model_id = ? desc', [(int) $post->model_id])->orderByDesc('vehicles.is_featured')->latest('vehicles.id')
                ->limit(4 - $cars->count())->get();
            $cars = $cars->concat($auto);
        }

        return view('storefront.blog.show', [
            'post'     => $post,
            'body'     => $body,
            'toc'      => $toc,
            'faq'      => collect(json_decode((string) $post->faq, true) ?: []),
            'category' => $post->category_id ? DB::table('blog_categories')->find($post->category_id) : null,
            'author'   => $post->user_id ? DB::table('users')->where('id', $post->user_id)->value('name') : null,
            'cars'     => $cars->map(fn ($v) => Showroom::card($v)),
            'related'  => self::published()->where('blog_posts.id', '!=', $post->id)
                ->when($post->category_id, fn ($w) => $w->orderByRaw('category_id = ? desc', [$post->category_id]))
                ->orderByDesc('published_at')->limit(3)->get(['slug', 'title_ar', 'title_en', 'cover_image', 'cover_alt', 'published_at', 'reading_minutes']),
        ]);
    }
}
