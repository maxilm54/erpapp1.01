<?php

require_once BASE_PATH . '/app/core/Model.php';

class Unidadmedida extends Model
{
    public function all(): array
    {
        return $this->db->query("SELECT * FROM unidad_medida ORDER BY nombre")->fetchAll();
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM unidad_medida WHERE id_medida = :id AND activo = 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $data): int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO unidad_medida (nombre, detalle) VALUES (:nombre, :detalle)"
            );
            $stmt->execute([
                'nombre'  => htmlspecialchars(trim($data['nombre'])),
                'detalle' => htmlspecialchars(trim($data['detalle'])),
            ]);
            $_SESSION['success'] = "Unidad de medida creada correctamente.";
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error al crear la unidad de medida: " . $e->getMessage();
            error_log('Error creating unidad_medida: ' . $e->getMessage());
            return 0;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE unidad_medida SET nombre = :nombre, detalle = :detalle WHERE id_medida = :id"
            );
            $stmt->execute([
                'nombre'  => htmlspecialchars(trim($data['nombre'])),
                'detalle' => htmlspecialchars(trim($data['detalle'])),
                'id'      => $id,
            ]);
            $_SESSION['success'] = "Unidad de medida actualizada correctamente.";
            return true;
        } catch (Exception $e) {
            $_SESSION['error'] = "Error al actualizar la unidad de medida: " . $e->getMessage();
            error_log('Error updating unidad_medida: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE unidad_medida SET activo = 0 WHERE id_medida = :id");
            $stmt->execute(['id' => $id]);
            $_SESSION['success'] = "Unidad de medida inactivada correctamente.";
            return true;
        } catch (Exception $e) {
            $_SESSION['error'] = "No se pudo inactivar la unidad de medida.";
            return false;
        }
    }

    public function activar(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE unidad_medida SET activo = 1 WHERE id_medida = :id");
            $stmt->execute(['id' => $id]);
            $_SESSION['success'] = "Unidad de medida activada correctamente.";
            return true;
        } catch (Exception $e) {
            $_SESSION['error'] = "No se pudo activar la unidad de medida.";
            return false;
        }
    }
}
