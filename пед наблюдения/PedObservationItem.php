<?php

namespace App\Http\Resources\PO;

use App\Facades\POFacade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class PedObservation
 * @package App\Http\Resources\PO
 *
 * @mixin \App\Models\PedObservation
 */
class PedObservationItem extends JsonResource
{
    /**
     * @var POFacade
     */
    private $facade;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->facade = app(POFacade::class);
    }

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        if (!isset($this->actions)) {
            $this->setActions($this->facade->getActions($this->resource));
        }
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'author' => [
                'id' => (int)$this->author_id,
                'firstname' => $this->author_firstname,
                'lastname' => $this->author_lastname,
                'middlename' => $this->author_middlename,
            ],
            'curator' => [
                'id' => (int)$this->curator_id,
                'firstname' => $this->curator_firstname,
                'lastname' => $this->curator_lastname,
                'middlename' => $this->curator_middlename,
            ],
            'student' => [
                'id' => (int)$this->student_id,
                'firstname' => $this->student_firstname,
                'lastname' => $this->student_lastname,
                'middlename' => $this->student_middlename,
                'study_group' => $this->student_group,
            ],
            'is_viewed' => (bool)$this->is_viewed,
            'is_favorite' => (bool)$this->is_favorite,
            'text' => $this->text,
            'note' => $this->note,
            'attachments' => $this->pedObservationFiles->map->only(['fid', 'size', 'name']),
            'actions' => $this->actions
        ];
    }
}
