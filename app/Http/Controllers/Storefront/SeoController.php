<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Showroom;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect(['storefront.home', 'storefront.vehicles.index', 'storefront.offers', 'storefront.finance', 'storefront.sell', 'storefront.contact', 'storefront.about', 'storefront.blog.index'])
            ->map(fn ($r) => ['loc' => route($r), 'lastmod' => now()->toDateString(), 'priority' => $r === 'storefront.home' ? '1.0' : '0.8']);

        Showroom::query()->select('vehicles.*')->latest('vehicles.updated_at')->limit(5000)->get()
            ->each(fn ($v) => $urls->push(['loc' => route('storefront.vehicles.show', $v), 'lastmod' => optional($v->updated_at)->toDateString(), 'priority' => '0.9']));

        BlogController::published()->orderByDesc('published_at')->limit(5000)->get(['slug', 'updated_at'])
            ->each(fn ($p) => $urls->push(['loc' => route('storefront.blog.show', $p->slug), 'lastmod' => substr((string) $p->updated_at, 0, 10), 'priority' => '0.7']));
        \Illuminate\Support\Facades\DB::table('blog_categories')->get(['slug'])
            ->each(fn ($c) => $urls->push(['loc' => route('storefront.blog.category', $c->slug), 'lastmod' => null, 'priority' => '0.5']));

        // نفس الصفحات بالإنجليزي تحت /en
        $urls = $urls->flatMap(function ($u) {
            $en = preg_replace('#^('.preg_quote(url('/'), '#').')(/|$)#', '$1/en$2', $u['loc'], 1);

            return [$u, ['loc' => rtrim($en, '/'), 'lastmod' => $u['lastmod'], 'priority' => $u['priority']]];
        });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u['loc']).'</loc>'.($u['lastmod'] ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '').'<priority>'.$u['priority'].'</priority></url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /customer\nDisallow: /login\nAllow: /\n\nSitemap: ".route('storefront.sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
