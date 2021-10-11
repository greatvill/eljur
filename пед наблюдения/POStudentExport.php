<?php

namespace App\Services;

use App\Services\Excel\PO\POStudentToExcelService;
use App\Services\Word\POWordExport;
use App\User;
use PhpOffice\PhpSpreadsheet\Writer\Exception;

class POStudentExport
{
    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_DOCX = 'docx';

    /**
     * @throws Exception
     */
    public function export(User $student, string $format = self::FORMAT_XLSX): void
    {
        if ($format === self::FORMAT_XLSX) {
            /**
             * @var POStudentToExcelService $service
             */
            $service = app(POStudentToExcelService::class);
            $service->setStudent($student)->export();
        }
        if ($format === self::FORMAT_DOCX) {
            app(POWordExport::class)->exportStudent($student);
        }
    }
}
