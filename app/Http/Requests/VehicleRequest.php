<?php

namespace App\Http\Requests;

use App\Http\Controllers\Admin\VehicleController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageVehicles();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_published'     => $this->boolean('is_published'),
            'is_featured'      => $this->boolean('is_featured'),
            'price_on_request' => $this->boolean('price_on_request'),
        ]);
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');
        $id = $vehicle?->id;
        $year = (int) date('Y') + 1;

        return [
            'vehicle_code'        => ['nullable', 'string', 'max:30', Rule::unique('vehicles', 'vehicle_code')->ignore($id)],
            'brand_id'            => ['required', Rule::exists('car_brands', 'id')],
            'model_id'            => ['required', Rule::exists('car_models', 'id')->where('brand_id', $this->input('brand_id'))],
            'supplier_id'         => ['nullable', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'import_source_id'    => ['nullable', Rule::exists('import_sources', 'id')],
            'branch_id'           => ['required', Rule::exists('branches', 'id')->whereNull('deleted_at')],

            'plate_number'        => ['nullable', 'string', 'max:30', Rule::unique('vehicles', 'plate_number')->ignore($id)],
            'chassis_number'      => ['nullable', 'string', 'max:50', Rule::unique('vehicles', 'chassis_number')->ignore($id)],
            'engine_number'       => ['nullable', 'string', 'max:50', Rule::unique('vehicles', 'engine_number')->ignore($id)],
            'color'               => ['required', 'string', 'max:50'],
            'manufacturing_year'  => ['required', 'integer', 'min:1950', 'max:'.$year],
            'cylinders'           => ['nullable', 'integer', 'min:1', 'max:16'],
            'mileage'             => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'license_expiry_date' => ['nullable', 'date'],
            'status'              => ['required', Rule::in(array_keys(VehicleController::STATUSES))],

            'purchase_price'      => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'tax_percent'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'selling_price'       => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'discount_price'      => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'offer_label'         => ['nullable', 'string', 'max:60'],
            'offer_ends_at'       => ['nullable', 'date'],
            'price_on_request'    => ['boolean'],
            'is_published'        => ['boolean'],
            'is_featured'         => ['boolean'],

            'specs'               => ['nullable', 'array'],
            'specs.transmission'  => ['nullable', 'string', 'max:50'],
            'specs.drive'         => ['nullable', 'string', 'max:50'],
            'specs.seats'         => ['nullable', 'integer', 'min:1', 'max:60'],
            'specs.doors'         => ['nullable', 'integer', 'min:1', 'max:10'],
            'specs.engine'        => ['nullable', 'string', 'max:50'],
            'specs.fuel'          => ['nullable', 'string', 'max:50'],

            'description_ar'      => ['nullable', 'string', 'max:5000'],
            'description_en'      => ['nullable', 'string', 'max:5000'],
            'notes'               => ['nullable', 'string', 'max:2000'],

            'images'              => ['nullable', 'array', 'max:15'],
            'images.*'            => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_images'       => ['nullable', 'array'],
            'remove_images.*'     => ['string'],
            'cover_image'         => ['nullable', 'string'],
            // الرخصة وحالة العربية (قبل / بعد)
            'license_front'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'license_back'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_license'      => ['nullable', 'array'],
            'remove_license.*'    => ['in:front,back'],
            'before_images'       => ['nullable', 'array', 'max:5'],
            'before_images.*'     => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'after_images'        => ['nullable', 'array', 'max:5'],
            'after_images.*'      => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_condition'    => ['nullable', 'array'],
            'remove_condition.*'  => ['string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $selling = (float) $this->input('selling_price');
                $discount = $this->input('discount_price');

                if ($this->boolean('is_published') && ! $this->boolean('price_on_request') && $selling <= 0) {
                    $validator->errors()->add('selling_price', 'عشان تنشر العربية في المتجر لازم تحط سعر البيع، أو تختار "السعر عند الطلب".');
                }

                if ($discount !== null && $discount !== '' && (float) $discount >= $selling) {
                    $validator->errors()->add('discount_price', 'سعر الخصم لازم يكون أقل من سعر البيع.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_code' => 'كود المركبة', 'brand_id' => 'الماركة', 'model_id' => 'الطراز',
            'supplier_id' => 'المورد', 'import_source_id' => 'جهة الاستيراد', 'branch_id' => 'الفرع',
            'plate_number' => 'رقم اللوحة', 'chassis_number' => 'رقم الشاسيه', 'engine_number' => 'رقم المحرك',
            'color' => 'اللون', 'manufacturing_year' => 'سنة الصنع', 'cylinders' => 'عدد السلندرات',
            'mileage' => 'العداد', 'license_expiry_date' => 'انتهاء الرخصة', 'status' => 'الحالة',
            'purchase_price' => 'سعر الشراء', 'tax_percent' => 'الضريبة', 'selling_price' => 'سعر البيع',
            'discount_price' => 'سعر الخصم', 'images.*' => 'الصورة', 'images' => 'الصور',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'unique'   => ':attribute مسجل على عربية تانية.',
            'exists'   => ':attribute مش صحيح.',
            'integer'  => ':attribute لازم يكون رقم صحيح.',
            'numeric'  => ':attribute لازم يكون رقم.',
            'min'      => ':attribute أقل من المسموح.',
            'max'      => ':attribute أكبر من المسموح.',
            'image'    => ':attribute لازم تكون صورة.',
            'mimes'    => ':attribute لازم تكون JPG أو PNG أو WEBP.',
            'date'     => ':attribute لازم يكون تاريخ.',
            'in'       => 'قيمة :attribute مش صحيحة.',
            'model_id.exists' => 'الطراز ده مش تابع للماركة المختارة.',
            'images.*.max'    => 'كل صورة لازم تكون أقل من 5 ميجا.',
            'before_images.max' => 'أقصى 5 صور لحالة العربية قبل.',
            'after_images.max'  => 'أقصى 5 صور لحالة العربية دلوقتي.',
            'license_front.max' => 'الصورة لازم تكون أقل من 5 ميجا.',
            'license_back.max'  => 'الصورة لازم تكون أقل من 5 ميجا.',
        ];
    }
}
