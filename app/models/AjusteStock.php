<?php

class AjusteStock extends Model
{
    public function ajustarProducto(
        int $productoId,
        float $cantidad,
        string $motivo,
        int $usuarioId
    ): void {
        $this->db->beginTransaction();

        try {
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
            $this->db->prepare("
                UPDATE materias_primas
                SET stock = stock + ?
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
            $this->db->rollBack();
            throw $e;
        }
    }
}