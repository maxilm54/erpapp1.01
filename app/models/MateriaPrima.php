<?php
require_once BASE_PATH . '/app/core/Model.php';
class MateriaPrima extends Model
{
    public function all()
    {
        return $this->db->query(
            "SELECT * FROM materias_primas WHERE activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM materias_primas WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $d): bool
    {
        return $this->db->prepare(
            "INSERT INTO materias_primas 
            (nombre, sku, unidad_medida)
            VALUES (:n,:s,:u)"
        )->execute([
            'n'=>$d['nombre'],
            's'=>$d['sku'],
            'u'=>$d['unidad_medida']
        ]);
    }

    public function updateStock(int $id, float $cantidad): void
    {
        $this->db->prepare(
            "UPDATE materias_primas 
             SET stock_actual = stock_actual + :c 
             WHERE id = :id"
        )->execute(['c'=>$cantidad,'id'=>$id]);
    }

    public function search(string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre
            FROM materias_primas
            WHERE nombre LIKE :q
            ORDER BY nombre
            LIMIT 10
        ");
        $stmt->execute(['q' => "%$q%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}