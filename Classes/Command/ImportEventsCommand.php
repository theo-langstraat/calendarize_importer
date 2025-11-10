<?php

namespace Theolangstraat\CalendarizeImporter\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Core\Environment;
use Theolangstraat\CalendarizeImporter\Service\EventImportService;

#[AsCommand(
    name: 'calendarize:importevents',
    description: 'A command that imports events from fileadmin/import/CalendarHG.xlsx',
)]

final class ImportEventsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Importeer preekrooster vanuit Excel naar Calendarize')
            ->setHelp('Dit commando importeert events uit een Excel-bestand naar Calendarize.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        require_once dirname(__DIR__, 5) . '/vendor/autoload.php';

        $output->writeln('');
        $output->writeln('<fg=yellow>Start to import Excel file</>');
        $output->writeln('<fg=yellow>--------------------------</>');
        $output->writeln('');

        //$filePath = ExtensionManagementUtility::extPath('dailyverses') . 'fileadmin/import/CalendarHG.xlsx';
        //$filePath = '/var/www/html/sandbox/fileadmin/import/CalendarHG.xlsx';
        $publicPath = Environment::getPublicPath();
        $filePath = $publicPath . '/fileadmin/import/CalendarHG.xlsx';

        if (!is_readable($filePath)) {
            $output->writeln("<error>Bestand niet gevonden: " . $filePath . "</error>");
            return Command::FAILURE;
        }

        $inputFileName = $filePath;
        $sheetName = 'Events';
        $tableName = 'TabelEvents';

        try {
            $importService = GeneralUtility::makeInstance(EventImportService::class);
            $tableEvents = $importService->parseExcelTable($inputFileName, $sheetName, $tableName);

            $count = $importService->importEvents($tableEvents, $output);

            $output->writeln('');
            $output->writeln("<info>Import voltooid: {$count} events aangemaakt.</info>");
            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln("<error>Fout: {$e->getMessage()} </error>");
            exit;
        }
    }
}
