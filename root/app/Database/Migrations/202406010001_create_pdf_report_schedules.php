<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS pdf_report_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            template_id INTEGER NOT NULL,
            title TEXT,
            frequency TEXT NOT NULL,
            recipients TEXT NOT NULL,
            domain_filter TEXT,
            group_filter INTEGER,
            parameters TEXT,
            enabled INTEGER DEFAULT 1,
            last_run_at DATETIME,
            next_run_at DATETIME,
            last_status TEXT,
            last_error TEXT,
            last_generation_id INTEGER,
            created_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (template_id) REFERENCES pdf_report_templates(id) ON DELETE CASCADE,
            FOREIGN KEY (group_filter) REFERENCES domain_groups(id) ON DELETE SET NULL,
            FOREIGN KEY (last_generation_id) REFERENCES pdf_report_generations(id) ON DELETE SET NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_pdf_report_schedules_next_run ON pdf_report_schedules(next_run_at)",
        "CREATE INDEX IF NOT EXISTS idx_pdf_report_schedules_status ON pdf_report_schedules(enabled, last_status)"
    ],
    'down' => [
        'DROP TABLE IF EXISTS pdf_report_schedules'
    ]
];
