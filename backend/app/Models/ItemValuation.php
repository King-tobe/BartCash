<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ItemValuation extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'value_min',
        'value_max',
        'currency',
        'confidence',
        'status',
        'failed_reason',
        'raw_response',
    ];

    protected $casts = [
        'value_min'    => 'decimal:2',
        'value_max'    => 'decimal:2',
        'raw_response' => 'array',
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

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}