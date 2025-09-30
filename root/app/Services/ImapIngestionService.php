<?php

namespace App\Services;

use App\Utilities\DmarcParser;
use App\Models\DmarcReport;
use App\Core\ErrorManager;
use Exception;

class ImapIngestionService
{
    private $connection;
    private string $mailbox;

    /**
     * Initialize IMAP connection.
     */
    public function __construct()
    {
        $this->mailbox = $this->buildMailboxString();
    }

    /**
     * Build IMAP mailbox connection string.
     *
     * @return string
     */
    private function buildMailboxString(): string
    {
        $host = defined('IMAP_HOST') ? IMAP_HOST : 'localhost';
        $port = defined('IMAP_PORT') ? IMAP_PORT : 143;
        $ssl = defined('IMAP_SSL') ? IMAP_SSL : false;
        $mailbox = defined('IMAP_MAILBOX') ? IMAP_MAILBOX : 'INBOX';
        $validateCertConfig = defined('IMAP_VALIDATE_CERT') ? IMAP_VALIDATE_CERT : true;

        $sslEnabled = is_bool($ssl)
            ? $ssl
            : (filter_var($ssl, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $ssl);

        $flags = '/imap';
        if ($sslEnabled || (int) $port === 993) {
            $flags .= '/ssl';
        }

        $validateCert = is_bool($validateCertConfig)
            ? $validateCertConfig
            : (filter_var($validateCertConfig, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $validateCertConfig);

        if (!$validateCert) {
            $flags .= '/novalidate-cert';
        }

        return "{{$host}:{$port}{$flags}}{$mailbox}";
    }

    /**
     * Connect to IMAP server.
     *
     * @return bool
     */
    public function connect(): bool
    {
        try {
            $username = defined('IMAP_USERNAME') ? IMAP_USERNAME : '';
            $password = defined('IMAP_PASSWORD') ? IMAP_PASSWORD : '';

            if (empty($username) || empty($password)) {
                ErrorManager::getInstance()->log('IMAP credentials not configured', 'error');
                return false;
            }

            $this->connection = imap_open($this->mailbox, $username, $password);

            if (!$this->connection) {
                $error = 'IMAP connection failed: ' . imap_last_error();
                ErrorManager::getInstance()->log($error, 'error');
                return false;
            }

            return true;
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('IMAP connection error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Disconnect from IMAP server.
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
        }
    }

    /**
     * Process DMARC reports from inbox.
     *
     * @return array Processing results
     */
    public function processReports(): array
    {
        $results = [
            'processed' => 0,
            'errors' => 0,
            'messages' => []
        ];

        if (!$this->connect()) {
            $results['messages'][] = 'Failed to connect to IMAP server';
            return $results;
        }

        try {
            // Search for DMARC report emails
            $emails = imap_search($this->connection, 'SUBJECT "Report Domain:"', SE_UID) ?: [];
            $emails = array_merge($emails, imap_search($this->connection, 'SUBJECT "DMARC"', SE_UID) ?: []);

            foreach (array_unique($emails) as $uid) {
                try {
                    if ($this->processEmail($uid)) {
                        $results['processed']++;
                        // Mark email as read/processed
                        imap_delete($this->connection, $uid, FT_UID);
                        imap_setflag_full($this->connection, $uid, '\\Seen', ST_UID);
                    } else {
                        $results['errors']++;
                    }
                } catch (Exception $e) {
                    $results['errors']++;
                    $results['messages'][] = "Error processing email UID $uid: " . $e->getMessage();
                }
            }

            imap_expunge($this->connection);

            $results['messages'][] = "Processed {$results['processed']} reports with {$results['errors']} errors; flagged messages were expunged";
        } catch (Exception $e) {
            $results['messages'][] = 'Error during email processing: ' . $e->getMessage();
        } finally {
            $this->disconnect();
        }

        return $results;
    }

    /**
     * Process individual email.
     *
     * @param string $uid
     * @return bool
     */
    private function processEmail(string $uid): bool
    {
        try {
            $structure = imap_fetchstructure($this->connection, $uid, FT_UID);

            if ($structure->type == 1) { // Multipart message
                return $this->processMultipartEmail($uid, $structure);
            } else {
                return $this->processSinglePartEmail($uid);
            }
        } catch (Exception $e) {
            ErrorManager::getInstance()->log("Failed to process email UID $uid: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Process multipart email (with attachments).
     *
     * @param string $uid
     * @param object $structure
     * @return bool
     */
    private function processMultipartEmail(string $uid, object $structure): bool
    {
        if (!property_exists($structure, 'parts') || !is_array($structure->parts) || count($structure->parts) === 0) {
            return false;
        }

        $attachments = [];
        $partsCount = count($structure->parts);

        for ($index = 0; $index < $partsCount; $index++) {
            $part = $structure->parts[$index] ?? null;
            if (!is_object($part)) {
                continue;
            }

            if (!isset($part->disposition) || strtoupper((string) $part->disposition) !== 'ATTACHMENT') {
                continue;
            }

            $attachment = imap_fetchbody($this->connection, $uid, $index + 1, FT_UID);

            if ($part->encoding == 3) { // Base64
                $attachment = base64_decode($attachment);
            } elseif ($part->encoding == 4) { // Quoted-printable
                $attachment = quoted_printable_decode($attachment);
            }

            $attachments[] = $attachment;
        }

        $processed = false;

        foreach ($attachments as $index => $attachment) {
            try {
                if ($this->processAttachment($attachment)) {
                    $processed = true;
                    continue;
                }

                ErrorManager::getInstance()->log(
                    sprintf('Failed to process DMARC attachment %d for UID %s.', $index + 1, $uid),
                    'warning'
                );
            } catch (Exception $exception) {
                ErrorManager::getInstance()->log(
                    sprintf('Error processing DMARC attachment %d for UID %s: %s', $index + 1, $uid, $exception->getMessage()),
                    'error'
                );
            }
        }

        return $processed;
    }

    /**
     * Process single part email.
     *
     * @param string $uid
     * @return bool
     */
    private function processSinglePartEmail(string $uid): bool
    {
        $body = imap_fetchbody($this->connection, $uid, 1, FT_UID);

        if ($body === false || trim($body) === '') {
            ErrorManager::getInstance()->log('Single-part email contained no body content.', 'warning');
            return false;
        }

        try {
            // Try to parse as DMARC forensic report
            $forensicData = DmarcParser::parseForensicReport($body);
            $reportId = DmarcReport::storeForensicReport($forensicData);
            return $reportId > 0;
        } catch (Exception $e) {
            ErrorManager::getInstance()->log("Failed to parse forensic report: " . $e->getMessage(), 'warning');
            return false;
        }
    }

    /**
     * Process email attachment.
     *
     * @param string $attachment
     * @return bool
     */
    protected function processAttachment(string $attachment): bool
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'dmarc_');

        if ($tempFile === false) {
            ErrorManager::getInstance()->log('Failed to create temporary file for DMARC attachment.', 'warning');
            return false;
        }

        try {
            if (file_put_contents($tempFile, $attachment) === false) {
                throw new Exception('Failed to write attachment to temporary file.');
            }

            // Try to parse as DMARC aggregate report
            $reportData = DmarcParser::parseCompressedReport($tempFile);

            // Store the report
            $reportId = DmarcReport::storeAggregateReport($reportData);

            if (!empty($reportData['records'])) {
                DmarcReport::storeAggregateRecords($reportId, $reportData['records']);
            }

            return true;
        } catch (Exception $e) {
            ErrorManager::getInstance()->log(
                "Failed to process attachment at $tempFile: " . $e->getMessage(),
                'warning'
            );
            return false;
        } finally {
            if (is_string($tempFile) && $tempFile !== '' && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Test IMAP connection.
     *
     * @return array Connection test results
     */
    public function testConnection(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'details' => []
        ];

        try {
            if ($this->connect()) {
                $info = imap_check($this->connection);
                $result['success'] = true;
                $result['message'] = 'IMAP connection successful';
                $result['details'] = [
                    'mailbox' => $this->mailbox,
                    'messages' => $info->Nmsgs,
                    'recent' => $info->Recent
                ];
                $this->disconnect();
            } else {
                $result['message'] = 'Failed to connect to IMAP server';
                $result['details'] = ['error' => imap_last_error()];
            }
        } catch (Exception $e) {
            $result['message'] = 'IMAP connection error: ' . $e->getMessage();
        }

        return $result;
    }
}
