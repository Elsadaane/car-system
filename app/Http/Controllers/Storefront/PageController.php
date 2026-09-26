<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Company;
use App\Support\Showroom;
use App\Support\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PageController extends Controller
{
    public function contact(Request $request): View
    {
        Site::captureUtm($request);

        return view('storefront.pages.contact', ['branches' => self::branches()]);
    }

    public function sell(Request $request): View
    {
        Site::captureUtm($request);

        return view('storefront.pages.sell', [
            'brands' => DB::table('car_brands')->orderBy('name_ar')->pluck('name_ar'),
            'faqs'   => HomeController::blocks('faq'),
        ]);
    }

    public function finance(Request $request): View
    {
        Site::captureUtm($request);

        return view('storefront.pages.finance', [
            'companies' => DB::table('finance_companies')->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']),
            'price'     => (float) $request->query('price', 0),
            'vehicleId' => (int) $request->query('vehicle', 0) ?: null,
            'faqs'      => HomeController::blocks('faq'),
        ]);
    }

    public function about(Request $request): View
    {
        Site::captureUtm($request);

        return view('storefront.pages.about', [
            'features'     => HomeController::blocks('feature'),
            'testimonials' => HomeController::blocks('testimonial'),
            'branches'     => self::branches(),
            'stats'        => [
                'cars'     => Showroom::query()->count(),
                'sold'     => DB::table('vehicles')->where('status', 'sold')->count(),
                'branches' => DB::table('branches')->count(),
            ],
        ]);
    }

    public function terms(): View
    {
        $en = app()->getLocale() === 'en' ? Company::get('terms_and_conditions_en') : null;

        return view('storefront.pages.text', ['title' => __('الشروط والأحكام'), 'body' => $en ?: Company::get('terms_and_conditions_ar') ?: Company::get('terms_ar')]);
    }

    public function privacy(): View
    {
        $en = app()->getLocale() === 'en' ? Company::get('privacy_policy_en') : null;

        return view('storefront.pages.text', ['title' => __('سياسة الخصوصية'), 'body' => $en ?: Company::get('privacy_policy_ar') ?: Site::get('privacy_policy')]);
    }

    public static function branches()
    {
        return DB::table('branches')->orderBy('id')->get();
    }
}
