<?php

namespace App\Support;

use App\Models\SalesContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * استيراد بيانات المعرض من Excel:
 *  - customers:    العملاء
 *  - vehicles:     العربيات (رصيد أول المدة — من غير حركة في الخزنة)
 *  - installments: عقود تقسيط قديمة لسه شغالة (عميل + عربية + عقد + جدول أقساط + اللي اندفع)
 *
 * كل نوع: الأعمدة [key => [الاسم, مطلوب؟, أسماء بديلة يتعرّف عليها أوتوماتيك, مثال]]
 */
class Importer
{
    public const TYPES = [
        'customers' => [
            'label' => 'العملاء', 'icon' => '👥', 'module' => 'customers',
            'hint'  => 'كل صف = عميل. لو العميل موجود (نفس الرقم المدني أو الموبايل) تقدر تتخطاه أو تحدّث بياناته.',
            'fields' => [
                'name_ar'     => ['الاسم', true, ['اسم العميل', 'العميل', 'الاسم بالعربي', 'name', 'customer', 'customer name', 'full name'], 'خالد سالم البلوشي'],
                'phone'       => ['الموبايل', true, ['التليفون', 'الهاتف', 'رقم الهاتف', 'رقم الموبايل', 'جوال', 'رقم الجوال', 'phone', 'mobile', 'tel'], '96891234567'],
                'national_id' => ['الرقم المدني', false, ['رقم البطاقة', 'الهوية', 'رقم الهوية', 'البطاقة', 'national id', 'civil id', 'id number'], '12345678'],
                'name_en'     => ['الاسم بالإنجليزي', false, ['الاسم انجليزي', 'english name', 'name en'], 'Khalid Salem'],
                'email'       => ['الإيميل', false, ['البريد', 'البريد الإلكتروني', 'الايميل', 'email', 'e-mail'], 'khalid@mail.com'],
                'address'     => ['العنوان', false, ['address', 'المنطقة', 'السكن'], 'مسقط - الخوض'],
                'job_title'   => ['الوظيفة', false, ['المهنة', 'job', 'job title', 'occupation'], 'مهندس'],
                'nationality' => ['الجنسية', false, ['nationality'], 'عماني'],
                'notes'       => ['ملاحظات', false, ['ملاحظة', 'notes', 'note'], ''],
            ],
        ],
        'vehicles' => [
            'label' => 'العربيات (المخزون)', 'icon' => '🚗', 'module' => 'vehicles',
            'hint'  => 'كل صف = عربية. الماركة والموديل لو مش موجودين بيتعملوا لوحدهم. الاستيراد ده رصيد أول مدة — مش بيطلّع فلوس من الخزنة.',
            'fields' => [
                'brand'          => ['الماركة', true, ['الشركة', 'الشركة المصنعة', 'النوع', 'brand', 'make'], 'تويوتا'],
                'model'          => ['الموديل', true, ['الطراز', 'الفئة', 'model'], 'كامري'],
                'year'           => ['سنة الصنع', true, ['السنة', 'الموديل سنة', 'سنة', 'year', 'model year'], '2023'],
                'color'          => ['اللون', true, ['color', 'colour'], 'أبيض'],
                'purchase_price' => ['سعر الشراء', true, ['التكلفة', 'تكلفة الشراء', 'سعر التكلفة', 'cost', 'purchase price'], '8500'],
                'selling_price'  => ['سعر البيع', false, ['السعر', 'سعر العرض', 'price', 'selling price'], '9800'],
                'chassis_number' => ['رقم الشاسيه', false, ['الشاسيه', 'الشاصي', 'رقم الشاصي', 'vin', 'chassis', 'chassis number'], 'JTNB11HK5J3000001'],
                'plate_number'   => ['رقم اللوحة', false, ['اللوحة', 'plate', 'plate number'], ''],
                'engine_number'  => ['رقم الموتور', false, ['رقم المحرك', 'engine', 'engine number'], ''],
                'mileage'        => ['الكيلومترات', false, ['العداد', 'الممشى', 'km', 'mileage', 'odometer'], '15000'],
                'branch'         => ['الفرع', false, ['branch', 'المعرض'], ''],
                'status'         => ['الحالة', false, ['status'], 'متاح'],
                'notes'          => ['ملاحظات', false, ['ملاحظة', 'notes'], ''],
            ],
        ],
        'installments' => [
            'label' => 'عقود تقسيط قديمة', 'icon' => '📅', 'module' => 'sales',
            'hint'  => 'كل صف = عقد تقسيط لسه شغال. السيستم بيعمل العميل (لو مش موجود) والعربية (مباعة) والعقد وجدول الأقساط، وبيعلّم الأقساط اللي اندفعت على أساس المبلغ المدفوع. الفلوس القديمة مش بتدخل الخزنة.',
            'fields' => [
                'customer_name'      => ['اسم العميل', true, ['العميل', 'الاسم', 'name', 'customer'], 'خالد سالم'],
                'customer_phone'     => ['موبايل العميل', true, ['الموبايل', 'التليفون', 'الهاتف', 'جوال', 'phone', 'mobile'], '96891234567'],
                'national_id'        => ['الرقم المدني', false, ['رقم البطاقة', 'الهوية', 'civil id', 'national id'], ''],
                'brand'              => ['الماركة', true, ['الشركة', 'brand', 'make'], 'نيسان'],
                'model'              => ['الموديل', true, ['الطراز', 'model'], 'صني'],
                'year'               => ['سنة الصنع', true, ['السنة', 'year'], '2022'],
                'color'              => ['اللون', false, ['color'], 'فضي'],
                'chassis_number'     => ['رقم الشاسيه', false, ['الشاسيه', 'الشاصي', 'vin', 'chassis'], ''],
                'contract_date'      => ['تاريخ البيع', true, ['تاريخ العقد', 'التاريخ', 'date', 'contract date'], '2025-03-01'],
                'total_amount'       => ['إجمالي العقد', true, ['قيمة العقد', 'سعر البيع', 'الإجمالي', 'total', 'price'], '6000'],
                'down_payment'       => ['المقدم', false, ['الدفعة المقدمة', 'down payment'], '1200'],
                'installments_count' => ['عدد الأقساط', true, ['الأقساط', 'عدد الشهور', 'المدة', 'months', 'installments'], '24'],
                'first_due_date'     => ['تاريخ أول قسط', true, ['أول قسط', 'first due', 'first installment'], '2025-04-01'],
                'interval'           => ['كل كام شهر', false, ['الفترة', 'interval'], '1'],
                'paid_amount'        => ['إجمالي المدفوع لحد النهارده', false, ['المدفوع', 'المسدد', 'paid', 'total paid'], '3000'],
                'purchase_price'     => ['تكلفة العربية', false, ['سعر الشراء', 'التكلفة', 'cost'], ''],
                'contract_number'    => ['رقم العقد', false, ['العقد', 'contract', 'contract number'], ''],
                'branch'             => ['الفرع', false, ['branch'], ''],
            ],
        ],
    ];

