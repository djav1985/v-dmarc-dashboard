<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

namespace App\Services {
    if (!function_exists(__NAMESPACE__ . '\\imap_fetchbody')) {
        function imap_fetchbody($connection, $uid, $partNumber, $options)
        {
            return $GLOBALS['imap_test_bodies'][$partNumber] ?? '';
        }
    }
}

namespace {

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Services\ImapIngestionService;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$GLOBALS['imap_test_bodies'] = [];

if (!defined('FT_UID')) {
    define('FT_UID', 1);
}

if (!defined('ST_UID')) {
    define('ST_UID', 1);
}
    final class RecordingImapIngestionService extends ImapIngestionService
    {
        /** @var bool[] */
        private array $responseQueue;

        /** @var string[] */
        public array $processedAttachments = [];

        public function __construct(array $responseQueue)
        {
            parent::__construct();
            $this->responseQueue = $responseQueue;
        }

        protected function processAttachment(string $attachment): bool
        {
            $this->processedAttachments[] = $attachment;
            if (empty($this->responseQueue)) {
                return true;
            }

            $result = array_shift($this->responseQueue);
            return (bool) $result;
        }

        public function processMultipart(string $uid, object $structure): bool
        {
            $method = new \ReflectionMethod(ImapIngestionService::class, 'processMultipartEmail');
            $method->setAccessible(true);

            return (bool) $method->invoke($this, $uid, $structure);
        }
    }

    $failures = 0;

    $structure = (object) [
        'parts' => [
            (object) ['disposition' => 'ATTACHMENT', 'encoding' => 0],
            (object) ['disposition' => 'ATTACHMENT', 'encoding' => 0],
        ],
    ];

    $GLOBALS['imap_test_bodies'] = [
        1 => 'first-report',
        2 => 'second-report',
    ];

    $service = new RecordingImapIngestionService([true, true]);
    $result = $service->processMultipart('uid-test', $structure);

    assertTrue($result, 'At least one attachment should trigger success when processed.', $failures);
    assertCountEquals(2, $service->processedAttachments, 'All attachments should be attempted.', $failures);
    assertEquals(['first-report', 'second-report'], $service->processedAttachments, 'Attachments should be processed in order.', $failures);

    $GLOBALS['imap_test_bodies'] = [
        1 => 'first-success',
        2 => 'second-failure',
    ];

    $serviceWithFailure = new RecordingImapIngestionService([true, false]);
    $resultWithFailure = $serviceWithFailure->processMultipart('uid-failure', $structure);

    assertTrue($resultWithFailure, 'Processing should return success when any attachment succeeds.', $failures);
    assertCountEquals(2, $serviceWithFailure->processedAttachments, 'Failure attachments should still be attempted.', $failures);

    echo 'Imap ingestion attachment handling tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
    exit($failures === 0 ? 0 : 1);
}
