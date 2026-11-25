<?php

namespace Modules\Identity\Presentation\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Services\UserAdminService;
use Modules\Identity\Domain\UserStatus;
use Modules\Identity\Presentation\Http\Resources\AdminUserResource;

/** /admin/users — list, block/unblock, buyer ⇄ admin. */
class UserController extends Controller
{
    public function __construct(private readonly UserAdminService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
        ]);

        $users = User::query()
            ->with('makerProfile')
            ->when($request->string('q')->trim()->toString(), function ($q, $term) {
                // SQLite lowercases only ASCII, so also try the term as typed and capitalised (Cyrillic names)
                $variants = array_unique([$term, mb_strtolower($term), mb_convert_case($term, MB_CASE_TITLE)]);
                $q->where(function ($w) use ($variants) {
                    foreach ($variants as $v) {
                        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%';
                        $w->orWhere('name', 'like', $like)->orWhereRaw('lower(email) like ?', [mb_strtolower($like)]);
                    }
                });
            })
            ->when($request->string('role')->toString(), fn ($q, $r) => $q->where('role', $r))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return AdminUserResource::collection($users);
    }

    public function block(Request $request, User $user): AdminUserResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return AdminUserResource::make($this->users->block($request->user(), $user, $data['reason'])->load('makerProfile'));
    }

    public function unblock(Request $request, User $user): AdminUserResource
    {
        return AdminUserResource::make($this->users->unblock($request->user(), $user)->load('makerProfile'));
    }

    public function role(Request $request, User $user): AdminUserResource
    {
        $data = $request->validate(['role' => ['required', Rule::in([Role::Buyer->value, Role::Admin->value])]]);

        return AdminUserResource::make($this->users->setRole($request->user(), $user, Role::from($data['role']))->load('makerProfile'));
    }
}
