<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Trade extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'proposer_id',
        'receiver_id',
        'status',
        'completion_method',
        'proposer_confirmed',
        'receiver_confirmed',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'proposer_confirmed' => 'boolean',
        'receiver_confirmed' => 'boolean',
        'completed_at'       => 'datetime',
        'cancelled_at'       => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function tradeItems()
    {
        return $this->hasMany(TradeItem::class);
    }

    public function proposerItems()
    {
        return $this->hasMany(TradeItem::class)->where('side', 'proposer')->with('item.primaryImage', 'item.latestValuation');
    }

    public function receiverItems()
    {
        return $this->hasMany(TradeItem::class)->where('side', 'receiver')->with('item.primaryImage', 'item.latestValuation');
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function recentMessages()
    {
        return $this->hasMany(Message::class)->latest()->limit(2);
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
}