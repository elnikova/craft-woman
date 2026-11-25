<?php

namespace Modules\Workshops\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workshops\Application\Services\BookingService;
use Modules\Workshops\Infrastructure\Persistence\WorkshopBooking;
use Modules\Workshops\Presentation\Http\Resources\WorkshopPresenter;

/** The participant's workshops (/me/bookings). */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly WorkshopPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->bookings->forUser($request->user()->uuid)->map(fn (WorkshopBooking $b) => $this->presenter->booking($b))->values(),
        ]);
    }
}
