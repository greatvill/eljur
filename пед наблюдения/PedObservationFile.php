<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedObservationFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'ped_observation_id',
        'size',
        'fid',
    ];

    protected $casts = [
        'ped_observation_id' => 'integer',
        'size' => 'integer',
    ];

    public function pedObservation(): BelongsTo
    {
        return $this->belongsTo(PedObservation::class, 'ped_observation_id');
    }
}
