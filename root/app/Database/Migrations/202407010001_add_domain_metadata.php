<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

return [
    'up' => [
        "ALTER TABLE domains ADD COLUMN ownership_contact TEXT",
        "ALTER TABLE domains ADD COLUMN enforcement_level TEXT"
    ],
    'down' => [
        'ALTER TABLE domains DROP COLUMN ownership_contact',
        'ALTER TABLE domains DROP COLUMN enforcement_level'
    ]
];
