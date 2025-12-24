<?php

require_once BASE_PATH . '/app/core/Model.php';

class Producto extends Model
{
    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM productos WHERE activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM productos WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $data, ?string $imagen): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO productos 
            (nombre, sku, descripcion, precio_venta, imagen)
            VALUES (:nombre, :sku, :descripcion, :precio, :imagen)"
        );

        $stmt->execute([
            'nombre' => $data['nombre'],
            'sku' => $data['sku'],
            'descripcion' => $data['descripcion'],
            'precio' => $data['precio_venta'],
            'imagen' => $imagen
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, ?string $imagen = null): bool
    {
        $sql = "UPDATE productos SET
                nombre = :nombre,
                sku = :sku,
                descripcion = :descripcion,
                precio_venta = :precio";

        if ($imagen) {
            $sql .= ", imagen = :imagen";
        }

        $sql .= " WHERE id = :id";

        $params = [
            'id' => $id,
            'nombre' => $data['nombre'],
            'sku' => $data['sku'],
            'descripcion' => $data['descripcion'],
            'precio' => $data['precio_venta']
        ];

        if ($imagen) {
            $params['imagen'] = $imagen;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE productos SET activo = 0 WHERE id = :id"
        )->execute(['id' => $id]);
    }
}