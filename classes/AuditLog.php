<?php
// classes/AuditLog.php
class AuditLog
{
    private $pdo;
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    public function log($userId, $action, $details = null)
    {
        $sql = "INSERT INTO audit_logs (user_id, action, details) VALUES (:uid, :action, :details)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId, ':action' => $action, ':details' => $details]);
    }
    // Get logs with filters + pagination
    public function getLogs($filters, $limit, $offset, $sort_by, $sort_order)
    {
        $where = [];
        $params = [];

        // Filtering
        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = "al.created_at >= :from";
            $params[':from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = "al.created_at <= :to";
            $params[':to'] = $filters['to'];
        }

        $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

        // Count total
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM audit_logs al
            $whereSQL
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Fetch data (replace u.username with real column in your users table!)
        $stmt = $this->pdo->prepare("
            SELECT al.*, u.username AS user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $whereSQL
            ORDER BY $sort_by $sort_order
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'data' => $data
        ];
    }
}
