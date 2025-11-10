<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Calendarize Importer',
    'description' => 'Import events from Excel using calendarize',
    'category' => 'be',
    'state' => 'stable',
    'author' => 'Theo Langstraat',
    'author_email' => 'theo.langstraat@delta.nl',
    'author_company' => '',
    'version' => '13.4.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'calendarize' => '14.0.1',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];