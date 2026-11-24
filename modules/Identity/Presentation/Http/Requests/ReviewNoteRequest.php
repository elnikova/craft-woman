<?php

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Rejecting or suspending a Maker requires a note the Maker will see. */
class ReviewNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin check is done by the route middleware
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
