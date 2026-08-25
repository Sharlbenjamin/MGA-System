<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GopItem extends Model
{
    public const TYPE_SERVICE = 'service';

    public const TYPE_FILE_FEE = 'file_fee';

    protected $fillable = [
        'gop_id',
        'description',
        'cost',
        'selling_cost',
        'item_type',
        'sort_order',
    ];

    protected $attributes = [
        'item_type' => self::TYPE_SERVICE,
        'cost' => 0,
        'selling_cost' => 0,
        'sort_order' => 0,
    ];

    protected $casts = [
        'gop_id' => 'integer',
        'cost' => 'float',
        'selling_cost' => 'float',
        'sort_order' => 'integer',
    ];

    public function gop(): BelongsTo
    {
        return $this->belongsTo(Gop::class);
    }

    public function isFileFeeItem(): bool
    {
        return $this->item_type === self::TYPE_FILE_FEE;
    }

    public function isServiceItem(): bool
    {
        return ! $this->isFileFeeItem();
    }
};
