<?php

namespace App\Facades;

use App\Models\PedObservation;
use App\Models\Role;
use App\Models\UserPedObservation;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class POFacade
{
    private function baseQuery()
    {
        //TODO  переделать join на with
        return PedObservation::query()
            ->select([
                sprintf('%s.id as id', PedObservation::TABLE),
                sprintf('%s.created_at as created_at', PedObservation::TABLE),
                sprintf('%s.updated_at as updated_at', PedObservation::TABLE),
                sprintf('%s.text as text', PedObservation::TABLE),
                sprintf('%s.is_viewed as is_viewed', PedObservation::TABLE),
                sprintf('%s.is_favorite as is_favorite', UserPedObservation::TABLE),
                sprintf('%s.note as note', UserPedObservation::TABLE),

                'author.id as author_id',
                'author.firstname as author_firstname',
                'author.lastname as author_lastname',
                'author.middlename as author_middlename',

                'curator.id as curator_id',
                'curator.firstname as curator_firstname',
                'curator.lastname as curator_lastname',
                'curator.middlename as curator_middlename',

                sprintf('%s.id as student_id', User::TABLE),
                sprintf('%s.firstname as student_firstname', User::TABLE),
                sprintf('%s.lastname as student_lastname', User::TABLE),
                sprintf('%s.middlename as student_middlename', User::TABLE),
                sprintf('%s.study_group as student_group', User::TABLE),
                sprintf('%s.direction as student_direction', User::TABLE),
            ])
            ->with('pedObservationFiles')
            ->leftJoin(User::TABLE, function (JoinClause $join) {
                $join
                    ->on(
                        sprintf('%s.student_id', PedObservation::TABLE),
                        sprintf('%s.id', User::TABLE)
                    );
            })
            ->leftJoin(
                sprintf('%s as author', User::TABLE),
                sprintf('%s.author_id', PedObservation::TABLE),
                'author.id'
            )
            ->leftJoin(
                sprintf('%s as curator', User::TABLE),
                sprintf('%s.curator_id', User::TABLE),
                'curator.id'
            )
            ->leftJoin(UserPedObservation::TABLE, function (JoinClause $join) {
                $join
                    ->on(
                        sprintf('%s.ped_observation_id', UserPedObservation::TABLE),
                        sprintf('%s.id', PedObservation::TABLE)
                    )
                    ->whereColumn(
                        sprintf('%s.curator_id', User::TABLE),
                        sprintf('%s.user_id', UserPedObservation::TABLE)
                    );
            });
    }

    public function getByAuthor(User $user)
    {
        $query = $this->baseQuery();
        $query->where(sprintf('%s.author_id', PedObservation::TABLE), $user->id);
        return $query->get();
    }

    public function getByCurator(User $user, array $filter = [])
    {
        $query = $this->baseQuery();
        $query->orderByDesc(sprintf('%s.updated_at', PedObservation::TABLE));
        $query->where(sprintf('%s.curator_id', User::TABLE), $user->id);

        if (isset($filter['count'])) {
            $query->limit($filter['count']);
        }
        if (isset($filter['student_id'])) {
            /**
             * @var User|null $student
             */
            $student = User::find($filter['student_id']);

            if (
                !$student
                || $student->curator_id !== $user->id
                || !in_array(Role::CODE_STUDENT, $student->roles->pluck('code')->toArray(), true)
            ) {
                return new Collection();
            }
            $query->where(sprintf('%s.student_id', PedObservation::TABLE), $filter['student_id']);
        }

        return $query->get();

    }

    public function getByStudent(User $student)
    {
        $query = $this->baseQuery();
        $query->where('student_id', $student->id);
        $query->orderByDesc(sprintf('%s.updated_at', PedObservation::TABLE));
        return $query->get();
    }

    public function getAll()
    {
        $query = $this->baseQuery();
        return $query->get();
    }

    public function getPedObservation(int $id): PedObservation
    {
        /**
         * @var PedObservation|null $pedObservation
         */
        $pedObservation = PedObservation::find($id);
        if (is_null($pedObservation)) {
            throw new NotFoundHttpException('PedObservation not found');
        }
        return $pedObservation;
    }

    public function getActions(PedObservation $pedObservation): array
    {
        /**
         * @var User $user
         */
        $user = auth()->user();
        $actions = [];
        if ($user->id === (int)$pedObservation->author_id) {
            $actions[] = 'delete';
            $actions[] = 'edit';
        }
        if ($user->id === (int)($pedObservation->curator_id ?? $pedObservation->student->curator_id ?? -1)) {
            $actions[] = 'manage_note';
            $actions[] = 'manage_favorite';
        }
        return $actions;
    }
}
