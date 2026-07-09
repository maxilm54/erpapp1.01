<?php

require_once BASE_PATH . '/app/core/Model.php';

class ProductoCodigo extends Model
{
    public function add(int $productoId, string $codigo, string $tipo): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO producto_codigos (producto_id, codigo, tipo) 
                 VALUES (:producto_id, :codigo, :tipo)"
            );
            $_SESSION['success'] = "Código de barra agregado correctamente.";
            error_log($_SESSION['success']);
            return $stmt->execute([
                'producto_id' => $productoId,
                'codigo' => $codigo,
                'tipo' => $tipo
            ]);
        } catch (Exception $e) {
            error_log('Error adding barcode for product ID '.$productoId.': '.$e->getMessage());
            $_SESSION['error'] = "Error al agregar el código de barra: " . $e->getMessage();
            return false;
        }
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

    public function update(int $id, string $codigo, ?string $tipo): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE producto_codigos SET codigo = :codigo, tipo = :tipo WHERE id = :id");
            $stmt->execute([
                'codigo' => $codigo,
                'tipo' => $tipo,
                'id' => $id
            ]);
            $_SESSION['success'] = "Código o Tipo, actualizado correctamente.";
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error updating barcode ID '.$id.': '.$e->getMessage());
            $_SESSION['success'] .= "Error al actualizar el código de barra: " . $e->getMessage();
            return false;
        }
    }
}
