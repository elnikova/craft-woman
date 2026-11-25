<?php

use Modules\Education\Domain\AccessType;
use Modules\Education\Domain\CourseReadiness;
use Modules\Education\Domain\VideoUrl;

uses()->group('education');

it('turns YouTube and Vimeo links into embeds and rejects others', function (string $url, ?string $embed) {
    expect(VideoUrl::parse($url)['embed'] ?? null)->toBe($embed);
})->with([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://youtu.be/dQw4w9WgXcQ?t=10', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789'],
    ['https://vimeo.com/123456789/abcdef1234', 'https://player.vimeo.com/video/123456789?h=abcdef1234'],
    ['https://example.com/video.mp4', null],
]);

it('lists what a course still needs before review', function () {
    $base = ['name' => 'Вʼязання для початківців', 'description' => str_repeat('Опис курсу. ', 4), 'price_minor' => 4900,
        'access_type' => AccessType::Lifetime, 'access_days' => null, 'lessons' => 3, 'lessons_without_materials' => 0, 'required_lessons' => 2];

    expect(CourseReadiness::missing($base))->toBe([])
        ->and(CourseReadiness::missing([...$base, 'lessons' => 0]))->toBe(['lessons'])
        ->and(CourseReadiness::missing([...$base, 'lessons_without_materials' => 1]))->toBe(['lesson_materials'])
        ->and(CourseReadiness::missing([...$base, 'required_lessons' => 0]))->toBe(['required_lessons'])
        ->and(CourseReadiness::missing([...$base, 'access_type' => AccessType::Limited]))->toBe(['access_days'])
        ->and(CourseReadiness::missing([...$base, 'price_minor' => 0, 'description' => 'коротко']))->toBe(['description', 'price']);
});
