<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Support\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * الرئيسية: ويدجتس بتتفلتر بصلاحيات الموظف، وكل واحد بيرتّبها على مزاجه (سحب وإفلات).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $layout = Dashboard::layout($user);

        return view('admin.dashboard', [
            'layout'    => $layout,
            'data'      => collect($layout)->mapWithKeys(fn ($w) => [$w['k'] => Dashboard::data($w['k'], $user)])->all(),
            'available' => Dashboard::allowed($user),
            'permsOk'   => Access::ready(),
        ]);
    }

    /** ويدجت واحد (لما الموظف يضيفه من غير ما الصفحة تعمل refresh) */
    public function widget(Request $request, string $key)
    {
        $allowed = Dashboard::allowed($request->user());
        abort_unless(isset($allowed[$key]), 404);

        return view('admin.dashboard.card', ['key' => $key, 'size' => (int) $request->query('s', Dashboard::WIDGETS[$key][3]),
            'w' => Dashboard::WIDGETS[$key], 'd' => Dashboard::data($key, $request->user())]);
    }

    /** لغة لوحة التحكم للموظف ده (بتتحفظ في حسابه) */
    public function locale(Request $request, string $locale): \Illuminate\Http\RedirectResponse
    {
        // الاختيار بيتحفظ في الجلسة دايماً، وفي حساب الموظف لو عمود locale موجود (بعد php artisan migrate)
        $request->session()->put('admin_locale', $locale);
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'locale')) {
            \Illuminate\Support\Facades\DB::table('users')->where('id', $request->user()->id)->update(['locale' => $locale]);
        }
        \App\Support\Alerts::forget($request->user());

        return back();
    }

    public function saveLayout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout'     => ['present', 'array', 'max:40'],
            'layout.*.k' => ['required', 'string', 'max:40'],
            'layout.*.s' => ['required', 'integer', 'min:1', 'max:4'],
            'reset'      => ['nullable', 'boolean'],
        ]);
        $allowed = Dashboard::allowed($request->user());
        $layout = $request->boolean('reset') ? null : array_values(array_filter($data['layout'], fn ($w) => isset($allowed[$w['k']])));

        DB::table('users')->where('id', $request->user()->id)->update(['dashboard_layout' => $layout === null ? null : json_encode($layout)]);

        return response()->json(['ok' => true]);
    }
}
