<?php

namespace App\Services;

use App\Services\Excel\PO\POGroupToExcelService;
use App\Services\Word\POWordExport;
use App\User;
use PhpOffice\PhpSpreadsheet\Writer\Exception;

class POGroupExport
{
    /**
     * @throws Exception
     */
    public function export(User $curator, string $format = 'xlsx'): void
    {
        if ($format === 'xlsx') {
            /**
             * @var POGroupToExcelService $service
             */
            $service = app(POGroupToExcelService::class);
            $service->setCurator($curator);
            $service->export();
        }
        if ($format === 'docx') {
            app(POWordExport::class)->exportByCurator($curator);
        }
    }
}
