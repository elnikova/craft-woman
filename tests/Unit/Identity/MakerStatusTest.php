<?php

use Modules\Identity\Domain\Exceptions\InvalidMakerStatusTransition;
use Modules\Identity\Domain\MakerStatus;

uses()->group('makers');

it('allows the documented lifecycle transitions', function (MakerStatus $from, MakerStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue()
        ->and($from->transitionTo($to))->toBe($to);
})->with([
    'draft → pending' => [MakerStatus::Draft, MakerStatus::PendingReview],
    'pending → approved' => [MakerStatus::PendingReview, MakerStatus::Approved],
    'pending → rejected' => [MakerStatus::PendingReview, MakerStatus::Rejected],
    'rejected → pending (resubmit)' => [MakerStatus::Rejected, MakerStatus::PendingReview],
    'approved → suspended' => [MakerStatus::Approved, MakerStatus::Suspended],
    'suspended → approved (reinstate)' => [MakerStatus::Suspended, MakerStatus::Approved],
]);

it('rejects transitions that skip review', function (MakerStatus $from, MakerStatus $to) {
    $from->transitionTo($to);
})->with([
    'draft → approved' => [MakerStatus::Draft, MakerStatus::Approved],
    'rejected → approved' => [MakerStatus::Rejected, MakerStatus::Approved],
    'suspended → pending' => [MakerStatus::Suspended, MakerStatus::PendingReview],
    'approved → draft' => [MakerStatus::Approved, MakerStatus::Draft],
])->throws(InvalidMakerStatusTransition::class);

it('treats only approved makers as active', function () {
    expect(collect(MakerStatus::cases())->filter->isActive()->values()->all())
        ->toBe([MakerStatus::Approved]);
});

it('locks editing while under review or suspended', function () {
    expect(MakerStatus::PendingReview->isEditable())->toBeFalse()
        ->and(MakerStatus::Suspended->isEditable())->toBeFalse()
        ->and(MakerStatus::Draft->isEditable())->toBeTrue()
        ->and(MakerStatus::Rejected->isEditable())->toBeTrue()
        ->and(MakerStatus::Approved->isEditable())->toBeTrue();
});
