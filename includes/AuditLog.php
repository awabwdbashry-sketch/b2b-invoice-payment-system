<?php
declare(strict_types=1);

final class AuditLog
{
    public static function record(string $action, ?string $entityType, ?int $entityId, string $description): void
    {
        try {
            $pdo = Database::get();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, created_at)
                 VALUES (:uid, :action, :etype, :eid, :descr, :ip, :now)'
            );
            $stmt->execute([
                'uid'    => Auth::id(),
                'action' => $action,
                'etype'  => $entityType,
                'eid'    => $entityId,
                'descr'  => $description,
                'ip'     => client_ip(),
                'now'    => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the primary transaction/request.
            error_log('AuditLog failure: ' . $e->getMessage());
        }
    }

    public static function paginate(PDO $pdo, int $page, int $perPage, array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['user_id'])) { $where[] = 'user_id = :uid'; $params['uid'] = $filters['user_id']; }
        if (!empty($filters['action'])) { $where[] = 'action LIKE :act'; $params['act'] = '%' . $filters['action'] . '%'; }
        if (!empty($filters['entity_type'])) { $where[] = 'entity_type = :etype'; $params['etype'] = $filters['entity_type']; }
        if (!empty($filters['from'])) { $where[] = 'created_at >= :from'; $params['from'] = $filters['from'] . ' 00:00:00'; }
        if (!empty($filters['to'])) { $where[] = 'created_at <= :to'; $params['to'] = $filters['to'] . ' 23:59:59'; }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $count = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT al.*, u.full_name AS user_name FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                $whereSql ORDER BY al.created_at DESC LIMIT :lim OFFSET :off";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue('lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }
}
