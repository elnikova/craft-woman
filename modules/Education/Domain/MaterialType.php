<?php

namespace Modules\Education\Domain;

/**
 * What a lesson is made of (schema §5.4). Video is an external YouTube/Vimeo link
 * (decision 2026-11-25); files are stored privately and served only to students.
 */
enum MaterialType: string
{
    case Text = 'text';
    case Video = 'video';
    case File = 'file';
    case Link = 'link';
}
