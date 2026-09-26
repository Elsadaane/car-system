<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Media;
use App\Support\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SiteSettingsController extends Controller
{
    public function index(): View
    {
        $leads = DB::table('vehicle_inquiries')->whereNull('deleted_at');

        return view('admin.site.settings', [
            'values' => Site::all(),
            'stats'  => [
                'today'   => (clone $leads)->whereDate('created_at', today())->count(),
                'month'   => (clone $leads)->where('created_at', '>=', now()->startOfMonth())->count(),
                'new'     => (clone $leads)->where('status', 'new')->count(),
                'views'   => (int) DB::table('vehicles')->sum('views_count'),
                'sources' => (clone $leads)->where('created_at', '>=', now()->subDays(30))->groupBy('source')
                    ->select('source', DB::raw('count(*) as c'))->orderByDesc('c')->pluck('c', 'source'),
                'top'     => DB::table('vehicles')->join('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')->join('car_models', 'car_models.id', '=', 'vehicles.model_id')
                    ->where('vehicles.is_published', true)->whereNull('vehicles.deleted_at')->orderByDesc('vehicles.views_count')->limit(5)
                    ->get(['vehicles.id', 'vehicles.views_count', 'vehicles.manufacturing_year', \App\Support\Lang::sel('car_brands.name_ar as brand'), \App\Support\Lang::sel('car_models.name_ar as model')]),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (Site::FIELDS as [, $fields]) {
            foreach ($fields as $key => [, $type]) {
                $rules[$key] = match ($type) {
                    'image'  => ['nullable', 'image', 'max:4096'],
                    'number' => ['nullable', 'numeric', 'min:0'],
                    'color'  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                    'bool'   => ['nullable'],
                    default  => ['nullable', 'string', 'max:5000'],
                };
            }
        }
        $request->validate($rules, ['image' => 'لازم يكون صورة.', 'max' => 'الحجم كبير.', 'regex' => 'اللون مش صحيح.']);

        $current = Site::all();
        foreach (Site::FIELDS as $group => [, $fields]) {
            foreach ($fields as $key => [, $type]) {
                if ($type === 'image') {
                    if ($request->boolean('remove_'.$key)) {
                        Media::delete($current[$key] ?? null);
                        Site::set($key, null, $group);
                    } elseif ($request->hasFile($key)) {
                        Media::delete($current[$key] ?? null);
                        Site::set($key, Media::store($request->file($key), 'site'), $group);
                    }
                    continue;
                }
                $value = $type === 'bool' ? ($request->boolean($key) ? '1' : '0') : $request->input($key);
                Site::set($key, is_string($value) ? trim($value) : $value, $group);
            }
        }
        Site::forget();

        return redirect()->route('admin.site-settings.index', ['tab' => $request->input('_tab')])->with('status', 'تم حفظ إعدادات الموقع ✅');
    }
}
