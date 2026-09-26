<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Company;
use App\Support\Media;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * إعدادات النظام: هوية الشركة + الشروط الافتراضية للعقود.
 * أي تعديل هنا بيظهر فوراً في كل الطباعة والإيصالات والعقود الجديدة.
 */
class SettingsController extends Controller
{
    public const IMAGES = ['logo' => 'الشعار', 'seal' => 'الختم', 'header_image' => 'هيدر الطباعة'];

    public const TERMS = [
        'dealer_contract'   => ['عقود تفويض البيع', DealerContractController::class, 'DEFAULT_TERMS', null],
        'sales_contract'    => ['عقود البيع', SalesContractController::class, 'DEFAULT_TERMS_AR', 'DEFAULT_TERMS_EN'],
        'test_drive'        => ['تجربة القيادة', TestDriveController::class, 'DEFAULT_TERMS_AR', 'DEFAULT_TERMS_EN'],
        'delivery_contract' => ['عقود التسليم', null, null, null],
    ];

    public function edit(): View
    {
        $defaults = [];
        foreach (self::TERMS as $key => [$label, $class, $ar, $en]) {
            $defaults[$key] = [
                'ar' => $class && $ar ? constant($class.'::'.$ar) : 'يقر المستلم بأنه استلم المركبة بحالة جيدة ومعها المفاتيح والمستندات الخاصة بها، ويتحمل مسؤوليتها من تاريخ ووقت التسليم.',
                'en' => $class && $en ? constant($class.'::'.$en) : 'The recipient acknowledges receiving the vehicle in good condition with its keys and documents, and bears full responsibility from the time of delivery.',
            ];
        }

        return view('admin.settings.edit', ['company' => DB::table('company_info')->first() ?? (object) [], 'defaults' => $defaults]);
    }

    public function update(Request $request): RedirectResponse
    {
        $text = ['nullable', 'string', 'max:60000'];
        $data = $request->validate([
            'name_ar'  => ['required', 'string', 'max:150'],
            'name_en'  => ['nullable', 'string', 'max:150'],
            'commercial_register_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'address_en' => ['nullable', 'string', 'max:500'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email'    => ['nullable', 'email', 'max:150'],
            'website'  => ['nullable', 'string', 'max:255'],
            'footer_text'   => ['nullable', 'string', 'max:255'],
            'receipt_notes' => ['nullable', 'string', 'max:1000'],
            'terms_and_conditions_ar' => $text, 'terms_and_conditions_en' => $text,
            'privacy_policy_ar' => $text, 'privacy_policy_en' => $text,
            'dealer_contract_terms_ar' => $text, 'dealer_contract_terms_en' => $text,
            'sales_contract_terms_ar' => $text, 'sales_contract_terms_en' => $text,
            'test_drive_terms_ar' => $text, 'test_drive_terms_en' => $text,
            'delivery_contract_terms_ar' => $text, 'delivery_contract_terms_en' => $text,
            'logo'         => ['nullable', 'image', 'max:2048'],
            'seal'         => ['nullable', 'image', 'max:2048'],
            'header_image' => ['nullable', 'image', 'max:4096'],
        ], [
            'required' => 'اسم الشركة مطلوب.', 'email' => 'الإيميل مش صحيح.',
            'image' => 'لازم يكون صورة (png, jpg, webp).', 'max' => 'الملف أو النص أكبر من المسموح.',
        ]);

        // النصوص المنسّقة: السياسات (كاملة) وشروط العقود (أدوات الطباعة بس)
        foreach (['terms_and_conditions', 'privacy_policy'] as $k) {
            foreach (['ar', 'en'] as $l) {
                $data[$k.'_'.$l] = RichText::clean($data[$k.'_'.$l] ?? null, 'full');
            }
        }
        foreach (array_keys(self::TERMS) as $k) {
            foreach (['ar', 'en'] as $l) {
                $data[$k.'_terms_'.$l] = RichText::clean($data[$k.'_terms_'.$l] ?? null, 'terms');
            }
        }

        $current = DB::table('company_info')->first();

        foreach (array_keys(self::IMAGES) as $img) {
            unset($data[$img]);
            if ($request->hasFile($img)) {
                Media::delete($current->{$img} ?? null);
                $data[$img] = Media::store($request->file($img), 'company');
            } elseif ($request->boolean('remove_'.$img)) {
                Media::delete($current->{$img} ?? null);
                $data[$img] = null;
            }
        }

        $data['updated_at'] = now();
        if ($current) {
            DB::table('company_info')->where('id', $current->id)->update($data);
        } else {
            DB::table('company_info')->insert($data + ['created_at' => now()]);
        }

        Company::forget();

        return back()->with('status', 'تم حفظ الإعدادات — هتظهر في كل الطباعة من دلوقتي');
    }
}
