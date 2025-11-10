<?php

namespace Theolangstraat\CalendarizeImporter\Service;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Core\Bootstrap;
use DateTime;

class EventDataHandlerService
{

    private const DEFAULT_EVENT_DURATION_SECONDS = 7200;

    private function timeStringToSeconds(string $timeString): int {
            $timeString = trim($timeString); // verwijdert spaties links en rechts
            $time = DateTime::createFromFormat('H.i', $timeString);
            if (!$time) {
                throw new \InvalidArgumentException("Ongeldige tijdnotatie: {$timeString}");
            }
            return ((int)$time->format('H') * 3600) + ((int)$time->format('i') * 60);
        }

    public function createEvent(array $eventData): void
    {
        Bootstrap::initializeBackendAuthentication();

        // Bepaal een stabiele import-id gebaseerd op jouw bron
        $importId = 'mijnbron:' . $eventData['external_uid'];

        // DB-connection
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_calendarize_domain_model_event');

        // Zoek bestaand event op import_id
        $qb = $connection->createQueryBuilder();
        $existingEventUid = $qb
            ->select('uid')
            ->from('tx_calendarize_domain_model_event')
            ->where($qb->expr()->eq('import_id', $qb->createNamedParameter($importId)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        // Zoek bestaande configuration op import_id
        $connectionConfig = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_calendarize_domain_model_configuration');
        $qb2 = $connectionConfig->createQueryBuilder();
        $existingConfigUid = $qb2
            ->select('uid')
            ->from('tx_calendarize_domain_model_configuration')
            ->where($qb2->expr()->eq('import_id', $qb2->createNamedParameter($importId)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        // Keys voor DataHandler: update (numeric uid) of create (NEW...)
        $eventKey = $existingEventUid ? (int)$existingEventUid : uniqid('NEW', true);

        $configKey = $existingConfigUid ? (int)$existingConfigUid : uniqid('NEW', true);


        if (empty($eventData['title']) || empty($eventData['start_date'])) {
            throw new \InvalidArgumentException("Verplichte velden ontbreken: title of start_date external_uid: {$importId}");
        }

        // Data voor DataHandler
        $data = [
            'tx_calendarize_domain_model_configuration' => [
                $configKey => [
                    'pid' => $eventData['pid'],
                    'type' => 'time',
                    'handling' => 'include',
                    'state' => 'default',
                    'start_date' => $eventData['start_date'],
                    'end_date' => $eventData['end_date'],
                    'start_time' => $this->timeStringToSeconds($eventData['start_time']),
                    'end_time' => $this->timeStringToSeconds($eventData['end_time']) + self::DEFAULT_EVENT_DURATION_SECONDS, // standaard 2 uur duur event
                    'import_id' => $importId,
                ],
            ],
            'tx_calendarize_domain_model_event' => [
                $eventKey => [
                    'pid' => $eventData['pid'],
                    'title' => $eventData['title'],
                    'abstract' => $eventData['abstract'],
                    'description' => $eventData['description'],
                    'location' => $eventData['location'],
                    // IRRE link: als configKey numeric is gebruik dat uid; anders geef NEW-key door
                    'calendarize' => $configKey,  // IRRE: tx_calendarize_domain_model_configuration
                    'import_id' => $importId,
                    'categories' => $eventData['cat_id'] . ', 7',
                    'fe_group' => $eventData['fe_group']
                ],
            ],
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException('Error: ' . implode(', ', $dataHandler->errorLog));
        }
    }
}
