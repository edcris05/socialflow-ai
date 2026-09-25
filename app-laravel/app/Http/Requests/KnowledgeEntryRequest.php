<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => $this->input('category', 'fact'),
            'applicability' => $this->input('applicability', 'global'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:50000'],
            'source' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(['fact', 'product', 'price', 'policy', 'restriction', 'contact', 'brand_identity', 'future_idea'])],
            'applicability' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in(['pending', 'verified', 'archived'])],
        ];
    }
}