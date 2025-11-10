<?php

namespace Theolangstraat\CalendarizeImporter\Service;

use Symfony\Component\Console\Helper\ProgressBar;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EventImportService
{

    public function parseExcelTable(string $filePath, string $sheetName, string $tableName): array
    {
        $spreadsheet = IOFactory::load($filePath);

        if (!in_array($sheetName, $spreadsheet->getSheetNames())) {
            throw new \RuntimeException("Sheet '$sheetName' bestaat niet.");
        }

        $sheet = $spreadsheet->getSheetByName($sheetName);
        $table = $sheet->getTableByName($tableName);

        if ($table === null) {
            throw new \RuntimeException("Tabel '$tableName' niet gevonden op werkblad '$sheetName'.");
        }

        [$startCell, $endCell] = explode(':', $table->getRange());
        $startCol = preg_replace('/[0-9]/', '', $startCell);
        $endCol = preg_replace('/[0-9]/', '', $endCell);
        $startRow = (int) preg_replace('/[A-Z]/', '', $startCell);
        $endRow = (int) preg_replace('/[A-Z]/', '', $endCell);

        // Kolomnamen ophalen en normaliseren
        $columns = [];
        foreach (range($startCol, $endCol) as $col) {
            $key = $sheet->getCell($col . $startRow)->getValue();
            $columns[] = strtolower(trim(str_replace(' ', '_', $key)));
        }

        $events = [];
        for ($row = $startRow + 1; $row <= $endRow; $row++) {
            $rowData = [];

            foreach (range($startCol, $endCol) as $index => $col) {
                $key = $columns[$index];
                $value = $sheet->getCell($col . $row)->getCalculatedValue();
                $rowData[$key] = $value;
            }

            // Datumconversie
            if (isset($rowData['start_date']) && is_numeric($rowData['start_date'])) {
                $rowData['start_date'] = Date::excelToDateTimeObject($rowData['start_date'])->format('d-m-Y');
            } else {
                $rowData['start_date'] = '';
            }

            // Mapping naar verwachte velden
            $expectedFields = [
                'external_uid', 'start_date', 'start_time', 'title', 'abstract', 'description',
                'location', 'categories', 'cat_id', 'pid', 'fe_groups', 'fe_group'
            ];
            
            $event = [];
            foreach ($expectedFields as $field) {
                $event[$field] = $rowData[$field] ?? '';
            }

            $events[] = $event;
        }

        return [
            'columns' => $columns,
            'events' => $events,
            'row_count' => $endRow - $startRow,
            'start_row' => $startRow,
            'end_row' => $endRow,
        ];
    }

    public function importEvents($tableEvents, $output): int
    {
        $count = 0;
        $progressBar = new ProgressBar($output, $tableEvents['row_count']);
        $progressBar->setFormat('very_verbose');
        $progressBar->start();

        $eventCreator = GeneralUtility::makeInstance(EventDataHandlerService::class);

        foreach ($tableEvents['events'] as $event) {
            
            $eventCreator->createEvent([
                'pid' => $event['pid'],
                'title' => $event['title'],
                'abstract' => $event['abstract'],
                'description' => $event['description'],
                'location' => $event['location'],
                'start_date' => $event['start_date'],
                'end_date' => $event['start_date'],
                'start_time' => $event['start_time'],
                'end_time' => $event['start_time'],
                'external_uid' => 'excel_' . $event['external_uid'],
                'cat_id' => $event['cat_id'], 
                'fe_group' => $event['fe_group'],
            ]);

            $count++;
            $progressBar->advance(); // Advance the progress bar by one step

        }

        // $progressBar->finish(); // geeft deprecated foutmelding?? Zorgt dat 100% verschijnt

        return $count;
    }

}

