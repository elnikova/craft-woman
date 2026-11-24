<?php

namespace Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Application\Services\MakerProfileService;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Identity\Presentation\Http\Requests\UpsertMakerProfileRequest;
use Modules\Identity\Presentation\Http\Resources\MakerProfileResource;

/** The signed-in user's own Maker profile ("studio"). */
class MyMakerProfileController extends Controller
{
    public function __construct(private readonly MakerProfileService $service) {}

    public function show(Request $request): JsonResponse|MakerProfileResource
    {
        $profile = $this->ownProfile($request);

        return $profile
            ? MakerProfileResource::make($profile)
            : response()->json(['data' => null]);
    }

    public function upsert(UpsertMakerProfileRequest $request): JsonResponse
    {
        $existing = $this->ownProfile($request);
        $profile = $this->service->upsert($request->user(), $request->toData());

        return MakerProfileResource::make($profile)
            ->response()
            ->setStatusCode($existing ? 200 : 201);
    }

    public function submit(Request $request): MakerProfileResource
    {
        $profile = $this->ownProfile($request) ?? abort(404);

        return MakerProfileResource::make($this->service->submitForReview($profile));
    }

    private function ownProfile(Request $request): ?MakerProfile
    {
        return MakerProfile::query()->where('user_uuid', $request->user()->uuid)->first();
    }
}
