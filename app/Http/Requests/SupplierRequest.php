<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public const STATUSES = [
        'active'      => 'نشط',
        'inactive'    => 'غير نشط',
        'suspended'   => 'موقوف مؤقتاً',
        'blacklisted' => 'قائمة سوداء',
    ];

    public function authorize(): bool
    {
        return \App\Support\Access::can($this->isMethod('post') ? 'suppliers.create' : 'suppliers.edit');
    }

    public function rules(): array
    {
        $id = $this->route('supplier')?->id;

        return [
            'supplier_code'       => ['required', 'string', 'max:20', Rule::unique('suppliers', 'supplier_code')->ignore($id)],
            'name_ar'             => ['required', 'string', 'max:255'],
            'name_en'             => ['nullable', 'string', 'max:255'],
            'national_id'         => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'national_id')->ignore($id)],
            'national_id_expiry'  => ['nullable', 'date'],
            'tax_number'          => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'tax_number')->ignore($id)],
            'commercial_register' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'commercial_register')->ignore($id)],
            'phone'               => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:255'],
            'address'             => ['nullable', 'string', 'max:1000'],
            'settlement_days'     => ['required', 'integer', 'min:0', 'max:365'],
            'settlement_terms'    => ['nullable', 'string', 'max:2000'],
            'bank_name'           => ['nullable', 'string', 'max:255'],
            'iban_number'         => ['nullable', 'string', 'max:50'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'credit_limit'        => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'status'              => ['required', Rule::in(array_keys(self::STATUSES))],
            'status_reason'       => ['nullable', 'required_if:status,suspended,blacklisted', 'string', 'max:1000'],
            'branch_id'           => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_code'       => 'كود المورد',
            'name_ar'             => 'الاسم',
            'name_en'             => 'الاسم بالإنجليزي',
            'national_id'         => 'البطاقة الشخصية',
            'national_id_expiry'  => 'تاريخ انتهاء البطاقة',
            'tax_number'          => 'الرقم الضريبي',
            'commercial_register' => 'السجل التجاري',
            'phone'               => 'رقم الهاتف',
            'email'               => 'الإيميل',
            'address'             => 'العنوان',
            'settlement_days'     => 'مدة التسوية',
            'settlement_terms'    => 'شروط التسوية',
            'bank_name'           => 'اسم البنك',
            'iban_number'         => 'رقم الآيبان',
            'account_holder_name' => 'اسم صاحب الحساب',
            'credit_limit'        => 'حد الائتمان',
            'status'              => 'الحالة',
            'status_reason'       => 'سبب الإيقاف',
            'branch_id'           => 'الفرع',
            'notes'               => 'الملاحظات',
        ];
    }

    public function messages(): array
    {
        return [
            'required'    => 'حقل :attribute مطلوب.',
            'required_if' => 'اكتب :attribute.',
            'unique'      => ':attribute مسجل لمورد تاني.',
            'email'       => ':attribute مش صحيح.',
            'numeric'     => ':attribute لازم يكون رقم.',
            'integer'     => ':attribute لازم يكون رقم صحيح.',
            'min'         => ':attribute أقل من المسموح.',
            'max'         => ':attribute أكبر من المسموح.',
            'date'        => ':attribute لازم يكون تاريخ.',
            'in'          => 'قيمة :attribute مش صحيحة.',
            'exists'      => ':attribute مش موجود.',
        ];
    }
}
