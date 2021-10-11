    public function getStudentsWithIgw(array $filter): EloquentBuilder
    {
        /** @var User $user */
        $user = auth()->user();
        $query = User::query()
            ->select(sprintf('%s.*', User::TABLE))
            ->with([
                'igw',
                'igw.version',
                'igw.stage',
            ])
            ->where('curator_id', $user->id)
            ->join(
                UserRole::TABLE,
                sprintf('%s.id', User::TABLE),
                '=',
                sprintf('%s.user_id', UserRole::TABLE)
            )
            ->join(
                Role::TABLE,
                sprintf('%s.role_id', UserRole::TABLE),
                '=',
                sprintf('%s.id', Role::TABLE)
            )
            ->where(
                sprintf('%s.code', Role::TABLE),
                Role::CODE_STUDENT
            );
        if (!empty($filter['without_igw'])) {
            return $query->whereNotExists(function (QueryBuilder $query) {
                $query
                    ->from(IGW::TABLE)
                    ->whereColumn(sprintf('%s.id', User::TABLE), sprintf('%s.student_id', IGW::TABLE));
            });
        }
        if (isset($filter['author_id'])) {
            $query->where(sprintf('%s.id', User::TABLE), $filter['author_id']);
        }
        if (isset($filter['direction_id'])) {
            $query->whereHas('igw.version', function ($query) use ($filter) {
                $query
                    ->whereIn('direction_id', $filter['direction_id']);
            });
        }

        if (isset($filter['type']) && in_array($filter['type'], IGW::getTypes(), true)) {
            $query->whereHas('igw', function ($query) use ($filter) {
                $query
                    ->where('type', $filter['type']);
            });
        }

        if (isset($filter['stage_type'])) {
            $query->whereHas('igw.stage', function ($query) use ($filter) {
                $query
                    ->whereIn('type', $filter['stage_type']);
            });
        }

        if (isset($filter['status']) && !empty($statuses = $this->getIgwVersionStatusesByCodes($filter['status']))) {
            $query->whereHas('igw.version', function ($query) use ($statuses) {
                $query->whereIn('status', $statuses);
            });
        }

        if (isset($filter['evaluating_expert_id'])) {
            $query
                ->join(IGW::TABLE, sprintf('%s.student_id', IGW::TABLE), sprintf('%s.id', User::TABLE));
            $this->filterByEvaluatingExpert($query, $filter);
        }

        $query
            ->orderBy(sprintf('%s.lastname', User::TABLE))
            ->orderBy(sprintf('%s.firstname', User::TABLE))
            ->orderBy(sprintf('%s.middlename', User::TABLE));

        return $query;
    }

    /**
     * @param EloquentBuilder $query
     * @param $filter
     */
    public function filterByEvaluatingExpert(EloquentBuilder $query, $filter): void
    {
        $query
            ->joinSub(function (QueryBuilder $query) use ($filter) {
                $query
                    ->select([
                        'last_iec.igw_id',
                        'iec.expert_id'
                    ])
                    ->from(sprintf('%s as iec', IGWExpertComment::TABLE))
                    ->joinSub(function (QueryBuilder $query) {
                        $query
                            ->select([DB::raw('max(iec2.id) as id'), 'ivn.igw_id'])
                            ->from(sprintf('%s as iec2', IGWExpertComment::TABLE))
                            ->join(sprintf('%s as ivn', IGWVersion::TABLE), 'iec2.igw_version_id', 'ivn.id')
                            ->groupBy('ivn.igw_id');
                    }, 'last_iec', 'iec.id', 'last_iec.id')
                    ->where('iec.expert_id', $filter['evaluating_expert_id']);
            }, 'experts_list', sprintf('%s.id', IGW::TABLE), 'experts_list.igw_id');
    }
}

