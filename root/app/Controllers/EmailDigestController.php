<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\EmailDigest;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Controller responsible for managing scheduled email digests.
 */
class EmailDigestController extends Controller
{
    /**
     * Display the digest schedule management view.
     */
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_ALERTS);

        $this->data = [
            'schedules' => EmailDigest::getAllSchedules(),
            'domains' => Domain::getAllDomains(),
            'groups' => DomainGroup::getAllGroups(),
        ];

        require __DIR__ . '/../Views/email_digests.php';
    }

    /**
     * Handle form submissions for schedule creation and management.
     */
    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_ALERTS);

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
                return;
            }
            header('Location: /email-digests');
            exit();
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create_schedule':
                $this->createSchedule();
                break;
            case 'toggle_schedule':
                $this->toggleSchedule();
                break;
            default:
                MessageHelper::addMessage('Unsupported digest action requested.', 'error');
                break;
        }

        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
            return;
        }

        header('Location: /email-digests');
        exit();
    }

    /**
     * Persist a new digest schedule.
     */
    private function createSchedule(): void
    {
        $name = trim($_POST['name'] ?? '');
        $frequencyInput = $_POST['frequency'] ?? 'weekly';
        $customRangeDays = max(1, (int) ($_POST['custom_range_days'] ?? 7));
        $recipientsInput = $_POST['recipients'] ?? '';
        $domainFilter = trim($_POST['domain_filter'] ?? '');
        $groupFilter = !empty($_POST['group_filter']) ? (int) $_POST['group_filter'] : null;
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $startImmediately = isset($_POST['start_immediately']);
        $firstSendAt = trim($_POST['first_send_at'] ?? '');

        $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientsInput))));

        if ($name === '' || empty($recipients)) {
            MessageHelper::addMessage('A schedule name and at least one recipient are required.', 'error');
            return;
        }

        $frequency = $frequencyInput === 'custom'
            ? sprintf('custom:%d', $customRangeDays)
            : $frequencyInput;

        $nextScheduled = null;

        if ($firstSendAt !== '') {
            try {
                $date = new DateTimeImmutable($firstSendAt);
                $nextScheduled = $date->format('Y-m-d H:i:s');
            } catch (Throwable $exception) {
                // Invalid datetime provided; fall back to computed schedule.
            }
        }

        if ($nextScheduled === null) {
            if ($startImmediately) {
                $nextScheduled = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            } else {
                $interval = $this->buildIntervalForFrequency($frequencyInput, $customRangeDays);
                $nextScheduled = (new DateTimeImmutable('now'))->add($interval)->format('Y-m-d H:i:s');
            }
        }

        try {
            EmailDigest::createSchedule([
                'name' => $name,
                'frequency' => $frequency,
                'recipients' => $recipients,
                'domain_filter' => $domainFilter,
                'group_filter' => $groupFilter,
                'enabled' => $enabled,
                'next_scheduled' => $nextScheduled,
                'created_by' => $_SESSION['username'] ?? null,
            ]);

            MessageHelper::addMessage('Email digest schedule created successfully.', 'success');
        } catch (RuntimeException $exception) {
            MessageHelper::addMessage($exception->getMessage(), 'error');
        } catch (Throwable $exception) {
            MessageHelper::addMessage('Failed to create digest schedule: ' . $exception->getMessage(), 'error');
        }
    }

    /**
     * Toggle a schedule's enabled state.
     */
    private function toggleSchedule(): void
    {
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        $desiredState = ($_POST['desired_state'] ?? '1') === '1';

        if ($scheduleId <= 0) {
            MessageHelper::addMessage('Invalid schedule specified.', 'error');
            return;
        }

        if (!EmailDigest::setEnabled($scheduleId, $desiredState)) {
            MessageHelper::addMessage(
                'You are not authorized to update the requested schedule or it no longer exists.',
                'error'
            );
            return;
        }

        MessageHelper::addMessage(
            $desiredState ? 'Schedule enabled.' : 'Schedule disabled.',
            'success'
        );
    }

    /**
     * Build a DateInterval from a frequency selection.
     */
    private function buildIntervalForFrequency(string $frequency, int $customDays): DateInterval
    {
        return match ($frequency) {
            'daily' => new DateInterval('P1D'),
            'weekly' => new DateInterval('P7D'),
            'monthly' => new DateInterval('P1M'),
            'custom' => new DateInterval('P' . max(1, $customDays) . 'D'),
            default => new DateInterval('P7D'),
        };
    }
}
