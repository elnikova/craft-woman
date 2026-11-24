<?php

namespace Modules\Identity\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Services\MakerProfileService;
use Modules\Identity\Domain\MakerStatus;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Identity\Presentation\Http\Requests\ReviewNoteRequest;
use Modules\Identity\Presentation\Http\Resources\MakerProfileResource;

/** Administrator review of Maker profiles. Access: `can:review,MakerProfile` on the route group. */
class MakerReviewController extends Controller
{
    public function __construct(private readonly MakerProfileService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(MakerStatus::class)],
        ]);

        $status = $request->enum('status', MakerStatus::class) ?? MakerStatus::PendingReview;

        $profiles = MakerProfile::query()
            ->with('user')
            ->where('status', $status)
            ->oldest('updated_at') // the longest-waiting first
            ->paginate(20);

        return MakerProfileResource::collection($profiles);
    }

    public function approve(MakerProfile $makerProfile): MakerProfileResource
    {
        return $this->respond($this->service->approve($makerProfile));
    }

    public function reject(ReviewNoteRequest $request, MakerProfile $makerProfile): MakerProfileResource
    {
        return $this->respond($this->service->reject($makerProfile, $request->string('note')->toString()));
    }

    public function suspend(ReviewNoteRequest $request, MakerProfile $makerProfile): MakerProfileResource
    {
        return $this->respond($this->service->suspend($makerProfile, $request->string('note')->toString()));
    }

    public function reinstate(MakerProfile $makerProfile): MakerProfileResource
    {
        return $this->respond($this->service->reinstate($makerProfile));
    }

    private function respond(MakerProfile $profile): MakerProfileResource
    {
        return MakerProfileResource::make($profile->load('user'));
    }
}
