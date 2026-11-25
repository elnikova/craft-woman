<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! auth()->guard('web')->attempt($this->safe(['email', 'password']), $this->boolean('remember'))) {
                    $validator->errors()->add('password', __('auth.failed'));

                    return;
                }
                // Correct password, but the account is blocked: don't keep the session
                if (auth()->guard('web')->user()?->isBlocked()) {
                    auth()->guard('web')->logout();
                    $validator->errors()->add('email', __('auth.blocked'));
                }
            },
        ];
    }
}
