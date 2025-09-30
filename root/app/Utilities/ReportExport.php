<?php

namespace App\Utilities;

class ReportExport
{
    /**
     * Build CSV output for the provided report rows.
     *
     * @param array<int, array<string, mixed>> $reports
     */
    public static function buildCsv(array $reports): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        $headers = self::getHeaderRow();
        fwrite($handle, implode(',', $headers) . PHP_EOL);

        foreach ($reports as $report) {
            fputcsv($handle, self::buildRow($report), ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Build a minimal XLSX workbook for the provided reports.
     *
     * @param array<int, array<string, mixed>> $reports
     */
    public static function buildXlsx(array $reports): string
    {
        $rows = [self::getHeaderRow()];
        foreach ($reports as $report) {
            $rows[] = self::buildRow($report);
        }

        $zip = new \ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tempFile === false) {
            return '';
        }

        if ($zip->open($tempFile, \ZipArchive::OVERWRITE | \ZipArchive::CREATE) !== true) {
            return '';
        }

        $zip->addFromString('[Content_Types].xml', self::buildContentTypesXml());
        $zip->addFromString('_rels/.rels', self::buildRelsXml());
        $zip->addFromString('xl/workbook.xml', self::buildWorkbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::buildWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', self::buildStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::buildSheetXml($rows));
        $zip->close();

        $binary = file_get_contents($tempFile);
        unlink($tempFile);

        return $binary !== false ? $binary : '';
    }

    /**
     * @return array<int, string>
     */
    private static function getHeaderRow(): array
    {
        return [
            'Domain',
            'Ownership Contact',
            'Enforcement Level',
            'Organization',
            'Reporter Email',
            'Report ID',
            'Range Start',
            'Range End',
            'Received At',
            'Total Records',
            'Total Volume',
            'Rejected Volume',
            'Quarantined Volume',
            'Passed Volume',
            'DKIM Pass Volume',
            'SPF Pass Volume',
            'Failure Volume',
        ];
    }

    /**
     * @return array<int, string|int>
     */
    private static function buildRow(array $report): array
    {
        $rangeStart = self::formatDate($report['date_range_begin'] ?? null);
        $rangeEnd = self::formatDate($report['date_range_end'] ?? null);
        $received = self::formatDateTime($report['received_at'] ?? null);

        return [
            (string) ($report['domain'] ?? ''),
            (string) ($report['ownership_contact'] ?? ''),
            (string) ($report['enforcement_level'] ?? ''),
            (string) ($report['org_name'] ?? ''),
            (string) ($report['email'] ?? ''),
            (string) ($report['report_id'] ?? ''),
            $rangeStart,
            $rangeEnd,
            $received,
            (int) ($report['total_records'] ?? 0),
            (int) ($report['total_volume'] ?? 0),
            (int) ($report['rejected_count'] ?? 0),
            (int) ($report['quarantined_count'] ?? 0),
            (int) ($report['passed_count'] ?? 0),
            (int) ($report['dkim_pass_count'] ?? 0),
            (int) ($report['spf_pass_count'] ?? 0),
            (int) ($report['failure_volume'] ?? 0),
        ];
    }

    private static function formatDate($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return date('Y-m-d', (int) $value);
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? date('Y-m-d', $timestamp) : (string) $value;
    }

    private static function formatDateTime($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : (string) $value;
    }

    private static function buildContentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private static function buildRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private static function buildWorkbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Reports" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML;
    }

    private static function buildWorkbookRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private static function buildStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1">
        <font>
            <sz val="11"/>
            <color theme="1"/>
            <name val="Calibri"/>
            <family val="2"/>
        </font>
    </fonts>
    <fills count="1">
        <fill>
            <patternFill patternType="none"/>
        </fill>
    </fills>
    <borders count="1">
        <border>
            <left/><right/><top/><bottom/><diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
    <dxfs count="0"/>
    <tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>
XML;
    }

    /**
     * @param array<int, array<int, string|int>> $rows
     */
    private static function buildSheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach ($cells as $cellIndex => $value) {
                $columnName = self::columnName($cellIndex + 1);
                $cellReference = $columnName . $rowNumber;
                $xml .= '<c r="' . $cellReference . '" t="inlineStr"><is><t>' . self::escapeXml((string) $value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = (int) ($index / 26);
        }

        return $name;
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
