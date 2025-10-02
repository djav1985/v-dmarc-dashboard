<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

return [
    'up' => [
        "ALTER TABLE dmarc_aggregate_reports ADD COLUMN policy_adkim TEXT",
        "ALTER TABLE dmarc_aggregate_reports ADD COLUMN policy_aspf TEXT",
        "ALTER TABLE dmarc_aggregate_reports ADD COLUMN policy_p TEXT",
        "ALTER TABLE dmarc_aggregate_reports ADD COLUMN policy_sp TEXT",
        "ALTER TABLE dmarc_aggregate_reports ADD COLUMN policy_pct INTEGER",
        "ALTER TABLE dmarc_aggregate_reports ADD COLUMN policy_fo TEXT",
        "ALTER TABLE dmarc_aggregate_records ADD COLUMN policy_evaluated_reasons TEXT",
        "ALTER TABLE dmarc_aggregate_records ADD COLUMN policy_override_reasons TEXT",
        "ALTER TABLE dmarc_aggregate_records ADD COLUMN auth_results TEXT",
    ],
    'down' => [
        'ALTER TABLE dmarc_aggregate_reports DROP COLUMN policy_adkim',
        'ALTER TABLE dmarc_aggregate_reports DROP COLUMN policy_aspf',
        'ALTER TABLE dmarc_aggregate_reports DROP COLUMN policy_p',
        'ALTER TABLE dmarc_aggregate_reports DROP COLUMN policy_sp',
        'ALTER TABLE dmarc_aggregate_reports DROP COLUMN policy_pct',
        'ALTER TABLE dmarc_aggregate_reports DROP COLUMN policy_fo',
        'ALTER TABLE dmarc_aggregate_records DROP COLUMN policy_evaluated_reasons',
        'ALTER TABLE dmarc_aggregate_records DROP COLUMN policy_override_reasons',
        'ALTER TABLE dmarc_aggregate_records DROP COLUMN auth_results',
    ],
];
