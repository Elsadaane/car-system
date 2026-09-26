<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\ImportSource;
use App\Models\StorefrontSetting;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية للمتجر - ماركات وموديلات و10 عربيات معروضة.
 *
 * ⚠️ للتطوير والعرض بس. متشغلهاش على نسخة عميل حقيقية.
 *
 * التشغيل:  php artisan db:seed --class=DemoStorefrontSeeder
 */
class DemoStorefrontSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::main();
        if (! $branch) {
            $this->command->error('مفيش فروع. شغّل php artisan migrate الأول.');
            return;
        }

        // ---------- إعدادات الموقع ----------
        foreach ([
            ['phone',   '+968 2400 0000', 'contact'],
            ['whatsapp','+968 9100 0000', 'contact'],
            ['email',   'info@laroville.com', 'contact'],
            ['address', 'مسقط - الخوض، سلطنة عُمان', 'contact'],
        ] as [$k, $v, $g]) {
            StorefrontSetting::updateOrCreate(['key' => $k], ['value' => $v, 'group' => $g]);
        }

        // ---------- مصدر الاستيراد ----------
        $source = ImportSource::firstOrCreate(
            ['name_ar' => 'وكيل محلي'],
            ['name_en' => 'Local Dealer', 'is_active' => true]
        );

        // ---------- مورد ----------
        $supplier = Supplier::firstOrCreate(
            ['supplier_code' => 'SUP-DEMO-001'],
            [
                'name_ar'          => 'مؤسسة الخليج للسيارات',
                'name_en'          => 'Gulf Motors',
                'phone'            => '+968 2411 1111',
                'email'            => 'sales@gulfmotors.test',
                'settlement_days'  => 30,
                'status'           => 'active',
                'branch_id'        => $branch->id,
            ]
        );

        // ---------- الماركات والموديلات ----------
        $catalog = [
            'تويوتا'   => ['Toyota',     ['كامري' => 'Camry', 'كورولا' => 'Corolla', 'لاندكروزر' => 'Land Cruiser']],
            'نيسان'    => ['Nissan',     ['التيما' => 'Altima', 'باترول' => 'Patrol', 'صني' => 'Sunny']],
            'هيونداي'  => ['Hyundai',    ['النترا' => 'Elantra', 'توسان' => 'Tucson']],
            'كيا'      => ['Kia',        ['سيراتو' => 'Cerato', 'سبورتاج' => 'Sportage']],
            'لكزس'     => ['Lexus',      ['ES 350' => 'ES 350', 'LX 570' => 'LX 570']],
            'ميتسوبيشي'=> ['Mitsubishi', ['باجيرو' => 'Pajero', 'لانسر' => 'Lancer']],
        ];

        $models = [];
        foreach ($catalog as $brandAr => [$brandEn, $modelList]) {
            $brand = CarBrand::firstOrCreate(
                ['name_ar' => $brandAr],
                ['name_en' => $brandEn, 'is_active' => true]
            );

            foreach ($modelList as $modelAr => $modelEn) {
                $models[] = CarModel::firstOrCreate(
                    ['brand_id' => $brand->id, 'name_ar' => $modelAr],
                    ['name_en' => $modelEn, 'is_active' => true]
                );
            }
        }

        // ---------- العربيات ----------
        $colors  = ['أبيض', 'أسود', 'فضي', 'رمادي', 'أزرق', 'أحمر'];
        $created = 0;

        foreach ($models as $i => $model) {
            $year     = rand(2017, 2024);
            $cost     = rand(3200, 14000);
            $margin   = rand(12, 22) / 100;
            $selling  = round($cost * (1 + $margin), -1);
            $discount = $i % 4 === 0 ? round($selling * 0.92, -1) : null;

            $vehicle = Vehicle::firstOrCreate(
                ['vehicle_code' => 'VEH-DEMO-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'supplier_id'        => $supplier->id,
                    'branch_id'          => $branch->id,
                    'brand_id'           => $model->brand_id,
                    'model_id'           => $model->id,
                    'import_source_id'   => $source->id,
                    'plate_number'       => sprintf('%d %s', rand(1000, 9999), ['أ','ب','ج','د','هـ'][array_rand(['أ','ب','ج','د','هـ'])]),
                    'color'              => $colors[array_rand($colors)],
                    'manufacturing_year' => $year,
                    'mileage'            => rand(15, 180) * 1000,
                    'purchase_price'     => $cost,
                    'tax_percent'        => 0,
                    'tax_amount'         => 0,
                    'total_cost'         => $cost,

                    // بيانات المتجر
                    'selling_price'      => $selling,
                    'discount_price'     => $discount,
                    'is_published'       => true,
                    'is_featured'        => $i < 6,
                    'status'             => $i === 3 ? 'reserved' : 'available',

                    'description_ar'     => "سيارة {$model->name_ar} موديل {$year} بحالة ممتازة، صيانة دورية بالكامل وفحص شامل قبل العرض. متاحة للمعاينة والتجربة في المعرض.",
                    'specifications'     => [
                        'ناقل الحركة' => ['أوتوماتيك', 'عادي'][rand(0, 1)],
                        'عدد المقاعد' => [5, 5, 5, 7][rand(0, 3)],
                        'نوع الوقود'  => 'بنزين',
                        'سعة المحرك'  => [1.6, 2.0, 2.4, 3.5][rand(0, 3)] . ' لتر',
                        'نوع الدفع'   => ['دفع أمامي', 'دفع رباعي'][rand(0, 1)],
                        'الحالة'      => 'مستعملة',
                    ],
                ]
            );

            if ($vehicle->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->newLine();
        $this->command->info("تم إنشاء بيانات المتجر التجريبية:");
        $this->command->table(
            ['العنصر', 'العدد'],
            [
                ['ماركات', CarBrand::count()],
                ['موديلات', CarModel::count()],
                ['عربيات معروضة', Vehicle::where('is_published', true)->count()],
                ['منها مميزة', Vehicle::where('is_featured', true)->count()],
            ]
        );
        $this->command->newLine();
        $this->command->info('افتح:  http://laroville.test');
    }
}
