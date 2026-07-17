<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicReflectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // form công khai — tổ chức xã hội không cần tài khoản
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'subject'           => ['required', 'string', 'max:255'],
            'content'           => ['required', 'string', 'min:20', 'max:5000'],
            'contact_name'      => ['nullable', 'string', 'max:255'],
            'contact_email'     => ['required', 'email', 'max:255'],
            'contact_phone'     => ['nullable', 'string', 'max:30'],

            // Bẫy spam: trường ẩn, người thật không bao giờ điền.
            'website'           => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'organization_name' => 'tên tổ chức',
            'subject'           => 'tiêu đề',
            'content'           => 'nội dung phản ánh',
            'contact_name'      => 'người liên hệ',
            'contact_email'     => 'email liên hệ',
            'contact_phone'     => 'số điện thoại',
        ];
    }

    public function messages(): array
    {
        return [
            'content.min'         => 'Nội dung phản ánh cần ít nhất :min ký tự để chúng tôi xử lý được.',
            'contact_email.required' => 'Cần email liên hệ để chúng tôi phản hồi kết quả xử lý.',
            'website.prohibited'  => 'Yêu cầu không hợp lệ.',
        ];
    }
}
