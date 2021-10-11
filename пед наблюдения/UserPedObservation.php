<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\User;

/**
 * App\Models\UserPedObservation
 *
 * @property int $id
 * @property int $ped_observation_id
 * @property int $user_id
 * @property bool $is_favorite
 * @property string|null $note
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\PedObservation $pedObservation
 * @property-read User $user
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation newQuery()
 * @method static \Illuminate\Database\Query\Builder|UserPedObservation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation whereIsFavorite($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation wherePedObservationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserPedObservation whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|UserPedObservation withTrashed()
 * @method static \Illuminate\Database\Query\Builder|UserPedObservation withoutTrashed()
 * @mixin \Eloquent
 */
class UserPedObservation extends Model
{
    use SoftDeletes;

    public const TABLE = 'user_ped_observations';

    protected $casts = [
        'ped_observation_id' => 'integer',
        'user_id' => 'integer',
        'is_favorite' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    protected $fillable = [
        'ped_observation_id',
        'note',
        'is_favorite',
        'user_id',
    ];

    protected $table = self::TABLE;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pedObservation(): BelongsTo
    {
        return $this->belongsTo(PedObservation::class, 'ped_observation_id');
    }
}
