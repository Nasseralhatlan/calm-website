<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single source of truth for user lookup, creation, and profile updates.
 *
 * Both transports (web Blade redirects, API JSON responses) call into this
 * service so the same business rules apply — column choice per OTP channel,
 * default role on auto-create, allowed-attribute filtering on update.
 */
final class UserService
{
    /**
     * Find a user by the channel they're authenticating through.
     */
    public function findByIdentifier(OtpType $type, string $identifier): ?User
    {
        return User::query()
            ->where($this->columnFor($type), $identifier)
            ->first();
    }

    /**
     * Find a user by their identifier, creating an unverified shell if missing.
     * Used by the OTP request flow on first contact.
     */
    public function findOrCreateForOtp(OtpType $type, string $identifier): User
    {
        return User::query()->firstOrCreate(
            [$this->columnFor($type) => $identifier],
            ['role' => UserRole::User->value],
        );
    }

    /**
     * Phone-flavored alias for {@see findOrCreateForOtp()}. Used by the host
     * wizard when an admin types a "attach to host phone" — same find-or-create
     * semantics, but the call site reads naturally outside the OTP context.
     */
    public function findOrCreateByPhone(string $phone): User
    {
        return $this->findOrCreateForOtp(OtpType::Phone, $phone);
    }

    /**
     * Apply a validated attribute set to the user.
     * Only mass-assigned columns make it through; role is never editable here.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function update(User $user, array $attrs): User
    {
        unset($attrs['role'], $attrs['phone_verified_at'], $attrs['email_verified_at']);

        // A profile-picture upload arrives as an UploadedFile — store it to S3
        // and replace the attribute with the object key before filling. The
        // previous avatar (if any) is removed so we don't orphan objects.
        if (isset($attrs['avatar']) && $attrs['avatar'] instanceof UploadedFile) {
            $old = $user->avatar;
            $attrs['avatar'] = $this->storeAvatar($attrs['avatar']);

            if ($old !== null && $old !== '' && ! str_starts_with($old, 'http')) {
                Storage::disk('s3')->delete($old);
            }
        }

        $user->fill($attrs)->save();

        return $user->refresh();
    }

    /**
     * Persist a profile picture to the public S3 disk and return its key.
     */
    private function storeAvatar(UploadedFile $file): string
    {
        $ext = Str::lower($file->getClientOriginalExtension() ?: 'jpg');
        $key = 'avatars/'.Str::lower(Str::random(24)).'.'.$ext;

        Storage::disk('s3')->put($key, $file->getContent(), 'public');

        return $key;
    }

    /**
     * Admin-side user update. Unlike {@see update()} above this DOES allow
     * the role to change — used by the admin Users management screen so an
     * admin can promote/demote accounts. Verification timestamps are still
     * locked because they reflect real-world events (the OTP being verified).
     *
     * @param  array<string, mixed>  $attrs
     */
    public function updateAsAdmin(User $user, array $attrs): User
    {
        unset($attrs['phone_verified_at'], $attrs['email_verified_at']);

        $user->fill($attrs)->save();

        return $user->refresh();
    }

    /**
     * Paginated user list for the admin Users tab. Eager-loads place count so
     * the table can show "N places" without N+1 queries.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->withCount('places')
            ->latest('created_at')
            ->paginate($perPage);
    }

    private function columnFor(OtpType $type): string
    {
        return $type === OtpType::Phone ? 'phone' : 'email';
    }
}
