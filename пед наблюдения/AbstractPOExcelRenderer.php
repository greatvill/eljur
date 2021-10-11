<?php

namespace App\Services\Excel\PO;

use App\Models\PedObservation;
use App\Services\POExportTraits\FilenameGenerable;
use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

abstract class AbstractPOExcelRenderer extends \App\Services\Excel\ExcelRenderer
{
    use FilenameGenerable;

    /**
     * @var int Максимальное количество символов для автоматического размера столбца
     */
    protected $limitChars = 50;

    /**
     * @var int Размер ячейки в пиксилях, когда превышен $limitChars
     */
    protected $pValueLimit = 500;

    /**
     * @var array Хранит столбцы с превышенным $pValueLimit
     */
    protected $columnsOver = [];

    protected $currentRow;

    public function __construct()
    {
        parent::__construct();
        //Для установки переносов
        Cell::setValueBinder(new AdvancedValueBinder());
    }

    protected function applyHeaderStyles(): void
    {
        $this->list
            ->getStyleByColumnAndRow(1, 1, count($this->columns), 1)
            ->applyFromArray($this->getHeaderStyleArray());
    }

    protected function addRow(array $attrs): void
    {
        foreach ($this->columns as $key => $title) {
            if (isset($attrs[$key])) {
                $this->list->setCellValueByColumnAndRow($this->currentColumn, $this->currentRow, $attrs[$key]);
                $columnDimensions = $this->list->getColumnDimensionByColumn($this->currentColumn);
                if (!in_array($this->currentColumn, $this->columnsOver, true)) {
                    if (mb_strlen($attrs[$key]) > $this->limitChars) {
                        $this->columnsOver[] = $this->currentColumn;
                        $columnDimensions->setAutoSize(false);
                        $columnDimensions->setWidth($this->pValueLimit);
                    } else {
                        $columnDimensions->setAutoSize(true);
                    }
                }
            }
            $this->currentColumn++;
        }
        $this->currentRow++;
    }

    abstract protected function makeAttrs(PedObservation $item): array;

    protected function getCountFiles(PedObservation $item): ?int
    {
        return $item->pedObservationFiles()->count() > 0 ? $item->pedObservationFiles()->count() : null;
    }

    protected function isViewed(PedObservation $item): ?string
    {
        return $item->is_viewed ? 'прочитано' : null;
    }

    protected function isFavorite(PedObservation $item): ?string
    {
        return $item->is_favorite ? 'важное' : null;
    }
}
