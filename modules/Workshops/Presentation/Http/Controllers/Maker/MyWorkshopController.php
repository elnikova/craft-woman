<?php

namespace Modules\Workshops\Presentation\Http\Controllers\Maker;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Identity\Application\Contracts\MakerSummary;
use Modules\Workshops\Application\Services\WorkshopService;
use Modules\Workshops\Domain\BookingStatus;
use Modules\Workshops\Domain\WorkshopFormat;
use Modules\Workshops\Domain\WorkshopStatus;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Infrastructure\Persistence\WorkshopBooking;
use Modules\Workshops\Infrastructure\Persistence\WorkshopSession;
use Modules\Workshops\Presentation\Http\Resources\WorkshopPresenter;

/** The Maker's workshops (/me/workshops): details, sessions, lifecycle and participants. */
class MyWorkshopController extends Controller
{
    public function __construct(
        private readonly WorkshopService $workshops,
        private readonly WorkshopPresenter $presenter,
        private readonly MakerDirectory $makers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::enum(WorkshopStatus::class)]]);
        $maker = $this->maker($request);
        $page = Workshop::query()
            ->where('maker_profile_uuid', $maker->uuid)
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->with('categories', 'sessions')
            ->latest('updated_at')
            ->paginate(24);

        return response()->json($this->presenter->page($page, fn (Workshop $w) => [
            'status' => $w->status->value,
            'review_note' => $w->review_note,
            'name' => $w->name,
            'seats_booked' => $w->seatsTaken(),
        ]));
    }

    public function show(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);

        return $this->editor($workshop);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->editor($this->workshops->create($this->maker($request), $this->validated($request)), 201);
    }

    public function update(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);

        return $this->editor($this->workshops->update($workshop, $this->validated($request)));
    }

    public function cover(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);
        $request->validate(['cover' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192']]);

        return $this->editor($this->workshops->setCover($workshop, $request->file('cover')));
    }

    public function storeSession(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);
        $data = $this->sessionData($request);
        $this->workshops->addSession($workshop, $data['starts_at'], $data['ends_at']);

        return $this->editor($workshop);
    }

    public function updateSession(Request $request, WorkshopSession $session): JsonResponse
    {
        $this->own($request, $session->workshop);
        $data = $this->sessionData($request);
        $this->workshops->updateSession($session, $data['starts_at'], $data['ends_at']);

        return $this->editor($session->workshop);
    }

    public function destroySession(Request $request, WorkshopSession $session): JsonResponse
    {
        $workshop = $session->workshop;
        $this->own($request, $workshop);
        $this->workshops->deleteSession($session);

        return $this->editor($workshop);
    }

    public function submit(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);

        return $this->editor($this->workshops->submitForReview($workshop));
    }

    public function archive(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);

        return $this->editor($this->workshops->archive($workshop));
    }

    public function relist(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);

        return $this->editor($this->workshops->relist($workshop));
    }

    public function cancel(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->editor($this->workshops->cancel($workshop, $data['reason']));
    }

    public function destroy(Request $request, Workshop $workshop): Response
    {
        $this->own($request, $workshop);
        $this->workshops->delete($workshop);

        return response()->noContent();
    }

    /** Confirmed participants with contact e-mail, for the maker's list. */
    public function participants(Request $request, Workshop $workshop): JsonResponse
    {
        $this->own($request, $workshop);
        $rows = $workshop->bookings()
            ->where('status', BookingStatus::Confirmed)
            ->with('user:uuid,name,email')
            ->oldest('confirmed_at')
            ->get()
            ->map(fn (WorkshopBooking $b) => [
                'uuid' => $b->uuid,
                'name' => $b->user?->name,
                'email' => $b->user?->email,
                'seats' => $b->seats,
                'confirmed_at' => $b->confirmed_at,
            ]);

        return response()->json(['data' => $rows, 'meta' => [
            'seats_confirmed' => (int) $rows->sum('seats'),
            'seats_pending' => (int) $workshop->bookings()->where('status', BookingStatus::Pending)->sum('seats'),
            'capacity' => $workshop->capacity,
        ]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $required = $request->isMethod('post') ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'min:3', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'price_amount_minor' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['sometimes', 'in:EUR,USD,UAH'],
            'format' => ['sometimes', Rule::enum(WorkshopFormat::class)],
            'timezone' => ['sometimes', 'timezone:all'],
            'capacity' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'location_country_code' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'location_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'location_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'external_url' => ['sometimes', 'nullable', 'url:https,http', 'max:500'],
            'participant_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:categories,uuid'],
            'content_locale' => ['sometimes', Rule::in(Locales::SUPPORTED)],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'min:3', 'max:160'],
            'translations.*.description' => ['nullable', 'string', 'max:10000'],
            'translations.*.participant_note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => __('validation.attributes.workshop_name'),
            'external_url' => __('validation.attributes.meeting_url'),
            'capacity' => __('validation.attributes.capacity'),
        ]);
    }

    /** @return array{starts_at: string, ends_at: string} */
    private function sessionData(Request $request): array
    {
        return $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);
    }

    private function editor(Workshop $workshop, int $status = 200): JsonResponse
    {
        $workshop->refresh()->load('sessions', 'categories');

        return response()->json(['data' => $this->presenter->editor($workshop, $this->workshops->missingForReview($workshop))], $status);
    }

    private function maker(Request $request): MakerSummary
    {
        return $this->makers->forUser($request->user()->uuid) ?? abort(403);
    }

    private function own(Request $request, Workshop $workshop): void
    {
        abort_unless($this->makers->forUser($request->user()->uuid)?->uuid === $workshop->maker_profile_uuid, 404);
    }
}
