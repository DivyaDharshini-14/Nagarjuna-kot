<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class MobileDeviceToken extends Model
{
    protected $fillable = [
        'user_id', 'personal_access_token_id', 'client', 'token', 'platform', 'last_seen_at',
    ];

    protected $hidden = ['token_hash', 'token_ciphertext'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function setTokenAttribute(string $token): void
    {
        $this->attributes['token_hash'] = hash('sha256', $token);
        $this->attributes['token_ciphertext'] = Crypt::encryptString($token);
    }

    public function getTokenAttribute(): string
    {
        return Crypt::decryptString($this->attributes['token_ciphertext']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(\Laravel\Sanctum\PersonalAccessToken::class, 'personal_access_token_id');
    }
}
