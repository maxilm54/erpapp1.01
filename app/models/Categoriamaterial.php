<?php

require_once BASE_PATH . '/app/core/Model.php';

class Categoriamaterial extends Model
{
    public function all(): array
    {
        return $this->db->query("SELECT * FROM categorias_mp_id ORDER BY categoria_nombre")->fetchAll();
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM categorias_mp_id WHERE id_categoria = :id AND activo = 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $data): int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO categorias_mp_id (categoria_nombre) VALUES (:categoria_nombre)"
            );
            $stmt->execute([
                'categoria_nombre' => htmlspecialchars(trim($data['categoria_nombre'])),
            ]);
            $_SESSION['success'] = "Categoría creada correctamente.";
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error al crear la categoría: " . $e->getMessage();
            error_log('Error creating categorias_mp_id: ' . $e->getMessage());
            return 0;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE categorias_mp_id SET categoria_nombre = :categoria_nombre WHERE id_categoria = :id"
            );
            $stmt->execute([
                'categoria_nombre' => htmlspecialchars(trim($data['categoria_nombre'])),
                'id'               => $id,
            ]);
            $_SESSION['success'] = "Categoría actualizada correctamente.";
            return true;
        } catch (Exception $e) {
            $_SESSION['error'] = "Error al actualizar la categoría: " . $e->getMessage();
            error_log('Error updating categorias_mp_id: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE categorias_mp_id SET activo = 0 WHERE id_categoria = :id");
            $stmt->execute(['id' => $id]);
            $_SESSION['success'] = "Categoría inactivada correctamente.";
            return true;
        } catch (Exception $e) {
            $_SESSION['error'] = "No se pudo inactivar la categoría.";
            return false;
        }
    }

    public function activar(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE categorias_mp_id SET activo = 1 WHERE id_categoria = :id");
            $stmt->execute(['id' => $id]);
            $_SESSION['success'] = "Categoría activada correctamente.";
            return true;
        } catch (Exception $e) {
            $_SESSION['error'] = "No se pudo activar la categoría.";
            return false;
        }
    }
}
