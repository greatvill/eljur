<?php

namespace App\Services\Word;

use App\Models\PedObservation;
use App\Models\PedObservationFile;
use App\System\Storage\StorageManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;

class POWord
{
    /**
     * @var PhpWord
     */
    private $phpWord;
    /**
     * @var StorageManager
     */
    private $storageManager;
    /**
     * @var Section
     */
    private $section;

    public function __construct(StorageManager $storageManager)
    {
        $this->phpWord = new PhpWord();
        $this->section = $this->phpWord->addSection();
        $this->storageManager = $storageManager;
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        //Стили по-умолчания
        $phpWord->setDefaultFontName('DejaVu Sans');
        $phpWord->setDefaultFontSize(12);
        //Стили для ссылок
        $phpWord->addFontStyle('Link', ['color' => '0000FF', 'underline' => 'single', 'size' => 11]);
        //Задаем стили для заголовка
        $phpWord->addTitleStyle(null, ['size' => 22, 'bold' => true]);
    }

    public function addContent(Collection $collection): void
    {
        $oldStudentId = null;
        $table = $this->section->addTable([
            'cellMarginTop' => 300,
            'cellMarginRight' => 300,
            'cellMarginBottom' => 300,
            'cellMarginLeft' => 300,
        ]);
        /**
         * @var PedObservation $item
         */
        foreach ($collection as $item) {

            if ($oldStudentId !== $item->student_id) {
                $oldStudentId = $item->student_id;
                //Добавили заголовок
                $this->section->addTitle($item->student->getFio(), 0);
                $table = $this->section->addTable([
                    'cellMarginTop' => 300,
                    'cellMarginRight' => 300,
                    'cellMarginBottom' => 300,
                    'cellMarginLeft' => 300,
                ]);
                $this->section->addPageBreak();
            }

            $cell = $table->addRow()->addCell(null, ['borderBottomSize' => 6]);
            if ($item->is_favorite) {
                $cell->addText('★', null, ['align' => 'right']);
            } else {
                $cell->addText('☆', null, ['align' => 'right']);
            }
            $this->addText($item->text, $cell);
            if ($item->pedObservationFiles()->exists()) {
                $cell->addText('Прикрепленные файлы', ['bold' => true]);
                /**
                 * @var PedObservationFile $file
                 */
                foreach ($item->pedObservationFiles as $file) {
                    $link = sprintf(
                        '%s%s?%s',
                        $this->storageManager->getDownloadLink(),
                        $file->fid,
                        urldecode(http_build_query([
                            'filename' => $file->name,
                            'domain' => env('ELJUR_VENDOR'),
                        ]))
                    );
                    $cell->addLink($link, $file->name, 'Link');
                }
            }

            if ($item->note) {
                $cell->addText('Заметка куратора', ['bold' => true]);
                $this->addText($item->note, $cell, ['size' => 10], ['borderSize' => 4, 'borderColor' => '808080']);
            }
            $style = ['color' => '808080', 'size' => 10];
            $fioAuthor = implode(' ', [
                $item->author_lastname,
                $item->author_firstname,
                $item->author_middlename,
            ]);
            $cell->addText($fioAuthor, $style);
            $cell->addText(
                Carbon::createFromTimestamp($item->updated_at)->format('d.m.Y в H:i:s'),
                $style);
        }
    }

    public function export(Collection $collection, string $filename): void
    {
        $this->addContent($collection);
        $this->phpWord->save($filename, 'Word2007', true);
    }

    private function addText(string $text, Cell $cell, $fStyle = null, $pStyle = null): void
    {
        $textLines = explode("\n", $text);

        $textRun = $cell->addTextRun();
        $textRun->addText(array_shift($textLines), $fStyle, $pStyle);
        foreach ($textLines as $line) {
            $textRun->addTextBreak();
            $textRun->addText($line, $fStyle, $pStyle);
        }
    }
}
