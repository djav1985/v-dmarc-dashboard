<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use RuntimeException;

/**
 * Helper responsible for rendering PDF report HTML and exporting it with Dompdf.
 */
class PdfReportService
{
    /**
     * Render a PDF-ready HTML string using the shared template and report payload.
     */
    public static function renderHtml(array $reportData, string $title): string
    {
        if (empty($reportData)) {
            throw new InvalidArgumentException('Report data is required for PDF rendering.');
        }

        $report = $reportData;
        $reportTitle = $title;

        ob_start();
        include __DIR__ . '/../Views/pdf_report_document.php';
        return (string) ob_get_clean();
    }

    /**
     * Generate a PDF document from the provided report payload.
     *
     * @param array<string,mixed> $reportData
     * @param array<string,mixed> $options
     *
     * @return array{path:string,filename:string,size:int,relative_path:string}
     */
    public static function generatePdf(array $reportData, string $title, array $options = []): array
    {
        $html = self::renderHtml($reportData, $title);

        $storagePath = self::resolveStoragePath($options['output_directory'] ?? null);
        self::ensureDirectoryExists($storagePath);

        $filename = self::buildFilename($title, $options['prefix'] ?? 'dmarc_report', $options['timestamp'] ?? time());

        $dompdfOptions = new Options();
        $dompdfOptions->set('isRemoteEnabled', true);
        $dompdfOptions->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($options['paper'] ?? 'A4', $options['orientation'] ?? 'portrait');
        $dompdf->render();

        $binary = $dompdf->output();
        $targetPath = rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($targetPath, $binary) === false) {
            throw new RuntimeException('Unable to persist generated PDF to disk.');
        }

        $basePath = realpath($storagePath) ?: $storagePath;
        $relativePath = self::buildRelativePath($targetPath, $basePath);

        return [
            'path' => $targetPath,
            'filename' => $filename,
            'size' => strlen($binary),
            'relative_path' => $relativePath,
        ];
    }

    private static function resolveStoragePath(?string $override): string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        if (defined('PDF_REPORT_STORAGE_PATH')) {
            return (string) constant('PDF_REPORT_STORAGE_PATH');
        }

        return sys_get_temp_dir();
    }

    private static function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create PDF storage directory: ' . $path);
        }
    }

    private static function buildFilename(string $title, string $prefix, int $timestamp): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($title)) ?: 'report';
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'report';
        }

        return sprintf('%s-%s-%s.pdf', $prefix, date('Ymd-His', $timestamp), $slug);
    }

    private static function buildRelativePath(string $absolutePath, string $basePath): string
    {
        $normalizedBase = rtrim(str_replace(['\\', '//'], '/', $basePath), '/');
        $normalizedAbsolute = str_replace(['\\', '//'], '/', $absolutePath);

        if (str_starts_with($normalizedAbsolute, $normalizedBase)) {
            $relative = ltrim(substr($normalizedAbsolute, strlen($normalizedBase)), '/');
            return $relative === '' ? basename($absolutePath) : $relative;
        }

        return basename($absolutePath);
    }
}
