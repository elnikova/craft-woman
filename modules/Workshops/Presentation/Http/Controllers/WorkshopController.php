<?php

namespace Modules\Workshops\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Workshops\Application\Services\BookingService;
use Modules\Workshops\Domain\WorkshopStatus;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Presentation\Http\Resources\WorkshopPresenter;

/** Public workshop calendar and pages (/workshops). */
class WorkshopController extends Controller
{
    public function __construct(
        private readonly WorkshopPresenter $presenter,
        private readonly BookingService $bookings,
        private readonly MakerDirectory $makers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'in:online,offline'],
            'city' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
            'maker' => ['nullable', 'string', 'max:140'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $query = Workshop::query()->published()->upcoming()
            ->whereIn('maker_profile_uuid', $this->makers->approvedMakerUuidsQuery())
            ->with('categories', 'sessions');

        if ($term = trim($request->string('q')->toString())) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('translations', "%{$term}%"));
        }
        if ($format = $request->string('format')->toString()) {
            $query->where('format', $format);
        }
        if ($city = trim($request->string('city')->toString())) {
            $query->whereLike('location_city', "%{$city}%");
        }
        if ($slug = $request->string('category')->toString()) {
            $uuids = Category::query()->visible()->where('slug', $slug)->first()?->selfAndDescendantUuids() ?? [];
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.uuid', $uuids));
        }
        if ($slug = $request->string('maker')->toString()) {
            $query->where('maker_profile_uuid', $this->makers->findBySlug($slug)?->uuid);
        }
        // Soonest first
        $query->orderBy(fn ($q) => $q->selectRaw('min(starts_at)')->from('workshop_sessions')->whereColumn('workshop_sessions.workshop_uuid', 'workshops.uuid'));

        return response()->json($this->presenter->page($query->paginate($request->integer('per_page', 12) ?: 12)->withQueryString()));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $workshop = Workshop::query()->where('slug', $slug)->with('sessions')->firstOrFail();
        $user = $request->user('sanctum');
        $isOwner = $user !== null && $this->makers->forUser($user->uuid)?->uuid === $workshop->maker_profile_uuid;
        $seats = $user ? $this->bookings->seatsOf($user->uuid, $workshop) : 0;
        $public = in_array($workshop->status, [WorkshopStatus::Published, WorkshopStatus::Cancelled, WorkshopStatus::Archived], true)
            && in_array($workshop->maker_profile_uuid, $this->makers->approvedMakerUuidsQuery()->pluck('uuid')->all(), true);

        // Former participants still see a cancelled or archived workshop they booked
        $hadBooking = $user !== null && $workshop->bookings()->where('user_uuid', $user->uuid)->whereNotNull('confirmed_at')->exists();
        abort_unless(($public && ($workshop->status === WorkshopStatus::Published || $hadBooking)) || $isOwner || $user?->isAdmin(), 404);

        return response()->json(['data' => [
            ...$this->presenter->detail($workshop, $seats > 0 || $isOwner || (bool) $user?->isAdmin()),
            'is_owner' => $isOwner,
            'my_seats' => $seats,
        ]]);
    }
}
