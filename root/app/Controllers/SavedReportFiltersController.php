<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Models\SavedReportFilter;
use App\Models\DmarcReport;
use Throwable;

class SavedReportFiltersController extends Controller
{
    public function store(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);

        $username = $_SESSION['username'] ?? '';
        if ($username === '') {
            MessageHelper::addMessage('Unable to determine the current user.', 'error');
            $this->redirectToReports();
            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $filtersJson = $_POST['filters_json'] ?? '[]';

        if ($name === '') {
            MessageHelper::addMessage('Please provide a name for the saved filter.', 'error');
            $this->redirectToReports();
            return;
        }

        $decoded = $this->decodeFilters($filtersJson);
        $normalized = DmarcReport::normalizeFilterInput($decoded);

        $persisted = SavedReportFilter::create($username, $name, $normalized);
        if ($persisted === null) {
            MessageHelper::addMessage('Unable to save the filter. A filter with the same name may already exist.', 'error');
            $this->redirectToReports();
            return;
        }

        MessageHelper::addMessage('Filter saved successfully.', 'success');
        $this->redirectToReports(['saved_filter_id' => $persisted]);
    }

    public function update(int $id): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);

        $username = $_SESSION['username'] ?? '';
        if ($username === '') {
            MessageHelper::addMessage('Unable to determine the current user.', 'error');
            $this->redirectToReports();
            return;
        }

        $action = $_POST['update_action'] ?? 'rename';
        $attributes = [];

        if ($action === 'refresh') {
            $filtersJson = $_POST['filters_json'] ?? '[]';
            $decoded = $this->decodeFilters($filtersJson);
            $attributes['filters'] = DmarcReport::normalizeFilterInput($decoded);
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            $attributes['name'] = $name;
        }

        if (empty($attributes)) {
            MessageHelper::addMessage('No updates were provided for the saved filter.', 'warning');
            $this->redirectToReports(['saved_filter_id' => $id]);
            return;
        }

        $updated = SavedReportFilter::update($id, $username, $attributes);
        if ($updated) {
            MessageHelper::addMessage('Saved filter updated successfully.', 'success');
        } else {
            MessageHelper::addMessage('Unable to update the saved filter.', 'error');
        }

        $this->redirectToReports(['saved_filter_id' => $id]);
    }

    public function delete(int $id): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);

        $username = $_SESSION['username'] ?? '';
        if ($username === '') {
            MessageHelper::addMessage('Unable to determine the current user.', 'error');
            $this->redirectToReports();
            return;
        }

        if (SavedReportFilter::delete($id, $username)) {
            MessageHelper::addMessage('Saved filter removed.', 'success');
        } else {
            MessageHelper::addMessage('Unable to remove the saved filter.', 'error');
        }

        $this->redirectToReports();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFilters(string $filtersJson): array
    {
        try {
            $decoded = json_decode($filtersJson, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, scalar|array>|null $extra
     */
    private function redirectToReports(array $extra = null): void
    {
        $query = '';
        if ($extra !== null && !empty($extra)) {
            $query = '?' . http_build_query($extra);
        }

        header('Location: /reports' . $query);
        exit();
    }
}
