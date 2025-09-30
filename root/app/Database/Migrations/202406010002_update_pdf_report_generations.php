<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

return [
    'up' => [
        "ALTER TABLE pdf_report_generations ADD COLUMN file_path TEXT",
        "ALTER TABLE pdf_report_generations ADD COLUMN schedule_id INTEGER",
        "CREATE INDEX IF NOT EXISTS idx_pdf_report_generations_schedule ON pdf_report_generations(schedule_id)"
    ],
    'down' => [
        // SQLite does not support dropping columns without table rebuild; document manual rollback.
        "/* Manual rollback required: drop and recreate pdf_report_generations without file_path and schedule_id */"
    ]
];
