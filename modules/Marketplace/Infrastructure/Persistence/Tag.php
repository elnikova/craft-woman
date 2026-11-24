<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Reusable descriptive label (domain-model.md §5.5). Created on the fly when a Maker tags a product. */
class Tag extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['name', 'slug'];

    /**
     * @param  list<string>  $names
     * @return list<string> tag uuids
     */
    public static function resolveNames(array $names): array
    {
        return collect($names)
            ->map(fn ($n) => trim(mb_strtolower((string) $n)))
            ->filter(fn ($n) => $n !== '' && mb_strlen($n) <= 50)
            ->unique()
            ->take(10)
            ->map(function ($name) {
                $slug = Str::slug($name) ?: Str::lower(Str::random(8));

                return self::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->uuid;
            })
            ->values()
            ->all();
    }
}
