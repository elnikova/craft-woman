<?php

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Identity\Application\DTOs\MakerProfileData;

class UpsertMakerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is checked by the controller via the policy
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:2', 'max:120'],
            'bio' => ['nullable', 'string', 'max:280'],
            'story' => ['nullable', 'string', 'max:5000'],
            'avatar_url' => ['nullable', 'url', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'follow_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): MakerProfileData
    {
        return MakerProfileData::fromArray($this->validated());
    }
}
