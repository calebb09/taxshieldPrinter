<?php
// classes/CheckManager.php
class CheckManager
{
    private $pdo;
    private $audit;
    public function __construct($pdo, $audit)
    {
        $this->pdo = $pdo;
        $this->audit = $audit;
    }

    // create and auto-generate a check number
    public function createCheck($clientId, $amount, $companyId, $createdBy = null)
    {
        $checkNumber = $this->generateCheckNumber();
        $sql = "INSERT INTO checks (check_number, client_id, company_id, amount, created_by)
                VALUES (:cn, :cid, :acc, :amt, :created_by)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cn' => $checkNumber,
            ':cid' => $clientId,
            ':acc' => $companyId,
            ':amt' => $amount,
            ':created_by' => $createdBy
        ]);
        $id = $this->pdo->lastInsertId();
        $this->audit->log($createdBy, 'create_check', "check_id:$id check_number:$checkNumber client:$clientId");
        return $id;
    }

    public function getCheckByIdWithCompanyBank($userId, $checkId)
    {
        $sql = "
        SELECT c.*, 
               comp.id AS company_id,
               comp.logo AS company_logo,
               comp.address AS company_address,
               comp.phone AS company_phone,
               comp.email AS company_email,
               b.id AS bank_id,
               b.bank_name,
               b.logo AS bank_logo,
               b.bank_account,
               b.bank_routing
        FROM checks c
        LEFT JOIN companies comp ON c.company_id = comp.id
        LEFT JOIN banks b ON comp.bank_id = b.id
        WHERE c.id = :checkId
        AND c.created_by = :userId
        LIMIT 1
    ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':checkId' => $checkId,
            ':userId' => $userId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    public function generateCheckNumber()
    {
        // Example: COMPANY-YYYYMMDD-<random 6>
        // return 'TS-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        // Generate random number between 0 and 9999
        $randomNumber = random_int(0, 9999);

        // Pad with leading zeros to always have 4 digits
        $formattedNumber = str_pad($randomNumber, 4, '0', STR_PAD_LEFT);

        // Combine with prefix
        return '00' . $formattedNumber;
    }

    public function updateStatus($checkId, $status, $byUser = null)
    {
        $allowed = ['Printed', 'Pending', 'Paid', 'Bounced'];
        if (!in_array($status, $allowed))
            throw new Exception("Invalid status");
        $timeCol = ($status === 'Printed') ? 'printed_at' : (($status === 'Paid') ? 'paid_at' : null);
        $sqlParts = "status = :status";
        if ($timeCol)
            $sqlParts .= ", $timeCol = NOW()";
        $sql = "UPDATE checks SET $sqlParts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':status' => $status, ':id' => $checkId]);
        $this->audit->log($byUser, 'update_check_status', "check_id:$checkId status:$status");
    }

    public function getTotalsAndRecent($sinceDays = 30)
    {
        $sql = "SELECT
            COUNT(*) as total_checks,
            SUM(amount) as total_amount,
            SUM(CASE WHEN status='Bounced' THEN 1 ELSE 0 END) as bounced_count
            FROM checks";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch();
    }

    public function checksPerDay($days = 14)
    {
        $stmt = $this->pdo->prepare("
          SELECT DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total
          FROM checks
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
          GROUP BY DATE(created_at)
          ORDER BY DATE(created_at) ASC
        ");
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll();
    }

    public function getChecks(
        $userId,
        $status = null,
        $clientId = null,
        $from = null,
        $to = null,
        $limit = 10,
        $offset = 0,
        $sort_by = 'id',
        $sort_order = 'DESC'
    ) {
        $where = ["checks.created_by = :userId"];
        $params = [":userId" => $userId];

        if ($status) {
            $where[] = "checks.status = :status";
            $params[":status"] = $status;
        }

        if ($clientId) {
            $where[] = "checks.client_id = :clientId";
            $params[":clientId"] = $clientId;
        }

        if ($from) {
            $where[] = "DATE(checks.created_at) >= :from";
            $params[":from"] = $from;
        }

        if ($to) {
            $where[] = "DATE(checks.created_at) <= :to";
            $params[":to"] = $to;
        }

        $whereSql = implode(" AND ", $where);

        // Count total (no joins needed for count)
        $countSql = "SELECT COUNT(*) FROM checks WHERE $whereSql";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Get paginated + sorted data with joins for client and company details
        $sql = "SELECT 
                checks.*,
                clients.first_name AS client_first_name,
                clients.last_name AS client_last_name,
                companies.name AS company_name
            FROM checks
            LEFT JOIN clients ON checks.client_id = clients.id
            LEFT JOIN companies ON checks.company_id = companies.id
            WHERE $whereSql
            ORDER BY checks.$sort_by $sort_order
            LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(":limit", (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", (int) $offset, PDO::PARAM_INT);

        $stmt->execute();
        $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transform data to populate client_id and company_id as objects
        $data = array_map(function ($row) {
            $row['client_id'] = [
                'id' => (int) $row['client_id'],
                'first_name' => $row['client_first_name'] ?? null,
                'last_name' => $row['client_last_name'] ?? null,
                'name' => trim(($row['client_first_name'] ?? '') . ' ' . ($row['client_last_name'] ?? ''))
            ];
            unset($row['client_first_name'], $row['client_last_name']); // Clean up extra fields

            $row['company_id'] = [
                'id' => (int) $row['company_id'],
                'name' => $row['company_name'] ?? null
            ];
            unset($row['company_name']); // Clean up extra field

            // Ensure amount is numeric
            $row['amount'] = (float) $row['amount'];

            // Ensure IDs are integers where appropriate
            $row['id'] = (int) $row['id'];
            $row['created_by'] = (int) $row['created_by'];

            return $row;
        }, $rawData);

        return [
            'total' => $total,
            'data' => $data
        ];
    }

    public function getCheckById($user_id, $check_id)
    {
        $stmt = $this->pdo->prepare("
        SELECT c.*,
               cl.id AS client_id,
               cl.name AS client_name,
               cl.email AS client_email,
               cl.mobile AS client_phone
        FROM checks c
        JOIN clients cl ON c.client_id = cl.id
        WHERE c.id = :check_id AND c.client_id = :user_id
        LIMIT 1
    ");
        $stmt->execute([
            ':check_id' => $check_id,
            ':user_id' => $user_id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

}
