<?php
// classes/Banks.php

class BankManager
{
    private $pdo;
    private $audit;

    public function __construct($pdo, $audit = null)
    {
        $this->pdo = $pdo;
        $this->audit = $audit;
    }

    // List all banks
    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM banks ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single bank by ID
    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM banks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new bank
    public function create($data, $created_by)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO banks (logo, bank_name, bank_account, bank_routing, created_by) 
            VALUES (:logo, :bank_name, :bank_account, :bank_routing, :created_by)
        ");
        $stmt->execute([
            ':logo' => $data['logo'],
            ':bank_name' => $data['bank_name'],
            ':bank_account' => $data['bank_account'],
            ':bank_routing' => $data['bank_routing'],
            ':created_by' => $created_by,
        ]);
        return $this->pdo->lastInsertId();
    }

    // Update an existing bank
    public function update($id, $data)
    {
        // Dynamically build SET part
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $sql = "UPDATE banks SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // Delete a bank
    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM banks WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
