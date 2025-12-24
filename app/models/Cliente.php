<?php
require_once BASE_PATH . '/app/core/Model.php';
class Cliente extends Model
{
    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM clientes WHERE activo = 1 ORDER BY razon_social"
        );
        return $stmt->fetchAll();
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM clientes WHERE id = :id AND activo = 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $data): bool
    {
        $sql = "INSERT INTO clientes 
                (razon_social, cuit, email, telefono, direccion) 
                VALUES (:razon, :cuit, :email, :telefono, :direccion)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'razon' => $data['razon_social'],
            'cuit' => $data['cuit'],
            'email' => $data['email'],
            'telefono' => $data['telefono'],
            'direccion' => $data['direccion'],
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE clientes SET
                razon_social = :razon,
                cuit = :cuit,
                email = :email,
                telefono = :telefono,
                direccion = :direccion
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'razon' => $data['razon_social'],
            'cuit' => $data['cuit'],
            'email' => $data['email'],
            'telefono' => $data['telefono'],
            'direccion' => $data['direccion'],
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE clientes SET activo = 0 WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }
}