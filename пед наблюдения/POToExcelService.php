<?php

namespace App\Services\Excel\PO;

use App\Facades\POFacade;
use App\Models\EducationDirections;
use App\Models\PedObservation;
use App\Services\UserService;
use App\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;

class POToExcelService extends AbstractPOExcelRenderer
{
    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var Worksheet
     */
    protected $list;

    /**
     * @var array
     */
    protected $columns = [
        'updated_at' => 'Дата записи',
        'author' => 'Автор записи',
        'student' => 'ФИО лицеиста',
        'group_name' => 'Название кураторской группы',
        'curator' => 'ФИО куратора',
        'is_files' => 'Файлы',
        'is_viewed' => 'Прочитано',
        'is_favorite' => 'Важное',
        'is_note' => 'Заметка'
    ];

    /**
     * @var POFacade
     */
    private $facade;

    protected $currentRow = 2;

    public function __construct(POFacade $facade)
    {
        parent::__construct();
        $this->facade = $facade;
    }

    /**
     * @throws Exception
     */
    public function export(): void
    {
        $pedObservations = $this->facade->getAll();
        /**
         * @var PedObservation $item
         */
        foreach ($pedObservations as $item) {
            $this->currentColumn = 1;
            $attrs = $this->makeAttrs($item);
            $this->addRow($attrs);
        }

        $this->render();
    }


    protected function makeAttrs(PedObservation $item): array
    {
        return [
            'updated_at' => Carbon::createFromTimestamp($item->updated_at)->toDateTimeString(),
            'author' => $item->author_lastname . ' ' . $item->author_firstname . ' ' . $item->author_middlename,
            'student' => $item->student_lastname . ' ' . $item->student_firstname . ' ' . $item->student_middlename,
            'group_name' => $item->student_group,
            'curator' => $item->curator_lastname . ' ' . $item->curator_firstname . ' ' . $item->curator_middlename,
            'is_files' => $item->pedObservationFiles()->exists() ? 'есть' : null,
            'is_viewed' => $this->isViewed($item),
            'is_favorite' => $this->isFavorite($item),
            'is_note' => $item->note ? 'есть' : null,
        ];
    }

    protected function getFileName(): string
    {
        return sprintf(
            __('po.export_all_filename'),
            Carbon::now()->toDateString()
        );
    }

    private function getDirectionAlias(PedObservation $item)
    {
        if (!$item->direction) {
            return preg_replace('/\d/', '', $item->student_group);
        }
        return $item->direction;
    }

    private function getDirectionName(PedObservation $item)
    {
        $directionAlias = $this->getDirectionAlias($item);
        return $directionAlias
            ? (EducationDirections::DIRECTION_MAP[$directionAlias] ?? $directionAlias)
            : null;
    }
}
