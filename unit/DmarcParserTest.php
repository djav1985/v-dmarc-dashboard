<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

$autoloadPath = __DIR__ . '/../root/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require $autoloadPath;
} else {
    require __DIR__ . '/../root/app/Utilities/DmarcParser.php';
}

require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Utilities\DmarcParser;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$failures = 0;

$fixtureDir = __DIR__ . '/fixtures/dmarc';
$forensicXml = file_get_contents($fixtureDir . '/forensic-sample.xml');
$aggregateXml = file_get_contents($fixtureDir . '/aggregate-sample.xml');

assertTrue($forensicXml !== false, 'Forensic fixture should be readable.', $failures);
assertTrue($aggregateXml !== false, 'Aggregate fixture should be readable.', $failures);

if ($forensicXml !== false) {
    $forensicReport = DmarcParser::parseForensicReport($forensicXml);

    assertEquals('forensic.example', $forensicReport['domain'], 'Forensic parser should extract domain.', $failures);
    assertEquals(1700007200, $forensicReport['arrival_date'], 'Forensic parser should capture arrival date.', $failures);
    assertEquals('203.0.113.9', $forensicReport['source_ip'], 'Forensic parser should capture source IP.', $failures);
    assertEquals(
        'spf=fail smtp.mailfrom=forensic.example; dkim=fail header.d=forensic.example',
        $forensicReport['authentication_results'],
        'Forensic parser should include authentication results.',
        $failures
    );
    assertEquals('envelope-123', $forensicReport['original_envelope_id'], 'Forensic parser should capture envelope id.', $failures);
    assertEquals('forensic.example', $forensicReport['dkim_domain'], 'Forensic parser should capture DKIM domain.', $failures);
    assertEquals('selector1', $forensicReport['dkim_selector'], 'Forensic parser should capture DKIM selector.', $failures);
    assertEquals('fail', $forensicReport['dkim_result'], 'Forensic parser should capture DKIM result.', $failures);
    assertEquals('forensic.example', $forensicReport['spf_domain'], 'Forensic parser should capture SPF domain.', $failures);
    assertEquals('fail', $forensicReport['spf_result'], 'Forensic parser should capture SPF result.', $failures);
    assertEquals('Raw MIME message', $forensicReport['raw_message'], 'Forensic parser should capture the raw message.', $failures);

    $isoPayload = <<<XML
<feedback>
    <policy_published>
        <domain>iso.example</domain>
    </policy_published>
    <record>
        <row>
            <source_ip>198.51.100.200</source_ip>
        </row>
    </record>
    <event_time>2024-03-30T12:34:56Z</event_time>
</feedback>
XML;

    $isoReport = DmarcParser::parseForensicReport($isoPayload);
    assertEquals('iso.example', $isoReport['domain'], 'ISO payload should resolve the domain.', $failures);
    assertEquals('198.51.100.200', $isoReport['source_ip'], 'ISO payload should resolve the source IP.', $failures);
    $expectedIsoTimestamp = strtotime('2024-03-30T12:34:56Z');
    assertEquals($expectedIsoTimestamp, $isoReport['arrival_date'], 'ISO event_time should convert via strtotime.', $failures);
}

if ($aggregateXml !== false) {
    $gzipPath = tempnam(sys_get_temp_dir(), 'dmarc_gz_');
    $zipPath = tempnam(sys_get_temp_dir(), 'dmarc_zip_');

    assertTrue(is_string($gzipPath), 'Gzip temp file should be created.', $failures);
    assertTrue(is_string($zipPath), 'Zip temp file should be created.', $failures);

    if (is_string($gzipPath)) {
        file_put_contents($gzipPath, gzencode($aggregateXml));
        $gzipReport = DmarcParser::parseCompressedReport($gzipPath);
        assertEquals('aggregate.example', $gzipReport['policy_published_domain'], 'Gzip parser should read policy domain.', $failures);
        assertCountEquals(1, $gzipReport['records'], 'Gzip parser should return one record.', $failures);
        assertEquals('198.51.100.23', $gzipReport['records'][0]['source_ip'], 'Gzip parser should decode record source IP.', $failures);
        @unlink($gzipPath);
    }

    if (is_string($zipPath)) {
        $zip = new ZipArchive();
        $openMode = ZipArchive::OVERWRITE;
        if ($zip->open($zipPath, $openMode) !== true) {
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        }
        $zip->addFromString('report.xml', $aggregateXml);
        $zip->close();

        $zipReport = DmarcParser::parseCompressedReport($zipPath);
        assertEquals('aggregate.example', $zipReport['policy_published_domain'], 'Zip parser should read policy domain.', $failures);
        assertCountEquals(1, $zipReport['records'], 'Zip parser should return one record.', $failures);
        assertEquals('198.51.100.23', $zipReport['records'][0]['source_ip'], 'Zip parser should decode record source IP.', $failures);
        @unlink($zipPath);
    }
}

echo 'Dmarc parser tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
