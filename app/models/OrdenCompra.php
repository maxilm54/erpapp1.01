<?php

class OrdenCompra extends Model
{
    protected string $table = 'ordenes_compra';
    public function all()
    {
        return $this->db->query(
            "SELECT oc.*, p.razon_social
             FROM ordenes_compra oc
             JOIN proveedores p ON p.id = oc.proveedor_id
             ORDER BY oc.created_at DESC"
        )->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ordenes_compra (proveedor_id, usuario_id)
             VALUES (:p, :u)"
        );
        $stmt->execute([
            'p' => $data['proveedor_id'],
            'u' => $data['usuario_id']
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function addDetalle(int $ocId, int $mpId, float $cantidad): void // chequear si agrego detalle individualmente o en lote -> respndido en el controlador
    {
        $this->db->prepare(
            "INSERT INTO ordenes_compra_detalle
             (orden_compra_id, materia_prima_id, cantidad)
             VALUES (:o,:m,:c)"
        )->execute([
            'o' => $ocId,
            'm' => $mpId,
            'c' => $cantidad
        ]);
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ordenes_compra WHERE id = :id"
        );
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch();
    }

    public function detalle(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, mp.nombre, mp.unidad_medida
             FROM ordenes_compra_detalle d
             JOIN materias_primas mp ON mp.id = d.materia_prima_id
             WHERE d.orden_compra_id = :id"
        );
        $stmt->execute(['id'=>$id]);
        return $stmt->fetchAll();
    }

    public function aprobar(int $id): void
    {
        $this->db->prepare(
            "UPDATE ordenes_compra
             SET estado = 'APROBADA'
             WHERE id = :id"
        )->execute(['id'=>$id]);
    }

    public function findWithDetalle(int $id): ?array
    {
        // Cabecera
        $stmt = $this->db->prepare(
            "SELECT oc.*, p.razon_social
             FROM ordenes_compra oc
             JOIN proveedores p ON p.id = oc.proveedor_id
             WHERE oc.id = ?"
        );
        $stmt->execute([$id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orden) return null;

        // Detalle
        $orden['detalle'] = $this->detalle($id);

        return $orden;
    }
    public function update(int $id, array $data): bool
    {
        // Solo cabecera (el detalle debería manejarse aparte)
        return $this->db->prepare(
            "UPDATE ordenes_compra
             SET proveedor_id = :p
             WHERE id = :id AND estado = 'PENDIENTE'"
        )->execute([
            'p'  => $data['proveedor_id'],
            'id' => $id
        ]);
    }

    public function materiasPrimas(): array
    {
        return $this->db
            ->query("SELECT id, nombre FROM materias_primas ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function proveedores(): array
    {
        return $this->db
            ->query("SELECT id, razon_social FROM proveedores ORDER BY razon_social")
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}