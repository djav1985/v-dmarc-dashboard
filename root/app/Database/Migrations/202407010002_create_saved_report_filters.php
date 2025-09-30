<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS saved_report_filters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            name TEXT NOT NULL,
            filters TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, name)
        )",
        "CREATE INDEX IF NOT EXISTS idx_saved_filters_user ON saved_report_filters(user_id)"
    ],
    'down' => [
        'DROP TABLE IF EXISTS saved_report_filters'
    ]
];
