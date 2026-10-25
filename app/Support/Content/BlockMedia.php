<?php

namespace App\Support\Content;

use App\Models\Media;
use Illuminate\Support\Collection;

final class BlockMedia
{
    /** @return Collection<int, Media> approved media used by the blocks, keyed by id */
    public static function for(array $blocks): Collection
    {
        $ids = [];
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'image') {
                $ids[] = (int) $b['media_id'];
            }
        }
        if ($ids === []) {
            return collect();
        }

        return Media::query()->whereIn('id', $ids)->where('status', 'approved')->get()->keyBy('id');
    }
}
