<?php declare(strict_types=1);

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
    description: 'A command that imports events from fileadmin/import/Calendarize.xlsx',
)]

final class ImportEventsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Import fileadmin/import/Calendarize.xlsx to Calendarize')
            ->setHelp('This command imports events from an Excel file into Calendarize.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        require_once dirname(__DIR__, 5) . '/vendor/autoload.php';

        $output->writeln('');
        $output->writeln('<fg=yellow>Start to import Excel file</>');
        $output->writeln('<fg=yellow>--------------------------</>');
        $output->writeln('');

        $publicPath = Environment::getPublicPath();
        $filePath = $publicPath . '/fileadmin/import/Calendarize.xlsx';

        if (!is_readable($filePath)) {
            $output->writeln("<error>File not found: " . $filePath . "</error>");
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
            $output->writeln("<info>Import complete: {$count} events created.</info>");
            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln("<error>Error: {$e->getMessage()} </error>");
            exit;
        }
    }
}
