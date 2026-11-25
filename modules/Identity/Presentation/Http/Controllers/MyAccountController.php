<?php

namespace Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Application\Services\AccountService;

/** /me/account — the signed-in user's own settings. */
class MyAccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function update(Request $request): UserResource
    {
        $user = $request->user();
        $emailChanged = mb_strtolower((string) $request->input('email')) !== mb_strtolower($user->email);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->uuid, 'uuid')],
            // Changing the e-mail changes how you sign in, so it needs the password
            'current_password' => [$emailChanged ? 'required' : 'nullable', 'string', 'current_password:web'],
        ]);

        return UserResource::make($this->accounts->updateProfile($user, $data)->load('makerProfile'));
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $this->accounts->changePassword($request->user(), $data['password'], $request->hasSession() ? $request->session()->getId() : null);

        return response()->json(['message' => __('auth.password_changed')]);
    }
}
