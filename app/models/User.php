<?php
require_once BASE_PATH . '/app/core/Model.php';

class User extends Model{

    public function create(array $data):bool{
        $sql = "INSERT INTO users
                (nombre, email, password_hash, token_verificacion, role)
                VALUES (:nombre, :email, :password, :token, :role)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $data['nombre'],
            ':email' => $data['email'],
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':token' => $data['token'],
            ':role' => $data['role'] ?? Role::OPERARIO
        ]);
    }

    public function findByEmail(string $email){
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function verifyEmail(string $token): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET email_verificado = 1, token_verificacion = NULL
             WHERE token_verificacion = :token"
        );
        return $stmt->execute(['token' => $token]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET role = :role WHERE id = :id"
        );
        return $stmt->execute([':role' => $role, ':id' => $id]);
    }
public function getCurrentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return $this->findById($_SESSION['user_id']);
    }

    public function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], $roles);
    }
    
    

    public function getAllUsers():array{
        $stmt = $this->db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}