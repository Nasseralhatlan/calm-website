<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name',
        'avatar',
        'locale',
        'gender',
        'age',
        'birth_date',
        'phone',
        'email',
        'country_id',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
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

    public function isHost(): bool
    {
        return $this->places()->exists();
    }

    /** Bookings this user has made as a guest. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'guest_user_id');
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
