<?php
require_once BASE_PATH . '/app/core/Model.php';

class User extends Model{

    public function create(array $data):bool{
        $sql = "INSERT INTO users 
                (nombre, email, password_hash, token_verificacion) 
                VALUES (:nombre, :email, :password, :token)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $data['nombre'],
            ':email' => $data['email'],
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':token' => $data['token']
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
    
    

    public function getAllUsers():array{
        $stmt = $this->db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}