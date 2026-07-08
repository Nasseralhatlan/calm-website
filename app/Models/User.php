<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'avatar',
        'locale',
        'gender',
        'age',
        'birth_date',
        'phone',
        'email',
        'deleted_phone',
        'deleted_email',
        'country_id',
        'bank',
        'bank_account',
        'bank_account_name',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * In-memory defaults so a just-created user (e.g. the OTP shell or an
     * admin-attached host) carries its locale before being reloaded — keeps
     * notifications/OTP from seeing a null locale. Mirrors the DB default.
     */
    protected $attributes = [
        'locale' => 'ar',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'age' => 'integer',
            'birth_date' => 'date',
        ];
    }

    /**
     * Public URL for the profile picture. Stored value is an S3 object key
     * (or already a full URL, passed through). Null when no avatar is set.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar === null || $this->avatar === '') {
            return null;
        }

        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        return Storage::disk('s3')->url($this->avatar);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class);
    }

    /** Expo push tokens across this user's devices. */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** In-app notification feed (newest first). */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class)->latest();
    }

    /**
     * Places this user hosts (a user "becomes a host" implicitly the first
     * time they finish a place registration — there's no separate host_id).
     */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class, 'host_user_id');
    }

    /**
     * A host is anyone with a live listing OR who has hosted a real booking —
     * the latter keeps host access (e.g. to view guest bookings + finances)
     * even after they delete their only place. host_user_id is denormalized on
     * bookings and survives the place's soft-delete, so deleted-place bookings
     * still count. Scoped to confirmed/completed so abandoned holds don't.
     */
    public function isHost(): bool
    {
        return $this->places()->exists()
            || $this->hostBookings()
                ->whereIn('booking_status', [
                    BookingStatus::Confirmed->value,
                    BookingStatus::Completed->value,
                ])
                ->exists();
    }

    /** Bookings this user has made as a guest. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'guest_user_id');
    }

    /** Bookings where this user is the host (host_user_id), incl. on deleted places. */
    public function hostBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'host_user_id');
    }

    /** Places this user has liked (powers the heart icon + favorites list). */
    public function likedPlaces(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'place_likes')
            ->using(PlaceLike::class)
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role?->value,
        ];
    }
}
