<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class StaffInvitation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a fresh single-use token for $user, replacing any prior one.
     * Returns the PLAIN token (only the sha256 hash is stored).
     */
    public static function issueFor(User $user): string
    {
        static::where('user_id', $user->id)->delete();

        $plain = Str::random(64);

        static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(24),
            'used_at' => null,
        ]);

        return $plain;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public static function signedUrlFor(User $user, string $plainToken): string
    {
        $expiresAt = static::where('user_id', $user->id)->value('expires_at') ?? now()->addHours(24);

        return URL::temporarySignedRoute('staff.setup-password', $expiresAt, [
            'token' => $plainToken,
            'email' => $user->email,
        ]);
    }
}
