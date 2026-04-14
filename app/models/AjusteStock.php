<?php

class AjusteStock extends Model
{
    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT 
            m.created_at,
            m.tipo,
            m.origen,
            m.referencia_id,
            m.cantidad,
            m.observaciones,
            m.motivo,
            u.nombre AS usuario,
            p.nombre AS producto,
            mp.nombre AS materia_prima
            FROM movimientos_stock m
            JOIN users u ON u.id = m.usuario_id
            LEFT JOIN productos p ON p.id = m.producto_id
            LEFT JOIN materias_primas mp ON mp.id = m.materia_prima_id
            ORDER BY m.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function ajustarProducto(
        int $productoId,
        float $cantidad,
        string $motivo,
        int $usuarioId,
        string $tipo,
        string $obs
    ): void {
        $this->db->beginTransaction();

        try {
            if ($cantidad == 0) {
            throw new Exception('La cantidad no puede ser 0');
            }

            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo, origen, producto_id, cantidad,motivo, observaciones, usuario_id)
                VALUES (?, 'AJUSTE_STOCK', ?, ?, ?, ?, ?)
            ")->execute([
                $tipo,
                $productoId,
                $cantidad,
                $motivo,
                $obs,
                $usuarioId
            ]);

            $this->db->commit();

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al ajustar stock: ' . $e->getMessage();
            error_log('Error al ajustar stock de producto id ' . $productoId . ' : ' . $e->getMessage().__FILE__.':'.__LINE__);
            $this->db->rollBack();
            header('Location: ' . BASE_URL . '/ajustesstock');
            exit;
        }
    }

    public function ajustarMateriaPrima(
        int $mpId,
        float $cantidad,
        string $motivo,
        int $usuarioId,
        string $tipo,
        string $obs
    ): void {
        $this->db->beginTransaction();

        try {
            if ($cantidad == 0) {
            throw new Exception('La cantidad no puede ser 0');
            }
            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo, origen, materia_prima_id, cantidad,motivo, observaciones, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $tipo,
                'AJUSTE_STOCK',
                $mpId,
                $cantidad,
                $motivo,
                $obs,
                $usuarioId
            ]);

            $this->db->commit();

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al ajustar stock de materia prima id ' . $mpId . ' : ' . $e->getMessage();
            error_log('Error al ajustar stock de materia prima id ' . $mpId . ' : ' . $e->getMessage());
            $this->db->rollBack();
            header('Location: ' . BASE_URL . '/ajustesstock');
            exit;
        }
    }
}