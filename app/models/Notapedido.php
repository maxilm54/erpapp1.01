<?php

class NotaPedido extends Model
{
    protected string $table = 'notas_pedido';

    // 📄 Listado
    public function all(): array
    {
        return $this->db->query("
            SELECT np.*, c.razon_social
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            ORDER BY np.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ➕ Crear NP
    public function create(array $data): int
    {
        if (empty($data['items'])) {
            throw new Exception('La nota de pedido no tiene items');
        }
        if (empty($data['presupuesto_id'])) {
            $presupuestoId = null;
        }else{
            $presupuestoId = $data['presupuesto_id'];
        }
        $this->db->beginTransaction();

        $stmt = $this->db->prepare("
            INSERT INTO notas_pedido
            (cliente_id, presupuesto_id, usuario_id, observaciones)
            VALUES (:c, :p, :u, :o)
        ");

        $stmt->execute([
            'c' => $data['cliente_id'],
            'p' => $presupuestoId,
            'u' => $data['usuario_id'],
            'o' => $data['observaciones']
        ]);

        $id = (int)$this->db->lastInsertId();

        foreach ($data['items'] as $item) {
            if (
                empty($item['producto_id']) ||
                empty($item['cantidad']) ||
                empty($item['precio'])
            ) continue;

            $this->db->prepare("
                INSERT INTO notas_pedido_detalle
                (nota_pedido_id, producto_id, cantidad, precio)
                VALUES (?, ?, ?, ?)
            ")->execute([
                $id,
                $item['producto_id'],
                $item['cantidad'],
                $item['precio']
            ]);
        }

        $this->db->commit();
        return $id;
    }

    // 👁 NP con detalle
    public function findWithDetalle(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT np.*, c.razon_social,
            p.id AS presupuesto_id,
            p.created_at AS presupuesto_fecha
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            LEFT JOIN presupuestos p ON p.id = np.presupuesto_id
            WHERE np.id = ?
        ");
        $stmt->execute([$id]);
        $np = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$np) return null;

        $stmt = $this->db->prepare("
            SELECT d.*, p.nombre
            FROM notas_pedido_detalle d
            JOIN productos p ON p.id = d.producto_id
            WHERE d.nota_pedido_id = ?
        ");
        $stmt->execute([$id]);
        $np['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $np;
    }

    // ✅ Aprobar
    public function aprobar(int $id): void
    {
        $this->db->prepare("
            UPDATE notas_pedido
            SET estado = 'APROBADA'
            WHERE id = :id AND estado = 'BORRADOR'
        ")->execute(['id' => $id]);
    }

    // ❌ Anular
    public function anular(int $id, string $motivo): void
    {
        $this->db->beginTransaction();
        if (trim($motivo) === '') {
            throw new Exception('Debe indicar el motivo de anulación');
        }
        // 1️⃣ Obtener NP para controlar que exista
        $stmt = $this->db->prepare("
            SELECT presupuesto_id
            FROM notas_pedido
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $np = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$np) {
            throw new Exception('NP no encontrada');
        }

        $this->db->prepare("
            UPDATE notas_pedido
            SET estado = 'ANULADA',
                motivo_anulacion = :m,
                presupuesto_id = NULL,
                anulado_at = NOW()
            WHERE id = :id
        ")->execute([
            'm' => $motivo,
            'id' => $id
        ]);
        // 3️⃣ Liberar presupuesto si existe
        if (!empty($np['presupuesto_id'])) {
            $this->db->prepare("
                UPDATE presupuestos
                SET pre_asign = 'LIBRE'
                WHERE id = ?
            ")->execute([$np['presupuesto_id']]);
        }

        $this->db->commit();
    }

    // 🔽 Combos
    public function clientes(): array // clientes solo activos para realizar np
    {
        return $this->db->query("
            SELECT id, razon_social FROM clientes WHERE activo=1 ORDER BY razon_social
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWithPendientes(int $id): ?array //lo uso en remito salida para el detalle de los pedidos con sus pendientes
    {
        // Cabecera
        $stmt = $this->db->prepare("
            SELECT np.*, c.razon_social AS cliente_nombre,c.id AS id_cliente, SUM(npd.precio*npd.cantidad) AS total_precio
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            LEFT JOIN notas_pedido_detalle npd ON npd.nota_pedido_id = np.id
            WHERE np.id = ?
        ");
        $stmt->execute([$id]);
        $np = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$np) return null; //si no existe la cabecera retorno null y no puedo remitar, deberia redirigir.
        if($np['remitido'] === 'RemitidoCompleto'){ //si la np esta totalmente remitada devuelvo el sussces para avisar, es solo control
            $_SESSION['success'] = 'La Nota de Pedido # '.$id.' ya fue totalmente remitida.';   
            return null;
        }

        // Detalle con pendientes y stock disponible
        $stmt = $this->db->prepare("
            SELECT
                d.producto_id,
                p.nombre,
                p.unidad_medida,
                um.nombre AS unidad_nombre,
                d.cantidad AS pedida,
                COALESCE(SUM(rsd.cantidad), 0) AS remitida,
                (d.cantidad - COALESCE(SUM(rsd.cantidad), 0)) AS pendiente,
                d.precio AS precio,
                SUM(d.cantidad * d.precio) AS total_linea,
                COALESCE((
                    SELECT SUM(
                        CASE
                            WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad
                            WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad
                            ELSE 0
                        END
                    )
                    FROM movimientos_stock ms
                    WHERE ms.producto_id = d.producto_id
                ), 0) AS stock_disponible
            FROM notas_pedido_detalle d
            JOIN productos p ON p.id = d.producto_id
            LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
            LEFT JOIN remitos_salida rs
                ON rs.nota_pedido_id = d.nota_pedido_id
            LEFT JOIN remitos_salida_detalle rsd
                ON rsd.remito_id = rs.id
                AND rsd.producto_id = d.producto_id
            WHERE d.nota_pedido_id = ?
            GROUP BY d.producto_id
        ");
        $stmt->execute([$id]);
        $np['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //aca deberia hacer el control de stock para determinar si la np la puedo remitar o no  

        return $np;
    }
}
