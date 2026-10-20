<?php
require_once BASE_PATH . '/app/core/Model.php';

class Deposito extends Model
{
    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM depositos WHERE activo = 1 ORDER BY es_transito ASC, nombre"
        )->fetchAll();
    }

    public function allSeleccionables(): array
    {
        return $this->db->query(
            "SELECT * FROM depositos WHERE activo = 1 AND es_transito = 0 ORDER BY nombre"
        )->fetchAll();
    }

    public function getTransito(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM depositos WHERE es_transito = 1 AND activo = 1 LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function ensureTransito(): int
    {
        $transito = $this->getTransito();
        if ($transito) return (int)$transito['id'];

        $this->db->prepare(
            "INSERT INTO depositos (nombre, descripcion, es_transito) VALUES ('En Tránsito', 'Depósito virtual para mercadería en tránsito entre depósitos', 1)"
        )->execute();
        return (int)$this->db->lastInsertId();
    }

    public function allWithInactives(): array
    {
        return $this->db->query(
            "SELECT * FROM depositos ORDER BY activo DESC, nombre"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM depositos WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO depositos (nombre, descripcion) VALUES (?, ?)"
        );
        $stmt->execute([$data['nombre'], $data['descripcion'] ?? null]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE depositos SET nombre = ?, descripcion = ? WHERE id = ?"
        );
        return $stmt->execute([$data['nombre'], $data['descripcion'] ?? null, $id]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE depositos SET activo = 0 WHERE id = :id"
        )->execute(['id' => $id]);
    }
}