    public const VEHICLE_STATUSES = [
        'available' => ['متاح', 'متاحة', 'موجود', 'موجودة', 'للبيع', 'available', 'in stock'],
        'reserved'  => ['محجوز', 'محجوزة', 'reserved'],
        'sold'      => ['مباع', 'مباعة', 'اتباعت', 'sold'],
        'damaged'   => ['تالف', 'تالفة', 'damaged'],
        'maintenance' => ['صيانة', 'في الصيانة', 'maintenance'],
    ];

    public const OPENING_METHOD = 'رصيد افتتاحي (قبل السيستم)';

    private array $cache = ['brand' => [], 'model' => [], 'branch' => null];
    public array $created = ['brands' => 0, 'models' => 0, 'customers' => 0];

    public function __construct(private string $type, private $user) {}

    public static function fields(string $type): array
    {
        return self::TYPES[$type]['fields'];
    }

    /** أوتوماتيك: عمود الملف ده يقابل أنهي حقل؟ */
    public static function guessMapping(string $type, array $headers): array
    {
        $norm = fn ($s) => Str::of((string) $s)->lower()->replaceMatches('/[\s_\-\.:()*]+/u', ' ')->replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'])->trim()->value();
        $map = [];
        foreach (self::fields($type) as $key => [$label, , $aliases]) {
            $names = array_map($norm, array_merge([$label, $key], $aliases));
            foreach ($headers as $i => $h) {
                if (! in_array($i, $map, true) && in_array($norm($h), $names, true)) {
                    $map[$key] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    // =========================================================
    //  تنضيف القيم
    // =========================================================

    public static function digits(string $v): string
    {
        return strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => ',', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
    }

    public static function number(?string $v): ?float
    {
        $v = str_replace([',', ' ', "\u{00A0}"], '', self::digits(trim((string) $v)));
        $v = preg_replace('/[^\d.\-]/u', '', $v);

        return $v === '' || ! is_numeric($v) ? null : (float) $v;
    }

    public static function date(?string $v): ?string
    {
        $v = trim(self::digits((string) $v));
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) { // رقم تاريخ Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        foreach (['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'Y-n-j', 'd/m/y', 'm/Y', 'Y-m'] as $f) {
            try {
                $d = Carbon::createFromFormat('!'.$f, $v);
                if ($d && ($d->format($f) === $v || in_array($f, ['j/n/Y', 'Y-n-j'], true)) && $d->year >= 1950 && $d->year <= 2100) {
                    return $d->toDateString();
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    private static function phoneTail(?string $p): string
    {
        return substr(preg_replace('/\D/', '', self::digits((string) $p)), -8);
    }

    // =========================================================
    //  التحقق + الإدخال
    // =========================================================

    /** بيرجّع [data, errors[]] للصف */
    public function check(array $row, array $map): array
    {
        $get = fn ($k) => isset($map[$k]) && $map[$k] !== '' ? trim((string) ($row[(int) $map[$k]] ?? '')) : '';
        $errors = [];
        $d = [];
        foreach (self::fields($this->type) as $key => [$label, $required]) {
            $d[$key] = $get($key);
            if ($required && $d[$key] === '') {
                $errors[] = "«{$label}» فاضي";
            }
        }
        $num = function (string $key, bool $positive = false) use (&$d, &$errors) {
            if ($d[$key] === '') {
                $d[$key] = null;

                return;
            }
            $n = self::number($d[$key]);
            if ($n === null || ($positive && $n <= 0) || $n < 0) {
                $errors[] = '«'.self::fields($this->type)[$key][0].'» مش رقم صحيح: '.$d[$key];
            }
            $d[$key] = $n;
        };
        $date = function (string $key) use (&$d, &$errors) {
            if ($d[$key] === '') {
                $d[$key] = null;

                return;
            }
            $v = self::date($d[$key]);
            if (! $v) {
                $errors[] = '«'.self::fields($this->type)[$key][0].'» مش تاريخ مفهوم: '.$d[$key].' (اكتبه كده 2025-03-01)';
            }
            $d[$key] = $v;
        };

        if ($this->type === 'customers') {
            if ($d['email'] !== '' && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'الإيميل مش صحيح';
            }
            if ($d['phone'] !== '' && strlen(preg_replace('/\D/', '', self::digits($d['phone']))) < 7) {
                $errors[] = 'رقم الموبايل قصير';
            }
        }

        if ($this->type === 'vehicles') {
            $num('purchase_price', true);
            $num('selling_price');
            $num('mileage');
            $y = (int) self::number($d['year']);
            if ($d['year'] !== '' && ($y < 1950 || $y > (int) date('Y') + 2)) {
                $errors[] = 'سنة الصنع مش منطقية: '.$d['year'];
            }
            $d['year'] = $y;
            if ($d['status'] !== '') {
                $st = $this->vehicleStatus($d['status']);
                if (! $st) {
                    $errors[] = 'الحالة مش معروفة: '.$d['status'].' (متاح / محجوز / مباع / صيانة / تالف)';
                }
                $d['status'] = $st;
            } else {
                $d['status'] = 'available';
            }
        }

        if ($this->type === 'installments') {
            $y = (int) self::number($d['year']);
            if ($d['year'] !== '' && ($y < 1950 || $y > (int) date('Y') + 2)) {
                $errors[] = 'سنة الصنع مش منطقية: '.$d['year'];
            }
            $d['year'] = $y;
            foreach (['total_amount' => true, 'down_payment' => false, 'paid_amount' => false, 'purchase_price' => false, 'interval' => false] as $k => $pos) {
                $num($k, $pos);
            }
            $cnt = self::number((string) $d['installments_count']);
            if ($d['installments_count'] !== '' && ($cnt === null || $cnt < 1 || $cnt > 240 || floor($cnt) != $cnt)) {
                $errors[] = 'عدد الأقساط مش صحيح: '.$d['installments_count'];
            }
            $d['installments_count'] = (int) $cnt;
            $date('contract_date');
            $date('first_due_date');
            $d['interval'] = max(1, (int) ($d['interval'] ?? 1));
            $d['down_payment'] = (float) ($d['down_payment'] ?? 0);
            $d['paid_amount'] = $d['paid_amount'] ?? $d['down_payment'];
            if (! $errors) {
                if ($d['down_payment'] >= $d['total_amount']) {
                    $errors[] = 'المقدم أكبر من أو يساوي إجمالي العقد';
                }
                if ($d['paid_amount'] > $d['total_amount']) {
                    $errors[] = 'المدفوع أكبر من إجمالي العقد';
                }
                if ($d['paid_amount'] < $d['down_payment']) {
                    $d['paid_amount'] = $d['down_payment']; // المدفوع أقل حاجة = المقدم
                }
                if ($d['contract_date'] && $d['contract_date'] > today()->toDateString()) {
                    $errors[] = 'تاريخ البيع في المستقبل';
                }
                if ($d['contract_number'] !== '' && DB::table('sales_contracts')->where('contract_number', $d['contract_number'])->exists()) {
                    $errors[] = 'رقم العقد ده موجود قبل كده: '.$d['contract_number'];
                }
            }
        }

        return [$d, $errors];
    }

    /** موجود قبل كده؟ بيرجّع id أو null */
    public function existing(array $d): ?int
    {
        return match ($this->type) {
            'customers' => $this->findCustomer($d['national_id'], $d['phone']),
            'vehicles'  => $this->findVehicle($d),
            'installments' => $d['chassis_number'] !== '' ? DB::table('vehicles')->whereNull('deleted_at')->where('chassis_number', $d['chassis_number'])
                ->whereExists(fn ($q) => $q->from('sales_contracts')->whereColumn('sales_contracts.vehicle_id', 'vehicles.id')->whereNull('sales_contracts.deleted_at'))->value('id') : null,
            default => null,
        };
    }

    /** بيدخّل الصف — بيرجّع 'created' أو 'updated' أو 'skipped' */
    public function import(array $d, bool $update): string
    {
        return DB::transaction(fn () => match ($this->type) {
            'customers'    => $this->importCustomer($d, $update),
            'vehicles'     => $this->importVehicle($d, $update),
            'installments' => $this->importContract($d),
        });
    }

    // ---------------------------------------------------------

    private function importCustomer(array $d, bool $update): string
    {
        $id = $this->findCustomer($d['national_id'], $d['phone']);
        $data = array_filter([
            'name_ar' => $d['name_ar'], 'name_en' => $d['name_en'], 'phone' => self::digits($d['phone']), 'national_id' => self::digits($d['national_id']),
            'email' => $d['email'], 'address' => $d['address'], 'job_title' => $d['job_title'], 'nationality' => $d['nationality'], 'notes' => $d['notes'],
        ], fn ($v) => $v !== '' && $v !== null);

        if ($id) {
            if (! $update) {
                return 'skipped';
            }
            unset($data['national_id']); // الرقم المدني مش بيتغيّر من الاستيراد
            DB::table('customers')->where('id', $id)->update($data + ['updated_at' => now()]);

            return 'updated';
        }
        if (! empty($data['national_id']) && DB::table('customers')->where('national_id', $data['national_id'])->exists()) {
            return 'skipped';
        }
        DB::table('customers')->insert($data + ['customer_code' => $this->customerCode(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return 'created';
    }

    private function importVehicle(array $d, bool $update): string
    {
        $id = $this->findVehicle($d);
        $brand = $this->brandId($d['brand']);
        $data = [
            'brand_id' => $brand, 'model_id' => $this->modelId($brand, $d['model']), 'manufacturing_year' => $d['year'], 'color' => $d['color'],
            'purchase_price' => Money::round($d['purchase_price']), 'total_cost' => Money::round($d['purchase_price']),
            'selling_price' => $d['selling_price'] !== null ? Money::round($d['selling_price']) : null,
            'mileage' => (int) ($d['mileage'] ?? 0), 'status' => $d['status'], 'branch_id' => $this->branchId($d['branch']),
        ];
        foreach (['chassis_number', 'plate_number', 'engine_number', 'notes'] as $k) {
            if ($d[$k] !== '') {
                $data[$k] = $d[$k];
            }
        }

        if ($id) {
            if (! $update) {
                return 'skipped';
            }
            DB::table('vehicles')->where('id', $id)->update($data + ['updated_at' => now()]);

            return 'updated';
        }
        $this->insertVehicle($data);

        return 'created';
    }

    private function importContract(array $d): string
    {
        if ($this->existing($d)) {
            return 'skipped';
        }
        $now = now();

        // 1) العميل
        $customerId = $this->findCustomer($d['national_id'], $d['customer_phone']);
        if (! $customerId) {
            $customerId = DB::table('customers')->insertGetId(array_filter([
                'customer_code' => $this->customerCode(), 'name_ar' => $d['customer_name'], 'phone' => self::digits($d['customer_phone']),
                'national_id' => $d['national_id'] !== '' ? self::digits($d['national_id']) : null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ], fn ($v) => $v !== null));
            $this->created['customers']++;
        }

        // 2) العربية (مباعة)
        $branch = $this->branchId($d['branch']);
        $brand = $this->brandId($d['brand']);
        $cost = Money::round($d['purchase_price'] ?? 0);
        $inStock = $d['chassis_number'] !== '' ? DB::table('vehicles')->whereNull('deleted_at')->where('chassis_number', $d['chassis_number'])->value('id') : null;
        if ($inStock) { // العربية متسجلة في المخزون بالفعل — نعلّمها مباعة بدل ما نكررها
            DB::table('vehicles')->where('id', $inStock)->update(['status' => 'sold', 'is_published' => false, 'updated_at' => $now]);
        }
        $vehicleId = $inStock ?: $this->insertVehicle(array_filter([
            'brand_id' => $brand, 'model_id' => $this->modelId($brand, $d['model']), 'manufacturing_year' => $d['year'], 'color' => $d['color'] ?: '—',
            'chassis_number' => $d['chassis_number'] ?: null, 'purchase_price' => $cost, 'total_cost' => $cost, 'status' => 'sold', 'branch_id' => $branch,
            'notes' => 'اتسجلت من استيراد عقود التقسيط القديمة',
        ], fn ($v) => $v !== null));

        // 3) العقد
        $contract = new SalesContract;
        $contract->forceFill([
            'contract_number' => $d['contract_number'] ?: $this->contractNumber(),
            'customer_id' => $customerId, 'vehicle_id' => $vehicleId, 'branch_id' => $branch,
            'contract_date' => $d['contract_date'], 'sale_price' => Money::round($d['total_amount']), 'discount_amount' => 0, 'tax_percent' => 0, 'tax_amount' => 0,
            'total_amount' => Money::round($d['total_amount']), 'payment_type' => 'installments', 'down_payment' => Money::round($d['down_payment']),
            'installments_count' => $d['installments_count'], 'installment_interval' => $d['interval'], 'first_due_date' => $d['first_due_date'],
            'paid_amount' => 0, 'remaining_amount' => Money::round($d['total_amount']), 'delivery_date' => $d['contract_date'],
            'notes' => 'عقد قديم اتسجل بالاستيراد — المبالغ المدفوعة قبل السيستم متسجلة كرصيد افتتاحي (مش في الخزنة)', 'user_id' => $this->user->id,
        ])->save();
        InstallmentPlan::generate($contract);

        // 4) المدفوع قبل السيستم: المقدم + قسط قسط بتاريخ استحقاقه (عشان تقييم العميل يفضل صح)
        $method = $this->openingMethod();
        $pay = function (float $amount, string $date, string $note) use ($contract, $method, $now) {
            return DB::table('payments')->insertGetId([
                'payment_reference' => 'OPEN-'.Str::upper(Str::random(8)), 'payable_type' => $contract->getMorphClass(), 'payable_id' => $contract->id,
                'payment_method_id' => $method, 'amount' => Money::round($amount), 'payment_date' => min($date, today()->toDateString()),
                'notes' => $note, 'status' => 'completed', 'user_id' => $this->user->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        };
        if ($d['down_payment'] > 0) {
            $contract->forceFill(['down_payment_id' => $pay($d['down_payment'], $d['contract_date'], 'مقدم العقد (رصيد افتتاحي)')])->save();
        }
        $left = InstallmentPlan::units($d['paid_amount'] - $d['down_payment']);
        foreach (DB::table('installments')->where('sales_contract_id', $contract->id)->orderBy('installment_sequence')->get() as $ins) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, InstallmentPlan::units($ins->amount));
            $pay(InstallmentPlan::money($take), $ins->due_date, 'قسط '.$ins->installment_sequence.' (اندفع قبل السيستم)');
            $left -= $take;
        }

        InstallmentPlan::recalculate($contract->fresh());
        InstallmentPlan::refreshCustomer($customerId);

        return 'created';
    }

    // =========================================================

    private function findCustomer(?string $nationalId, ?string $phone): ?int
    {
        $nid = self::digits(trim((string) $nationalId));
        if ($nid !== '' && ($id = DB::table('customers')->whereNull('deleted_at')->where('national_id', $nid)->value('id'))) {
            return $id;
        }
        $tail = self::phoneTail($phone);
        if (strlen($tail) >= 7) {
            return DB::table('customers')->whereNull('deleted_at')
                ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ['%'.$tail])->value('id');
        }

        return null;
    }

    private function findVehicle(array $d): ?int
    {
        foreach (['chassis_number', 'plate_number', 'engine_number'] as $k) {
            if (($d[$k] ?? '') !== '' && ($id = DB::table('vehicles')->whereNull('deleted_at')->where($k, $d[$k])->value('id'))) {
                return $id;
            }
        }

        return null;
    }

    private function vehicleStatus(string $v): ?string
    {
        $v = mb_strtolower(trim($v));
        foreach (self::VEHICLE_STATUSES as $key => $names) {
            if ($v === $key || in_array($v, $names, true)) {
                return $key;
            }
        }

        return null;
    }

    private function brandId(string $name): int
    {
        $k = mb_strtolower(trim($name));
        if (isset($this->cache['brand'][$k])) {
            return $this->cache['brand'][$k];
        }
        $id = DB::table('car_brands')->where(fn ($q) => $q->whereRaw('LOWER(name_ar) = ?', [$k])->orWhereRaw('LOWER(name_en) = ?', [$k]))->value('id');
        if (! $id) {
            $isLatin = ! preg_match('/\p{Arabic}/u', $name);
            $id = DB::table('car_brands')->insertGetId(['name_ar' => trim($name), 'name_en' => $isLatin ? trim($name) : null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $this->created['brands']++;
        }

        return $this->cache['brand'][$k] = (int) $id;
    }

    private function modelId(int $brandId, string $name): int
    {
        $k = $brandId.'|'.mb_strtolower(trim($name));
        if (isset($this->cache['model'][$k])) {
            return $this->cache['model'][$k];
        }
        $n = mb_strtolower(trim($name));
        $id = DB::table('car_models')->where('brand_id', $brandId)->where(fn ($q) => $q->whereRaw('LOWER(name_ar) = ?', [$n])->orWhereRaw('LOWER(name_en) = ?', [$n]))->value('id');
        if (! $id) {
            $isLatin = ! preg_match('/\p{Arabic}/u', $name);
            $id = DB::table('car_models')->insertGetId(['brand_id' => $brandId, 'name_ar' => trim($name), 'name_en' => $isLatin ? trim($name) : null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('car_brands')->where('id', $brandId)->increment('total_models');
            $this->created['models']++;
        }

        return $this->cache['model'][$k] = (int) $id;
    }

    private function branchId(?string $name): int
    {
        $own = Access::branchIds($this->user);
        if ($own !== null) {
            return (int) ($own[0] ?: $this->defaultBranch()); // الموظف المقفول على فرعه بيدخّل لفرعه بس
        }
        $name = trim((string) $name);
        if ($name !== '') {
            $id = DB::table('branches')->whereNull('deleted_at')->where(fn ($q) => $q->where('name_ar', $name)->orWhere('name_en', $name)->orWhere('code', $name))->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return $this->defaultBranch();
    }

    private function defaultBranch(): int
    {
        return $this->cache['branch'] ??= (int) ($this->user->branch_id ?: DB::table('branches')->whereNull('deleted_at')->orderBy('id')->value('id') ?: 1);
    }

    private function insertVehicle(array $data): int
    {
        $now = now();
        $id = DB::table('vehicles')->insertGetId($data + ['vehicle_code' => 'TMP-'.Str::random(12), 'is_published' => false, 'user_id' => $this->user->id, 'created_at' => $now, 'updated_at' => $now]);
        $brand = DB::table('car_brands')->where('id', $data['brand_id'])->first();
        $model = DB::table('car_models')->where('id', $data['model_id'])->first();
        DB::table('vehicles')->where('id', $id)->update([
            'vehicle_code' => 'VH-'.str_pad($id, 5, '0', STR_PAD_LEFT),
            'slug' => (Str::slug(trim(($brand?->name_en ?: $brand?->name_ar).' '.($model?->name_en ?: $model?->name_ar).' '.$data['manufacturing_year'])) ?: 'car').'-'.Str::lower(Str::random(4)),
        ]);

        return $id;
    }

    private function customerCode(): string
    {
        $n = (int) DB::table('customers')->max('id') + 1;
        do {
            $code = 'CUST-'.str_pad($n++, 4, '0', STR_PAD_LEFT);
        } while (DB::table('customers')->where('customer_code', $code)->exists());

        return $code;
    }

    private function contractNumber(): string
    {
        $n = (int) DB::table('sales_contracts')->where('contract_number', 'like', 'OLD-%')->count() + 1;
        do {
            $code = 'OLD-'.str_pad($n++, 5, '0', STR_PAD_LEFT);
        } while (DB::table('sales_contracts')->where('contract_number', $code)->exists());

        return $code;
    }

    /** طريقة دفع مخفية للمبالغ اللي اندفعت قبل السيستم — مش مربوطة بخزنة فمش بتأثر على رصيدها */
    private function openingMethod(): int
    {
        return $this->cache['method'] ??= (int) (DB::table('payment_methods')->where('name_ar', self::OPENING_METHOD)->value('id')
            ?: DB::table('payment_methods')->insertGetId(['name_ar' => self::OPENING_METHOD, 'name_en' => 'Opening balance', 'is_active' => false, 'financial_account_id' => null, 'sort_order' => 999, 'created_at' => now(), 'updated_at' => now()]));
    }
}
