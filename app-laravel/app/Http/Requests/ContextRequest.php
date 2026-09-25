<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}