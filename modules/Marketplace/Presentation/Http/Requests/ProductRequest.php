<?php

namespace Modules\Marketplace\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Marketplace\Application\DTOs\ProductData;
use Modules\Marketplace\Domain\Currency;
use Modules\Marketplace\Domain\ProductType;

/**
 * Drafts may be incomplete: only the name is required to save.
 * Completeness is checked on "submit for review" (ProductReadiness).
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership: policy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_amount_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'production_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'shipping_info' => ['nullable', 'string', 'max:1000'],
            'content_locale' => ['sometimes', Rule::in(\App\Support\Locales::SUPPORTED)],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'min:3', 'max:160'],
            'translations.*.description' => ['nullable', 'string', 'max:10000'],
            'translations.*.shipping_info' => ['nullable', 'string', 'max:1000'],
            'category_uuid' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')->where('is_visible', true)],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'variants' => ['sometimes', 'array', 'min:1', 'max:50'],
            'variants.*.uuid' => ['nullable', 'uuid'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.price_amount_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('validation.attributes.product_name'),
            'variants.*.name' => __('validation.attributes.variant_name'),
            'variants.*.stock' => __('validation.attributes.stock'),
        ];
    }

    public function toData(): ProductData
    {
        return ProductData::fromArray($this->validated());
    }
}
