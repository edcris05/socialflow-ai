<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetaConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facebook_page_id' => ['nullable', 'string', 'max:255'],
            'instagram_account_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:5000'],
            'token_expires_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ];
    }
}
