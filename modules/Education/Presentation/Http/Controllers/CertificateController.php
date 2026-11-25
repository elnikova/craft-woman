<?php

namespace Modules\Education\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Education\Infrastructure\Persistence\Certificate;

/** Public verification: anyone with the number can check a certificate is genuine. */
class CertificateController extends Controller
{
    public function show(Certificate $certificate): JsonResponse
    {
        return response()->json(['data' => self::present($certificate)]);
    }

    /** @return array<string, mixed> */
    public static function present(Certificate $c): array
    {
        $course = $c->enrollment->course;

        return [
            'number' => $c->certificate_number,
            'holder_name' => $c->holder_name,
            'course_name' => $c->course_name,
            'maker_name' => $c->maker_name,
            'issued_at' => $c->issued_at,
            'course_slug' => $course?->trashed() ? null : $course?->slug,
        ];
    }
}
