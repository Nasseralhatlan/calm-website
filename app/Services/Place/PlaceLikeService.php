<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\Place;
use App\Models\User;

/**
 * Like / unlike operations for the heart-icon UX. Both ops are idempotent —
 * re-liking is a no-op, unliking something you don't like is a no-op — so
 * the mobile app can fire them without precondition checks.
 */
final class PlaceLikeService
{
    /**
     * Returns the resulting state: true = liked, false = unliked.
     * Idempotent: liking twice still ends up "liked".
     */
    public function like(User $user, Place $place): bool
    {
        $user->likedPlaces()->syncWithoutDetaching([$place->id]);

        return true;
    }

    public function unlike(User $user, Place $place): bool
    {
        $user->likedPlaces()->detach($place->id);

        return false;
    }

    /** Convenience for a single toggle endpoint if mobile prefers that. */
    public function toggle(User $user, Place $place): bool
    {
        return $user->likedPlaces()->where('places.id', $place->id)->exists()
            ? $this->unlike($user, $place)
            : $this->like($user, $place);
    }
}
