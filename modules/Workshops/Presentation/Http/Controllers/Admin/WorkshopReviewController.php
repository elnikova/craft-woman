<?php

namespace Modules\Workshops\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Workshops\Application\Services\WorkshopService;
use Modules\Workshops\Domain\WorkshopStatus;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Presentation\Http\Resources\WorkshopPresenter;

/** Workshop moderation (/admin/workshops), the same flow as products and courses. */
class WorkshopReviewController extends Controller
{
    public function __construct(
        private readonly WorkshopService $workshops,
        private readonly WorkshopPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::enum(WorkshopStatus::class)]]);
        $page = Workshop::query()
            ->where('status', $request->string('status')->toString() ?: WorkshopStatus::PendingReview->value)
            ->with('sessions', 'categories')
            ->oldest('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Workshop $w) => $this->presenter->editor($w, []))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function approve(Workshop $workshop): JsonResponse
    {
        return $this->respond($this->workshops->approve($workshop));
    }

    public function reject(Request $request, Workshop $workshop): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->respond($this->workshops->reject($workshop, $data['note']));
    }

    public function archive(Request $request, Workshop $workshop): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->respond($this->workshops->archive($workshop, $data['note']));
    }

    private function respond(Workshop $workshop): JsonResponse
    {
        return response()->json(['data' => $this->presenter->editor($workshop->load('sessions'), [])]);
    }
}
