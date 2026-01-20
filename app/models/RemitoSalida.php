<?php

class RemitoSalida extends Model
{
    protected string $table = 'remitos_salida';

    /**
     * 📋 Listado de remitos (para index)
     */
    public function all(): array
    {
        $sql = "
            SELECT 
                r.id,
                r.nota_pedido_id,
                r.usuario_id,
                r.created_at,
                c.razon_social   AS cliente,
                u.nombre         AS usuario
            FROM remitos_salida r
            JOIN notas_pedido np 
                ON np.id = r.nota_pedido_id
            JOIN clientes c 
                ON c.id = np.cliente_id
            JOIN users u 
                ON u.id = r.usuario_id
            ORDER BY r.created_at DESC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 🔍 Buscar remito con detalle (para show)
     */
    public function findWithDetalle(int $id): ?array
    {
        // Cabecera
        $stmt = $this->db->prepare("
            SELECT 
                r.*,
                c.razon_social AS cliente,
                u.nombre       AS usuario
            FROM remitos_salida r
            JOIN notas_pedido np ON np.id = r.nota_pedido_id
            JOIN clientes c      ON c.id = np.cliente_id
            JOIN users u      ON u.id = r.usuario_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$remito) {
            return null;
        }

        // Detalle
        $stmt = $this->db->prepare("
            SELECT 
                d.cantidad,
                p.nombre,
                p.sku
            FROM remitos_salida_detalle d
            JOIN productos p ON p.id = d.producto_id
            WHERE d.remito_id = ?
        ");
        $stmt->execute([$id]);
        $remito['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $remito;
    }

    private function stockDisponibleProducto(int $productoId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                    WHEN tipo = 'SALIDA' THEN -cantidad
                END
            ),0) AS stock
            FROM movimientos_stock
            WHERE producto_id = ?
        ");
        $stmt->execute([$productoId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * ➕ Crear remito (impacta stock)
     * (lo usará create/store)
     */
    public function create(
        int $notaPedidoId,
        int $usuarioId,
        array $items,
        ?string $observaciones = null
        ): int{
        try {
            $this->db->beginTransaction();

            // 1️⃣ Cabecera
            $stmt = $this->db->prepare("
                INSERT INTO remitos_salida
                (nota_pedido_id, usuario_id, observaciones)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$notaPedidoId, $usuarioId, $observaciones]);

            $remitoId = (int)$this->db->lastInsertId();

            // 2️⃣ Detalle + impacto stock
            foreach ($items as $productoId => $cantidad) {
                $stock = $this->stockDisponibleProducto($productoId);
                if ($stock < $cantidad) {
                    throw new Exception(
                        "Stock insuficiente para el producto ID $productoId. Disponible: $stock"
                    );
                }

                if (!is_numeric($productoId) || !is_numeric($cantidad)) {
                    $_SESSION['error'] = 'Datos inválidos en el remito, para NP ID '.$notaPedidoId;
                    throw new Exception('Datos inválidos en el remito');                    
                }
                $cantidad = (float)$cantidad;
                if ($cantidad <= 0) continue;

                // Detalle
                $this->db->prepare("
                    INSERT INTO remitos_salida_detalle
                    (remito_id, producto_id, cantidad)
                    VALUES (?, ?, ?)
                ")->execute([
                    $remitoId,
                    $productoId,
                    $cantidad
                ]);

                // Movimiento de stock (salida)
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, cantidad, observaciones, usuario_id)
                    VALUES ('SALIDA', 'REMITO_SALIDA', ?, ?, ?, ?, ?)
                ")->execute([
                    $remitoId,
                    $productoId,
                    $cantidad,
                    'Remito de salida #' . $remitoId,
                    $usuarioId
                ]);
            }

            /// 🔄 Actualizar estado de la NP
            $stmt = $this->db->prepare("
                SELECT SUM(d.cantidad) AS pedidoTotal , (SELECT SUM(cantidad) AS RemTotal
                FROM remitos_salida_detalle rsd
                LEFT JOIN remitos_salida rs ON rs.id=rsd.remito_id
                LEFT JOIN notas_pedido np ON np.id=rs.nota_pedido_id
                WHERE rs.nota_pedido_id= ?) AS RemTotal
                FROM notas_pedido_detalle d
                WHERE d.nota_pedido_id = ?
            ");
            $stmt->execute([$notaPedidoId, $notaPedidoId]);
            $totales = $stmt->fetch(PDO::FETCH_ASSOC);

            $estado = ($totales['RemTotal'] >= $totales['pedidoTotal']) ? 'RemitidoCompleto' : 'RemitidoParcial';
            error_log('Remitando y Actualizando estado NP ID '.$notaPedidoId.' a '.$estado.' (Pedida: '.$totales['pedidoTotal'].' - Remitida: '.$totales['RemTotal'].')');

            $this->db->prepare("
                UPDATE notas_pedido
                SET remitido = ?
                WHERE id = ?
            ")->execute([$estado, $notaPedidoId]);

            $this->db->commit();
            return $remitoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception('Error al generar remito: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al generar remito: ' . $e->getMessage();
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT  
                remsal.id AS NumRem, c.razon_social AS RazonSocial, np.id AS idNpRem, np.presupuesto_id AS PresNpRem,np.observaciones AS obsNpRem, 
                u.nombre AS UserRem,remsal.observaciones AS obsRemRem, remsal.created_at AS fecha
             FROM remitos_salida remsal
             LEFT JOIN notas_pedido np ON np.id = remsal.nota_pedido_id
             JOIN clientes c ON c.id = np.cliente_id
             JOIN users u ON u.id = remsal.usuario_id
             WHERE remsal.id = ?"
        );
        $stmt->execute([$id]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "SELECT remdet.remito_id AS NumRem, remdet.producto_id AS idProdRem, remdet.cantidad AS CantRem, p.nombre AS ProdRem
             FROM remitos_salida_detalle remdet
             JOIN productos p ON p.id = remdet.producto_id
             WHERE remdet.remito_id = ?"
        );
        $stmt->execute([$id]);
        $remito['detalle'] = $stmt->fetchall(PDO::FETCH_ASSOC);



        if (!$remito) return null;
        return $remito;
    }
}