<?php

class IngresoMercaderia extends Model
{
    public function remitoExiste(int $proveedorId, string $remito): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM ingresos_mercaderia
             WHERE proveedor_id = :p AND remito = :r"
        );
        $stmt->execute([
            'p'=>$proveedorId,
            'r'=>$remito
        ]);

        return $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ingresos_mercaderia
            (orden_compra_id, proveedor_id, remito, usuario_id)
            VALUES (:o,:p,:r,:u)"
        );
        $stmt->execute([
            'o'=>$data['orden_compra_id'],
            'p'=>$data['proveedor_id'],
            'r'=>$data['remito'],
            'u'=>$data['usuario_id']
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function addDetalle(int $ingresoId, int $mpId, float $cantidad): void
    {
        $this->db->prepare(
            "INSERT INTO ingresos_mercaderia_detalle
             (ingreso_id, materia_prima_id, cantidad)
             VALUES (:i,:m,:c)"
        )->execute([
            'i'=>$ingresoId,
            'm'=>$mpId,
            'c'=>$cantidad
        ]);
    }

    public function registrar(int $orden_id, array $data): bool
    {
        try {
            $this->db->beginTransaction();

            // 1️⃣ Validar remito único por proveedor
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM ingresos_mercaderia im
                 JOIN ordenes_compra oc ON oc.id = im.orden_compra_id
                 WHERE oc.proveedor_id = (
                     SELECT proveedor_id FROM ordenes_compra WHERE id = ?
                 )
                 AND im.remito = ?"
            );
            $stmt->execute([$orden_id, $data['remito']]);

            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Remito ya utilizado para este proveedor');
            }

            // 2️⃣ Insertar cabecera ingreso
            $stmt = $this->db->prepare(
                "INSERT INTO ingresos_mercaderia
                 (orden_compra_id, remito, fecha)
                 VALUES (?, ?, NOW())"
            );
            $stmt->execute([$orden_id, $data['remito']]);
            $ingreso_id = $this->db->lastInsertId();

            // 3️⃣ Detalle + movimiento de stock
            foreach ($data['items'] as $item) {

                if ($item['cantidad'] <= 0) continue;

                // Detalle ingreso
                $this->db->prepare(
                    "INSERT INTO ingresos_mercaderia_detalle
                     (ingreso_id, materia_prima_id, cantidad)
                     VALUES (?, ?, ?)"
                )->execute([
                    $ingreso_id,
                    $item['materia_prima_id'],
                    $item['cantidad']
                ]);

                // Movimiento stock
                $this->db->prepare(
                    "UPDATE materias_primas
                     SET stock = stock + ?
                     WHERE id = ?"
                )->execute([
                    $item['cantidad'],
                    $item['materia_prima_id']
                ]);
            }

            // 4️⃣ Commit
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            die('Error ingreso mercadería: '.$e->getMessage());
        }
    }
}