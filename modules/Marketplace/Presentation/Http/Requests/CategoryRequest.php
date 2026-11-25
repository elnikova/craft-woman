<?php

namespace Modules\Marketplace\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin check is done by the route middleware
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:2', 'max:80'],
            'parent_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:categories,uuid'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_visible' => ['sometimes', 'boolean'],
            'content_locale' => ['sometimes', \Illuminate\Validation\Rule::in(\App\Support\Locales::SUPPORTED)],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'min:2', 'max:80'],
            'translations.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => __('validation.attributes.category_name')];
    }
}
