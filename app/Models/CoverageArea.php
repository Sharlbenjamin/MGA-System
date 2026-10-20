<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only row for the coverage screen. Rows are produced by CoverageAreaQuery
 * and are not stored in a coverage_areas table.
 */
class CoverageArea extends Model
{
    protected $table = 'coverage_areas';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'country_id' => 'integer',
        'city_id' => 'integer',
        'service_type_id' => 'integer',
        'branch_count' => 'integer',
    ];
}
