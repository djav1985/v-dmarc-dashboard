<?php

namespace App\Utilities;

use ZipArchive;
use Exception;
use SimpleXMLElement;

class DmarcParser
{
    /**
     * Parse DMARC aggregate report from XML.
     *
     * @param string $xmlContent
     * @return array Parsed report data
     */
    public static function parseAggregateReport(string $xmlContent): array
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);

            $reportData = [
                'org_name' => (string) $xml->report_metadata->org_name,
                'email' => (string) $xml->report_metadata->email,
                'extra_contact_info' => (string) $xml->report_metadata->extra_contact_info ?: null,
                'report_id' => (string) $xml->report_metadata->report_id,
                'date_range_begin' => (int) $xml->report_metadata->date_range->begin,
                'date_range_end' => (int) $xml->report_metadata->date_range->end,
                'policy_published_domain' => (string) $xml->policy_published->domain,
                'raw_xml' => $xmlContent,
                'records' => []
            ];

            // Parse individual records
            foreach ($xml->record as $record) {
                $recordData = [
                    'source_ip' => (string) $record->row->source_ip,
                    'count' => (int) $record->row->count,
                    'disposition' => (string) $record->row->policy_evaluated->disposition,
                    'dkim_result' => (string) $record->row->policy_evaluated->dkim ?: null,
                    'spf_result' => (string) $record->row->policy_evaluated->spf ?: null,
                ];

                // Add identifiers if present
                if (isset($record->identifiers)) {
                    $recordData['header_from'] = (string) $record->identifiers->header_from ?: null;
                    $recordData['envelope_from'] = (string) $record->identifiers->envelope_from ?: null;
                    $recordData['envelope_to'] = (string) $record->identifiers->envelope_to ?: null;
                }

                $reportData['records'][] = $recordData;
            }

            return $reportData;
        } catch (Exception $e) {
            throw new Exception("Failed to parse DMARC aggregate report: " . $e->getMessage());
        }
    }

    /**
     * Extract and parse compressed DMARC report files.
     *
     * @param string $filePath Path to the compressed file
     * @return array Parsed report data
     */
    public static function parseCompressedReport(string $filePath): array
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch (strtolower($fileExtension)) {
            case 'gz':
                return self::parseGzipReport($filePath);
            case 'zip':
                return self::parseZipReport($filePath);
            case 'xml':
                $xmlContent = file_get_contents($filePath);
                return self::parseAggregateReport($xmlContent);
            default:
                throw new Exception("Unsupported file format: $fileExtension");
        }
    }

    /**
     * Parse GZIP compressed DMARC report.
     *
     * @param string $filePath
     * @return array
     */
    private static function parseGzipReport(string $filePath): array
    {
        $xmlContent = gzdecode(file_get_contents($filePath));
        if ($xmlContent === false) {
            throw new Exception("Failed to decompress GZIP file: $filePath");
        }

        return self::parseAggregateReport($xmlContent);
    }

    /**
     * Parse ZIP compressed DMARC report.
     *
     * @param string $filePath
     * @return array
     */
    private static function parseZipReport(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Failed to open ZIP file: $filePath");
        }

        // Look for XML files in the ZIP
        $xmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'xml') {
                $xmlContent = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        if ($xmlContent === null) {
            throw new Exception("No XML file found in ZIP: $filePath");
        }

        return self::parseAggregateReport($xmlContent);
    }

    /**
     * Validate XML structure for DMARC aggregate reports.
     *
     * @param string $xmlContent
     * @return bool
     */
    public static function isValidDmarcXml(string $xmlContent): bool
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);

            // Check required elements
            $requiredElements = [
                'report_metadata',
                'report_metadata/org_name',
                'report_metadata/report_id',
                'report_metadata/date_range/begin',
                'report_metadata/date_range/end',
                'policy_published',
                'record'
            ];

            foreach ($requiredElements as $element) {
                if (!$xml->xpath("//$element")) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
