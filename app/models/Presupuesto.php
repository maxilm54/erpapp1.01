<?php

class Presupuesto extends Model
{
    protected string $table = 'presupuestos';

    public function all(): array
    {
        return $this->db->query(
            "SELECT pr.*, c.razon_social
             FROM presupuestos pr
             JOIN clientes c ON c.id = pr.cliente_id
             ORDER BY pr.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT pr.*, c.razon_social
             FROM presupuestos pr
             JOIN clientes c ON c.id = pr.cliente_id
             WHERE pr.id = ?"
        );
        $stmt->execute([$id]);
        $pr = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pr) return null;

        $pr['detalle'] = $this->detalle($id);
        return $pr;
    }

    public function detalle(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, p.nombre, p.unidad_medida
             FROM presupuestos_detalle d
             JOIN productos p ON p.id = d.producto_id
             WHERE d.presupuesto_id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $this->db->beginTransaction();

        try { 
            //cabecera
            $stmt = $this->db->prepare(
                "INSERT INTO presupuestos (cliente_id, usuario_id)
                 VALUES (:c, :u)"
            );
            $stmt->execute([
                'c' => $data['cliente_id'],
                'u' => $_SESSION['user_id']
            ]);

            $presupuestoId = (int)$this->db->lastInsertId();

            // Detalle
            $stmtDetalle = $this->db->prepare(
                "INSERT INTO presupuestos_detalle
                (presupuesto_id, producto_id, cantidad, precio)
                VALUES (:p, :prod, :cant, :precio)"
            );

            foreach ($data['items'] as $item) {

                // 🛡️ Validaciones duras
                if (
                    empty($item['producto_id']) ||
                    empty($item['cantidad']) ||
                    empty($item['precio'])
                ) {
                    continue;
                }

                $stmtDetalle->execute([
                    'p'     => $presupuestoId,
                    'prod'  => $item['producto_id'],
                    'cant'  => $item['cantidad'],
                    'precio'=> $item['precio']
                ]);
            }

            $this->db->commit();
            return $presupuestoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare(
                "UPDATE presupuestos
                 SET cliente_id = ?
                 WHERE id = ? AND estado = 'BORRADOR'"
            )->execute([
                $data['cliente_id'],
                $id
            ]);

            $this->db->prepare(
                "DELETE FROM presupuestos_detalle WHERE presupuesto_id = ?"
            )->execute([$id]);

            foreach ($data['items'] as $item) {
                if ($item['cantidad'] <= 0) continue;

                $this->db->prepare(
                    "INSERT INTO presupuestos_detalle
                     (presupuesto_id, producto_id, cantidad, precio)
                     VALUES (?, ?, ?, ?)"
                )->execute([
                    $id,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['precio']
                ]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function aprobar(int $id): void
    {
        $this->db->prepare(
            "UPDATE presupuestos
             SET estado = 'APROBADO'
             WHERE id = ? AND estado = 'BORRADOR'"
        )->execute([$id]);
    }
}