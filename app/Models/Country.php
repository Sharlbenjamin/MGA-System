<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'iso',
        'name',
        'nicename',
        'iso3',
        'numcode',
        'phonecode'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function provinces()
    {
        return $this->hasMany(Province::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }
}
