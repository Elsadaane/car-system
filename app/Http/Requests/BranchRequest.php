<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageSettings();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_main' => $this->boolean('is_main')]);
    }

    public function rules(): array
    {
        $id = $this->route('branch')?->id;

        return [
            'branch_code'         => ['required', 'string', 'max:20', Rule::unique('branches', 'branch_code')->ignore($id)],
            'name_ar'             => ['required', 'string', 'max:255'],
            'name_en'             => ['nullable', 'string', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'mobile'              => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:255'],
            'city'                => ['nullable', 'string', 'max:100'],
            'region'              => ['nullable', 'string', 'max:100'],
            'address'             => ['nullable', 'string', 'max:1000'],
            'location_url'        => ['nullable', 'url', 'max:255'],
            'manager_id'          => ['nullable', Rule::exists('users', 'id')->whereIn('role', ['staff', 'admin'])],
            'commercial_register' => ['nullable', 'string', 'max:50'],
            'tax_number'          => ['nullable', 'string', 'max:50'],
            'is_main'             => ['boolean'],
            'status'              => ['required', Rule::in(['active', 'inactive'])],
            'opening_date'        => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_code'         => 'كود الفرع',
            'name_ar'             => 'اسم الفرع بالعربي',
            'name_en'             => 'اسم الفرع بالإنجليزي',
            'phone'               => 'التليفون',
            'mobile'              => 'الموبايل',
            'email'               => 'البريد الإلكتروني',
            'city'                => 'المدينة',
            'region'              => 'المنطقة',
            'address'             => 'العنوان',
            'location_url'        => 'رابط الموقع',
            'manager_id'          => 'مدير الفرع',
            'commercial_register' => 'السجل التجاري',
            'tax_number'          => 'الرقم الضريبي',
            'status'              => 'الحالة',
            'opening_date'        => 'تاريخ الافتتاح',
            'notes'               => 'الملاحظات',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'unique'   => ':attribute مستخدم قبل كده.',
            'email'    => ':attribute مش صحيح.',
            'url'      => ':attribute لازم يكون رابط صحيح يبدأ بـ https://',
            'exists'   => ':attribute مش موجود.',
            'max'      => ':attribute أطول من المسموح.',
            'date'     => ':attribute لازم يكون تاريخ.',
            'in'       => 'قيمة :attribute مش صحيحة.',
        ];
    }
}
