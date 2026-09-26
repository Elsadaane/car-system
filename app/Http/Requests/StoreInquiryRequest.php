<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الفورم مفتوح للزوار
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'min:3', 'max:120'],
            'phone'          => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'email'          => ['nullable', 'email', 'max:150'],
            'message'        => ['nullable', 'string', 'max:1000'],
            'type'           => ['required', 'in:inquiry,test_drive,reservation,finance'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],

            // فخ للبوتات - المفروض يفضل فاضي
            'website'        => ['nullable', 'size:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'الاسم مطلوب',
            'name.min'                => 'الاسم قصير أوي',
            'phone.required'          => 'رقم التليفون مطلوب',
            'phone.regex'             => 'رقم التليفون مش صحيح',
            'phone.min'               => 'رقم التليفون قصير',
            'email.email'             => 'الإيميل مش صحيح',
            'message.max'             => 'الرسالة طويلة أوي (1000 حرف كحد أقصى)',
            'type.in'                 => 'نوع الطلب مش صحيح',
            'preferred_date.after_or_equal' => 'الميعاد لازم يكون النهاردة أو بعدين',
            'website.size'            => 'حصل خطأ، حاول تاني',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'           => 'الاسم',
            'phone'          => 'التليفون',
            'email'          => 'الإيميل',
            'message'        => 'الرسالة',
            'preferred_date' => 'الميعاد المفضل',
        ];
    }
}
