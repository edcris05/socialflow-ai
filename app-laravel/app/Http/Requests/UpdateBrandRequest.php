<?php

namespace App\Http\Requests;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    /**
     * Prepare the input for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'social_channels' => $this->lines($this->input('social_channels_text')),
            'restrictions' => $this->lines($this->input('restrictions_text')),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($brand)],
            'description' => ['nullable', 'string', 'max:5000'],
            'tone_of_voice' => ['nullable', 'string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:1000'],
            'social_channels' => ['nullable', 'array'],
            'social_channels.*' => ['string', 'max:100'],
            'restrictions' => ['nullable', 'array'],
            'restrictions.*' => ['string', 'max:1000'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la marca es obligatorio.',
            'slug.required' => 'El slug es obligatorio.',
            'slug.regex' => 'El slug solo puede incluir letras minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una marca con este slug.',
            'social_channels.array' => 'Los canales sociales deben enviarse como una lista.',
            'restrictions.array' => 'Las restricciones deben enviarse como una lista.',
        ];
    }

    /**
     * Convert a multiline field into a clean list.
     *
     * @return array<int, string>|null
     */
    private function lines(?string $value): ?array
    {
        $lines = collect(preg_split('/\R/', $value ?? '') ?: [])
            ->map(static fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();

        return $lines === [] ? null : $lines;
    }
}
