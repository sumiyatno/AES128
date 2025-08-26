<?php
require_once __DIR__ . '/../config/database.php';

class User
{
    private $pdo;

    public function __construct()
    {
        // Ambil koneksi dari database.php
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getAllUsers()
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, a.level, a.name AS access_name, a.description
            FROM users u
            JOIN access_levels a ON u.access_level_id = a.id
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUserById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, a.level, a.name AS access_name
            FROM users u
            JOIN access_levels a ON u.access_level_id = a.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getUserByUsername($username)
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, a.level, a.name AS access_name
            FROM users u
            JOIN access_levels a ON u.access_level_id = a.id
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function createUser($username, $password, $access_level_id)
    {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, password, access_level_id)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$username, $hashed, $access_level_id]);
    }

    public function updateUser($id, $username, $password, $access_level_id)
    {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET username = ?, password = ?, access_level_id = ?
            WHERE id = ?
        ");
        return $stmt->execute([$username, $hashed, $access_level_id, $id]);
    }

    public function deleteUser($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
