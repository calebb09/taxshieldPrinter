<?php
// classes/ClientManager.php
class ClientManager
{
    private $pdo;
    private $audit;
    public function __construct($pdo, $audit)
    {
        $this->pdo = $pdo;
        $this->audit = $audit;
    }

    public function createClient($data, $createdBy = null)
    {
        $sql = "INSERT INTO clients
          (first_name,last_name,email,mobile,street_name, street_number, Apartment, city, state, ZIP_code, country, payable_amount,branch_contacted_id,gender,dob,referral_source,payment_reason,ssn,fein,created_by)
          VALUES (:first_name,:last_name,:email,:mobile,:street_name,:street_number,:Apartment,:city,:state,:ZIP_code,:country,:payable_amount,:branch,:gender,:dob,:ref,:reason,:ssn,:fein,:created_by)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $data['first_name'] ?? '',
            ':last_name' => $data['last_name'] ?? '',
            ':email' => $data['email'] ?? null,
            ':mobile' => $data['mobile'] ?? null,
            ':street_name' => $data['street_name'] ?? null,
            ':street_number' => $data['street_number'] ?? null,
            ':Apartment' => $data['Apartment'] ?? null,
            ':city' => $data['city'] ?? null,
            ':state' => $data['state'] ?? null,
            ':ZIP_code' => $data['ZIP_code'] ?? null,
            ':country' => $data['country'] ?? null,
            ':payable_amount' => $data['payable_amount'] ?? 0,
            ':branch' => $data['branch_contacted_id'] ?? null,
            ':gender' => $data['gender'] ?? 'other',
            ':dob' => !empty($data['dob']) ? $data['dob'] : null,
            ':ref' => $data['referral_source'] ?? null,
            ':reason' => $data['payment_reason'] ?? 'Other',
            ':ssn' => $data['ssn'] ?? null,
            ':fein' => $data['fein'] ?? null,
            ':created_by' => $createdBy
        ]);
        $id = $this->pdo->lastInsertId();
        $this->audit->log($createdBy, 'create_client', "client_id:$id");
        return $id;
    }

    public function getClient($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function updateClient($id, $data, $userId = null)
    {
        // Minimal update example - extend as needed
        $fields = [];
        $params = [':id' => $id];
        foreach (['name', 'email', 'mobile', 'address', 'payable_amount', 'payment_reason', 'referral_source', 'gender', 'dob'] as $f) {
            if (isset($data[$f])) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        if ($fields) {
            $sql = "UPDATE clients SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->audit->log($userId, 'update_client', "client_id:$id");
            return true;
        }
        return false;
    }

    public function listClients($filters = [], $limit = 10, $offset = 0, $sort_by = 'id', $sort_order = 'DESC')
    {
        $where = [];
        $params = [];

        if (isset($filters['branch'])) {
            $where[] = 'branch_id = :branch';
            $params[':branch'] = $filters['branch'];
        }
        if (isset($filters['name'])) {
            $where[] = 'name LIKE :name';
            $params[':name'] = '%' . $filters['name'] . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clients $whereSQL");
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        // Get data
        $stmt = $this->pdo->prepare("
        SELECT *
        FROM clients
        $whereSQL
        ORDER BY $sort_by $sort_order
        LIMIT :limit OFFSET :offset
    ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
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


    // 🔎 Search client by name
    public function searchClients($userId, $name, $email, $mobile, $limit, $offset, $sort_by, $sort_order)
    {
        $where = ["created_by = :uid"];
        $params = [":uid" => $userId];

        if ($name) {
            $where[] = "first_name LIKE :name OR last_name LIKE :name";
            $params[":name"] = "%$name%";
        }
        if ($email) {
            $where[] = "email LIKE :email";
            $params[":email"] = "%$email%";
        }
        if ($mobile) {
            $where[] = "mobile LIKE :mobile";
            $params[":mobile"] = "%$mobile%";
        }

        $whereSql = implode(" AND ", $where);

        // Count
        $countSql = "SELECT COUNT(*) FROM clients WHERE $whereSql";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        // Data
        $sql = "SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, mobile, created_at
            FROM clients
            WHERE $whereSql
            ORDER BY $sort_by $sort_order
            LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(":limit", (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total' => $total,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

}
