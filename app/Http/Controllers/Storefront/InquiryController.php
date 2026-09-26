<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Support\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * كل طلبات الموقع بتنزل في "طلبات الموقع" باللوحة ومعاها مصدر الإعلان (UTM / fbclid / gclid).
 */
class InquiryController extends Controller
{
    public const TYPES = [
        'inquiry'     => 'استفسار عام',
        'test_drive'  => 'تجربة قيادة',
        'reservation' => 'حجز',
        'finance'     => 'تقسيط / تمويل',
        'trade_in'    => 'تقييم / بدل عربية',
        'contact'     => 'تواصل معنا',
    ];

    private const MESSAGES = [
        'name.required'  => 'اكتب اسمك.',
        'phone.required' => 'اكتب رقم تليفونك.',
        'phone.regex'    => 'رقم التليفون مش صحيح.',
        'email.email'    => 'الإيميل مش صحيح.',
        'preferred_date.after_or_equal' => 'الميعاد لازم يكون النهارده أو بعد كده.',
    ];

    private static function messages(): array
    {
        return array_map(fn ($m) => __($m), self::MESSAGES);
    }

    /** طلب على عربية معيّنة */
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        if ($this->isBot($request)) {
            return back()->with('inquiry_success', __('تم استلام طلبك.'));
        }

        $data = $request->validate($this->rules(['inquiry', 'test_drive', 'reservation', 'finance']), self::messages());
        $this->save($request, $data, $vehicle->id, $vehicle->branch_id);

        return back()->with('inquiry_success', __('تمام يا :name! استلمنا طلبك وهنكلمك في أقرب وقت 🙌', ['name' => Str::before($data['name'], ' ')]))
            ->with('lead_event', ['type' => $data['type'], 'value' => (float) ($vehicle->discount_price ?: $vehicle->selling_price)]);
    }

    /** تواصل عام / قيّم عربيتك / طلب تمويل من غير عربية */
    public function lead(Request $request): RedirectResponse
    {
        if ($this->isBot($request)) {
            return back()->with('inquiry_success', __('تم استلام طلبك.'));
        }

        $data = $request->validate($this->rules(['contact', 'trade_in', 'finance', 'inquiry', 'test_drive']) + [
            'vehicle_id'  => ['nullable', 'integer', 'exists:vehicles,id'],
            'branch_id'   => ['nullable', 'integer', 'exists:branches,id'],
            'car_brand'   => ['nullable', 'string', 'max:60'],
            'car_model'   => ['nullable', 'string', 'max:60'],
            'car_year'    => ['nullable', 'integer', 'min:1970', 'max:'.(date('Y') + 1)],
            'car_mileage' => ['nullable', 'integer', 'min:0'],
            'car_price'   => ['nullable', 'numeric', 'min:0'],
            'car_notes'   => ['nullable', 'string', 'max:500'],
            'budget'      => ['nullable', 'numeric', 'min:0'],
            'down'        => ['nullable', 'numeric', 'min:0'],
            'months'      => ['nullable', 'integer', 'min:1', 'max:120'],
            'salary'      => ['nullable', 'numeric', 'min:0'],
        ], self::messages());

        // تفاصيل إضافية بتتجمع في الرسالة عشان الموظف يشوفها مرة واحدة
        $lines = array_filter([
            $data['type'] === 'trade_in'
                ? '🚗 عربية العميل: '.trim(($data['car_brand'] ?? '').' '.($data['car_model'] ?? '').' '.($data['car_year'] ?? ''))
                : (! empty($data['car_brand']) ? '🔎 العربية المطلوبة: '.$data['car_brand'] : null),
            ! empty($data['car_mileage']) ? 'الكيلومترات: '.number_format($data['car_mileage']) : null,
            ! empty($data['car_price']) ? 'السعر المتوقع: '.number_format($data['car_price']) : null,
            ! empty($data['car_notes']) ? 'حالتها: '.$data['car_notes'] : null,
            ! empty($data['budget']) ? '💰 الميزانية / سعر العربية: '.number_format($data['budget']) : null,
            ! empty($data['down']) ? 'المقدم: '.number_format($data['down']) : null,
            ! empty($data['months']) ? 'المدة: '.$data['months'].' شهر' : null,
            ! empty($data['salary']) ? 'الراتب: '.number_format($data['salary']) : null,
        ]);
        if ($lines) {
            $data['message'] = trim(implode("\n", $lines)."\n\n".($data['message'] ?? ''));
        }

        $vehicleBranch = ! empty($data['vehicle_id']) ? DB::table('vehicles')->where('id', $data['vehicle_id'])->value('branch_id') : null;
        $this->save($request, $data, $data['vehicle_id'] ?? null, $data['branch_id'] ?? $vehicleBranch);

        $msg = match ($data['type']) {
            'trade_in' => __('استلمنا بيانات عربيتك — هنراجعها ونكلمك بعرض سعر قريب جداً 🚗'),
            'finance'  => __('استلمنا طلب التقسيط — مستشار التمويل هيكلمك يوضحلك كل التفاصيل 💳'),
            default    => __('استلمنا رسالتك وهنرد عليك في أقرب وقت 🙌'),
        };

        return back()->with('inquiry_success', $msg)->with('lead_event', ['type' => $data['type'], 'value' => (float) ($data['budget'] ?? 0)]);
    }

    private function rules(array $types): array
    {
        return [
            'name'           => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'max:25', 'regex:/^[0-9+\s\-()]{7,25}$/'],
            'email'          => ['nullable', 'email', 'max:150'],
            'type'           => ['required', 'in:'.implode(',', $types)],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'message'        => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** فخ البوتات: حقل مخفي لو اتملى + فورم اتبعت في أقل من ثانيتين */
    private function isBot(Request $request): bool
    {
        $started = (int) $request->input('_ts', 0);

        return filled($request->input('website')) || ($started > 0 && time() - $started < 2);
    }

    private function save(Request $request, array $data, ?int $vehicleId, ?int $branchId): void
    {
        $utm = (array) $request->session()->get('utm', []);
        $branchId ??= (int) DB::table('branches')->orderBy('id')->value('id') ?: 1;

        do {
            $number = 'INQ-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (DB::table('vehicle_inquiries')->where('inquiry_number', $number)->exists());

        DB::table('vehicle_inquiries')->insert([
            'inquiry_number' => $number,
            'vehicle_id'     => $vehicleId,
            'branch_id'      => $branchId,
            'name'           => strip_tags($data['name']),
            'phone'          => $data['phone'],
            'email'          => $data['email'] ?? null,
            'message'        => isset($data['message']) ? strip_tags($data['message']) : null,
            'type'           => $data['type'],
            'preferred_date' => $data['preferred_date'] ?? null,
            'status'         => 'new',
            'source'         => $utm['utm_source'] ?? (isset($utm['fbclid']) ? 'facebook' : (isset($utm['gclid']) ? 'google' : 'website')),
            'utm_source'     => isset($utm['utm_source']) ? Str::limit($utm['utm_source'], 250, '') : null,
            'utm_medium'     => isset($utm['utm_medium']) ? Str::limit($utm['utm_medium'], 250, '') : null,
            'utm_campaign'   => isset($utm['utm_campaign']) ? Str::limit($utm['utm_campaign'], 250, '') : null,
            'fbclid'         => isset($utm['fbclid']) ? Str::limit($utm['fbclid'], 500, '') : null,
            'gclid'          => isset($utm['gclid']) ? Str::limit($utm['gclid'], 500, '') : null,
            'ip_address'     => $request->ip(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
