<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\DatabaseManager;
use App\Core\RBACManager;

class AuditLogController extends Controller
{
    public function handleRequest(): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_MANAGE_SECURITY);

        $filters = $this->collectFilters($_GET ?? []);
        $logs = $this->fetchLogs($filters);
        $actions = $this->getDistinctActions();

        $this->render('audit_logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
        ]);
    }

    public function handleSubmission(): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_MANAGE_SECURITY);
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            $this->render('audit_logs/index', [
                'logs' => [],
                'filters' => $this->collectFilters([]),
                'actions' => $this->getDistinctActions(),
            ]);
            return;
        }
        $filters = $this->collectFilters($_POST ?? []);
        $logs = $this->fetchLogs($filters);
        $actions = $this->getDistinctActions();

        $this->render('audit_logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
        ]);
    }

    private function collectFilters(array $input): array
    {
        $limit = isset($input['limit']) ? (int) $input['limit'] : 50;
        $limit = max(10, min(200, $limit));

        return [
            'action' => isset($input['action']) ? trim((string) $input['action']) : '',
            'user' => isset($input['user']) ? trim((string) $input['user']) : '',
            'limit' => $limit,
        ];
    }

    private function fetchLogs(array $filters): array
    {
        $db = DatabaseManager::getInstance();
        $sql = '
            SELECT al.*, u.first_name, u.last_name
            FROM audit_logs al
            LEFT JOIN users u ON u.username = al.user_id
            WHERE 1=1
        ';
        $params = [];

        if ($filters['action'] !== '') {
            $sql .= ' AND al.action = :action';
            $params[':action'] = $filters['action'];
        }

        if ($filters['user'] !== '') {
            $sql .= ' AND al.user_id = :user_id';
            $params[':user_id'] = $filters['user'];
        }

        $sql .= ' ORDER BY al.timestamp DESC LIMIT :limit';
        $db->query($sql);

        foreach ($params as $param => $value) {
            $db->bind($param, $value);
        }

        $db->bind(':limit', $filters['limit']);

        return $db->resultSet();
    }

    private function getDistinctActions(): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT DISTINCT action FROM audit_logs ORDER BY action');
        $rows = $db->resultSet();

        return array_map(static fn($row) => $row['action'], $rows);
    }
}
