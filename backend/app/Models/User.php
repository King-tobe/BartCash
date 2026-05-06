<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'password',
        'profile_photo',
        'bio',
        'location',
        'average_rating',
        'total_trades',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'average_rating' => 'decimal:2',
            'total_trades' => 'integer',
        ];
    }

    // JWT required methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'type' => 'user',
        ];
    }

    // Relationships
    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function passwordResetTokens()
    {
        return $this->hasMany(PasswordResetToken::class);
    }
}
