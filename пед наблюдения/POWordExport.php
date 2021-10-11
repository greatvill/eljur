<?php

namespace App\Services\Word;

use App\Facades\POFacade;
use App\Models\EducationDirections;
use App\Services\POExportTraits\FilenameGenerable;
use App\User;
use Carbon\Carbon;

class POWordExport
{
    use FilenameGenerable;
    /**
     * @var POWord
     */
    private $POWord;

    protected $format = 'docx';
    /**
     * @var POFacade
     */
    private $facade;

    public function __construct(POFacade $facade, POWord $POWord)
    {
        $this->facade = $facade;
        $this->POWord = $POWord;
    }

    public function exportStudent(User $student): void
    {
        $pedObservations = $this->facade->getByStudent($student);
        $this->POWord->export($pedObservations, $this->getFilenameStudent($student));

    }

    public function exportByCurator(User $curator): void
    {
        $pedObservations = $this->facade->getByCurator($curator);
        $pedObservations = $pedObservations->sortBy(function ($item) {
            return $item->student_lastname .
                $item->student_firstname .
                $item->student_middlename;
        });
        $this->POWord->export($pedObservations, $this->getFilenameGroup($curator));

    }
}
