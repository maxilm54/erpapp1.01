<?php

class AjusteStock extends Model
{
    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT ms.*, u.nombre AS usuario
            FROM movimientos_stock ms
            JOIN users u ON u.id = ms.usuario_id
            WHERE ms.origen = 'AJUSTE_STOCK'
            ORDER BY ms.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function ajustarProducto(
        int $productoId,
        float $cantidad,
        string $motivo,
        int $usuarioId
    ): void {
        $this->db->beginTransaction();

        try {
            if ($cantidad == 0) {
            throw new Exception('La cantidad no puede ser 0');
            }
            $this->db->prepare("
                UPDATE productos
                SET stock = stock + ?
                WHERE id = ?
            ")->execute([$cantidad, $productoId]);

            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo, origen, producto_id, cantidad, observaciones, usuario_id)
                VALUES ('AJUSTE','AJUSTE_STOCK', ?, ?, ?, ?)
            ")->execute([
                $productoId,
                $cantidad,
                $motivo,
                $usuarioId
            ]);

            $this->db->commit();

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al ajustar stock: ' . $e->getMessage();
            $this->db->rollBack();
            throw $e;
        }
    }

    public function ajustarMateriaPrima(
        int $mpId,
        float $cantidad,
        string $motivo,
        int $usuarioId
    ): void {
        $this->db->beginTransaction();

        try {
            if ($cantidad == 0) {
            throw new Exception('La cantidad no puede ser 0');
            }
            $this->db->prepare("
                UPDATE materias_primas
                SET stock_actual = stock_actual + ?
                WHERE id = ?
            ")->execute([$cantidad, $mpId]);

            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo, origen, materia_prima_id, cantidad, observaciones, usuario_id)
                VALUES ('AJUSTE','AJUSTE_STOCK', ?, ?, ?, ?)
            ")->execute([
                $mpId,
                $cantidad,
                $motivo,
                $usuarioId
            ]);

            $this->db->commit();

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al ajustar stock: ' . $e->getMessage();
            $this->db->rollBack();
            throw $e;
        }
    }
}