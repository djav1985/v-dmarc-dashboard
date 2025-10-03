<?php

namespace App\Models;

class PdfReportSchedulerHelper
{
    public static function buildInterval(array $parsed): \DateInterval
    {
        return match ($parsed['type']) {
            'daily' => new \DateInterval('P1D'),
            'weekly' => new \DateInterval('P7D'),
            'monthly' => new \DateInterval('P1M'),
            'custom' => new \DateInterval('P' . max(1, (int) ($parsed['custom_days'] ?? $parsed['range_days'])) . 'D'),
            default => new \DateInterval('P1D'),
        };
    }
}
