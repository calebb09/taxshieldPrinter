<?php
// classes/Banks.php

class CompanyManager
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
        $stmt = $this->pdo->query("SELECT * FROM companies ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single bank by ID
    public function getById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM companies WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error in getAll: " . $e->getMessage());
            return [];  // Fallback to empty
        }
    }

    public function getByBankId($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM companies WHERE bank_id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // Create a new bank
    public function create($data, $created_by)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO companies (logo, bank_id, name, address, phone, email, signature, created_by) 
            VALUES (:logo, :bankId, :companyName, :address, :phone, :email, :signature, :created_by)
        ");
        $stmt->execute([
            ':logo' => $data['logo'],
            ':bankId' => $data['bankId'],
            ':companyName' => $data['name'],
            ':address' => $data['address'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':signature' => $data['signature'],
            ':created_by' => $created_by,
        ]);
        return $this->pdo->lastInsertId();
    }

    // Update an existing bank
    public function update($id, $data)
    {
        $stmt = $this->pdo->prepare("
            UPDATE companies SET 
                logo = :logo,
                bank_id = :bankId,
                name = :companyName,
                address = :address,
                email = :email,
                phone = :phone
                signature = :signature
            WHERE id = :id
        ");
        return $stmt->execute([
            ':logo' => $data['logo'] ?? '',
            ':bankId' => $data['bankId'] ?? '',
            ':companyName' => $data['name'] ?? '',
            ':address' => $data['address'] ?? '',
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':signature' => $data['signature'] ?? '',
            ':id' => $id
        ]);
    }

    // Delete a bank
    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM companies WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
