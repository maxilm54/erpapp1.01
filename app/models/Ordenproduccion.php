<?php
class OrdenProduccion extends Model
{
    public function all(): array
    {
        return $this->db->query("
            SELECT op.*, p.nombre AS producto
            FROM ordenes_produccion op
            JOIN productos p ON p.id = op.producto_id
            ORDER BY op.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function crear(array $data): int
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO ordenes_produccion
                (producto_id, receta_id, cantidad, observaciones, usuario_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['producto_id'],
                $data['receta_id'],
                $data['cantidad'],
                $data['observaciones'],
                $data['usuario_id']
            ]);

            $ordenId = $this->db->lastInsertId();

            $this->reservarMateriaPrima($ordenId);

            $this->db->commit();
            return $ordenId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }  
    
    private function reservarMateriaPrima(int $ordenId): void
    {
        $orden = $this->find($ordenId);
        $receta = (new Receta())->detalle($orden['receta_id']);

        foreach ($receta as $item) {
            $cantidadNecesaria = $item['cantidad'] * $orden['cantidad'];

            // validar stock disponible
            if (!$this->stockDisponible($item['materia_prima_id'], $cantidadNecesaria)) {
                throw new Exception(
                    "Stock insuficiente de {$item['nombre']}"
                );
            }

            $this->db->prepare("
                INSERT INTO reservas_materia_prima
                (orden_produccion_id, materia_prima_id, cantidad)
                VALUES (?, ?, ?)
            ")->execute([
                $ordenId,
                $item['materia_prima_id'],
                $cantidadNecesaria
            ]);
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT op.*, p.nombre AS producto
            FROM ordenes_produccion op
            JOIN productos p ON p.id = op.producto_id
            WHERE op.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function stockDisponible(int $mpId, float $cantidad): bool
    {
        $stmt = $this->db->prepare("
            SELECT
              SUM(CASE WHEN tipo='ENTRADA' THEN cantidad ELSE 0 END) -
              SUM(CASE WHEN tipo='SALIDA' THEN cantidad ELSE 0 END) -
              IFNULL((SELECT SUM(cantidad) FROM reservas_materia_prima WHERE materia_prima_id=?),0)
            FROM movimientos_stock
            WHERE materia_prima_id=?
        ");
        $stmt->execute([$mpId, $mpId]);
        return (float)$stmt->fetchColumn() >= $cantidad;
    }

    public function stockDisponibleCantidad(int $mpId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                    CASE 
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ),0) AS stock
            FROM materias_primas mp
            LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
            WHERE mp.id=? LIMIT 1
        ");
        $stmt->execute([$mpId]);
        return (float)$stmt->fetchColumn();
    }

    public function chequearStockReceta(int $recetaId, float $cantidad): array
    {
        $receta = (new Receta())->detalle($recetaId);

        $faltantes = [];
        $ok = 0;

        foreach ($receta as $item) {
            $necesario = $item['cantidad'] * $cantidad;
            $disponible = $this->stockDisponibleCantidad($item['materia_prima_id']);

            if ($disponible < $necesario) { //
                $faltantes[] = [
                    'materia_prima' => $item['nombre'],
                    'necesario'     => $necesario,
                    'disponible'    => $disponible,
                    'faltante'      => $necesario - $disponible
                ];
            } else {
                $ok++;
            }
        }

        $estado = 'ok';
        if (count($faltantes) === count($receta)) {
            $estado = 'error';
        } elseif (count($faltantes) > 0) {
            $estado = 'warning';
        }

        return [
            'estado'    => $estado,
            'faltantes' => $faltantes
        ];
    }
}