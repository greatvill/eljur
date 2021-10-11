public function getStudentsWithIgw(int $itemsPerPage, array $filter = [])
    {
        $result = new IgwListResult($itemsPerPage);
        $paginator = $this
            ->repository
            ->getStudentsWithIgw($filter)
            ->paginate($itemsPerPage);
        /**
         * @var Collection $collection
         */
        $collection = $paginator->getCollection();
        if ($paginator->total() === 0) {
            return $result;
        }
        /**
         * @var EloquentCollection $igwCollection
         */
        $igwCollection = $collection->pluck('igw')->filter();
        $igwNames = $this->getNameForIgws($igwCollection);
        $collection = $collection->map(static function (User $student) use ($igwNames) {
            $igw = $student->igw;
            $data = [];
            if ($igw) {
                $data = [
                    'id' => $igw->id,
                    'name' => $igwNames[$igw->id] ?? '',
                    'type' => $igw->type,
                    'direction_id' => $igw->version->direction_id,
                    'created_at' => $igw->created_at,
                    'published_at' => $igw->version->published_at ? $igw->version->published_at->toISOString() : '',
                    'stage_type' => $igw->stage->type,
                    'adm_year' => $igw->admission_year,
                    'version' => $igw->version->version,
                    'status' => IGWVersion::getStatusCodeByStatus($igw->version->status)
                ];
                if ($igw->isDeleted()) {
                    $data['status'] = 'deleted';
                }
            }

            $data['author'] = [
                'id' => $student->id,
                'name' => $student->getFio(),
                'parallel' => $student->parallel,
                'study_group' => $student->study_group,
                'email' => $student->email,
                'eljur_id' => $student->eljur_id,
                'disabled' => $student->dropout,
            ];

            return $data;
        });

        $result
            ->setTotal($paginator->total())
            ->setItems($collection->toArray());

        return $result;
    }
}

