<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\PedObservation
 *
 * @property int $id
 * @property string $text
 * @property int $student_id
 * @property int $author_id
 * @property boolean $is_viewed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User $student
 * @property-read User $author
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation newQuery()
 * @method static \Illuminate\Database\Query\Builder|PedObservation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation query()
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PedObservation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|PedObservation withTrashed()
 * @method static \Illuminate\Database\Query\Builder|PedObservation withoutTrashed()
 * @mixin \Eloquent
 */
class PedObservation extends Model
{
    use SoftDeletes;

    public const TABLE = 'ped_observations';

    protected $table = self::TABLE;

    protected $fillable = [
        'student_id',
        'text',
        'author_id',
        'is_viewed',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'author_id' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'is_viewed' => 'boolean',
    ];
    /**
     * @var array
     */
    public $actions;

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function pedObservationFiles(): HasMany
    {
        return $this->hasMany(PedObservationFile::class, 'ped_observation_id', 'id');
    }

    public function userPedObservations(): HasMany
    {
        return $this->hasMany(UserPedObservation::class, 'ped_observation_id');
    }

    public function setActions(array $actions): PedObservation
    {
        $this->actions = $actions;
        return $this;
    }
}
