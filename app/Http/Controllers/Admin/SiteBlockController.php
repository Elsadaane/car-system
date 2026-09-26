<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * محتوى الموقع: البانرات، آراء العملاء، الأسئلة الشائعة، مميزات "ليه إحنا".
 */
class SiteBlockController extends Controller
{
    public const TYPES = [
        'banner'      => ['🖼️', 'البانرات', 'سلايدر فوق الرئيسية — ينفع تحدد له تاريخ بداية ونهاية'],
        'testimonial' => ['⭐', 'آراء العملاء', 'آراء حقيقية بتزوّد الثقة وتحسّن الـ Conversion'],
        'faq'         => ['❓', 'الأسئلة الشائعة', 'بتظهر في الرئيسية وصفحة التقسيط وقيّم عربيتك'],
        'feature'     => ['🛡️', 'ليه إحنا', 'مميزات المعرض — 4 مميزات بيبقوا أحسن شكل'],
    ];

    public function index(string $type = 'banner'): View
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return view('admin.site.blocks', [
            'type'   => $type,
            'blocks' => DB::table('site_blocks')->where('type', $type)->orderBy('sort')->orderBy('id')->get(),
            'counts' => DB::table('site_blocks')->groupBy('type')->select('type', DB::raw('count(*) as c'))->pluck('c', 'type'),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $data = $this->data($request, $type);
        if ($request->hasFile('image')) {
            $data['image'] = Media::store($request->file('image'), 'site');
        }
        $data['sort'] ??= (int) DB::table('site_blocks')->where('type', $type)->max('sort') + 1;
        DB::table('site_blocks')->insert($data + ['type' => $type, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تمت الإضافة');
    }

    public function update(Request $request, string $type, int $block): RedirectResponse
    {
        $row = DB::table('site_blocks')->where('id', $block)->where('type', $type)->first();
        abort_unless($row, 404);
        $data = $this->data($request, $type);
        if ($request->hasFile('image')) {
            Media::delete($row->image);
            $data['image'] = Media::store($request->file('image'), 'site');
        } elseif ($request->boolean('remove_image')) {
            Media::delete($row->image);
            $data['image'] = null;
        }
        $data['sort'] ??= $row->sort;
        DB::table('site_blocks')->where('id', $block)->update($data + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ التعديلات');
    }

    public function toggle(string $type, int $block): RedirectResponse
    {
        DB::table('site_blocks')->where('id', $block)->where('type', $type)->update(['is_active' => DB::raw('1 - is_active'), 'updated_at' => now()]);

        return back();
    }

    /** ترتيب بالسحب: ids[]=3&ids[]=1... */
    public function reorder(Request $request, string $type)
    {
        foreach ((array) $request->input('ids', []) as $i => $id) {
            DB::table('site_blocks')->where('id', (int) $id)->where('type', $type)->update(['sort' => $i]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(string $type, int $block): RedirectResponse
    {
        $row = DB::table('site_blocks')->where('id', $block)->where('type', $type)->first();
        if ($row) {
            Media::delete($row->image);
            DB::table('site_blocks')->where('id', $block)->delete();
        }

        return back()->with('status', 'تم المسح');
    }

    private function data(Request $request, string $type): array
    {
        $data = $request->validate([
            'title'       => [$type === 'banner' ? 'nullable' : 'required', 'string', 'max:190'],
            'body'        => [in_array($type, ['faq', 'testimonial'], true) ? 'required' : 'nullable', 'string', 'max:3000'],
            'image'       => [$type === 'banner' && $request->isMethod('post') ? 'required' : 'nullable', 'image', 'max:4096'],
            'link'        => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:60'],
            'title_en'    => ['nullable', 'string', 'max:190'],
            'body_en'     => ['nullable', 'string', 'max:3000'],
            'button_text_en' => ['nullable', 'string', 'max:60'],
            'icon'        => ['nullable', 'string', 'max:20'],
            'rating'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'sort'        => ['nullable', 'integer', 'min:0'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], [
            'title.required' => $type === 'testimonial' ? 'اكتب اسم العميل.' : 'العنوان مطلوب.',
            'body.required'  => $type === 'faq' ? 'اكتب الإجابة.' : 'اكتب الرأي.',
            'image.required' => 'البانر محتاج صورة.',
            'image.image'    => 'لازم يكون صورة.',
            'ends_at.after_or_equal' => 'تاريخ النهاية قبل البداية.',
        ]);
        unset($data['image']);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
