<?php

namespace Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Identity\Presentation\Http\Resources\MakerProfileResource;

/** Public Maker directory. */
class MakerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $makers = MakerProfile::query()
            ->approved()
            ->when($request->string('city')->toString(), fn ($q, $city) => $q->where('city', $city))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->whereLike('display_name', "%{$term}%"))
            ->orderByDesc('approved_at')
            ->paginate(min(max($request->integer('per_page', 12), 1), 48));

        return MakerProfileResource::collection($makers);
    }

    public function show(Request $request, string $slug): MakerProfileResource
    {
        $profile = MakerProfile::query()->where('slug', $slug)->firstOrFail();

        // Unapproved profiles are hidden from the public: 404, not 403, so their existence isn't revealed.
        abort_unless(Gate::forUser($request->user())->allows('view', $profile), 404);

        return MakerProfileResource::make($profile);
    }
}
