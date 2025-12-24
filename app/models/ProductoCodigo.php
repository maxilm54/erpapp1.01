<?php

require_once BASE_PATH . '/app/core/Model.php';

class ProductoCodigo extends Model
{
    public function add(int $productoId, string $codigo, string $tipo): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO producto_codigos (producto_id, codigo, tipo)
             VALUES (:producto, :codigo, :tipo)"
        );

        return $stmt->execute([
            'producto' => $productoId,
            'codigo' => $codigo,
            'tipo' => $tipo
        ]);
    }

    public function byProducto(int $productoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM producto_codigos WHERE producto_id = :id"
        );
        $stmt->execute(['id' => $productoId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare(
            "DELETE FROM producto_codigos WHERE id = :id"
        )->execute(['id' => $id]);
    }
}