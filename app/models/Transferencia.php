<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Numerador.php';
require_once BASE_PATH . '/app/models/Deposito.php';

class Transferencia extends Model
{
    protected string $table = 'transferencias_depositos';

    public function all(): array
    {
        return $this->db->query("
            SELECT t.*,
                   do.nombre AS deposito_origen,
                   dd.nombre AS deposito_destino,
                   u.nombre AS usuario
            FROM transferencias_depositos t
            JOIN depositos do ON do.id = t.deposito_origen_id
            JOIN depositos dd ON dd.id = t.deposito_destino_id
            JOIN users u ON u.id = t.usuario_id
            ORDER BY t.created_at DESC, t.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   do.nombre AS deposito_origen,
                   dd.nombre AS deposito_destino,
                   u.nombre AS usuario
            FROM transferencias_depositos t
            JOIN depositos do ON do.id = t.deposito_origen_id
            JOIN depositos dd ON dd.id = t.deposito_destino_id
            JOIN users u ON u.id = t.usuario_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfer) return null;

        $stmt = $this->db->prepare("
            SELECT td.*,
                   COALESCE(p.nombre, mp.nombre) AS nombre,
                   COALESCE(p.sku, mp.sku) AS sku,
                   l.codigo_lote,
                   l.fecha_vencimiento,
                   CASE WHEN td.producto_id IS NOT NULL THEN 'producto' ELSE 'materia_prima' END AS tipo
            FROM transferencias_detalle td
            LEFT JOIN productos p ON p.id = td.producto_id
            LEFT JOIN materias_primas mp ON mp.id = td.materia_prima_id
            LEFT JOIN lotes l ON l.id = td.lote_id
            WHERE td.transferencia_id = ?
            ORDER BY td.id
        ");
        $stmt->execute([$id]);
        $transfer['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $transfer;
    }

    public function stockPorDeposito(int $depositoId, ?int $productoId = null, ?int $materiaPrimaId = null): float
    {
        $sql = "SELECT COALESCE(SUM(
                    CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                         WHEN tipo = 'SALIDA' THEN -cantidad END
                ), 0) AS stock
                FROM movimientos_stock
                WHERE deposito_id = ?";
        $params = [$depositoId];

        if ($productoId) {
            $sql .= " AND producto_id = ?";
            $params[] = $productoId;
        } elseif ($materiaPrimaId) {
            $sql .= " AND materia_prima_id = ?";
            $params[] = $materiaPrimaId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function stockTotal(?int $productoId = null, ?int $materiaPrimaId = null): float
    {
        $sql = "SELECT COALESCE(SUM(
                    CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                         WHEN tipo = 'SALIDA' THEN -cantidad END
                ), 0) AS stock
                FROM movimientos_stock WHERE 1=1";
        $params = [];

        if ($productoId) {
            $sql .= " AND producto_id = ?";
            $params[] = $productoId;
        } elseif ($materiaPrimaId) {
            $sql .= " AND materia_prima_id = ?";
            $params[] = $materiaPrimaId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function lotesDisponibles(int $depositoId, ?int $productoId = null, ?int $materiaPrimaId = null): array
    {
        $sql = "
            SELECT l.id, l.codigo_lote, l.fecha_vencimiento,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id AND m.deposito_id = ?
            WHERE l.activo = 1
        ";
        $params = [$depositoId];

        if ($productoId) {
            $sql .= " AND l.producto_id = ?";
            $params[] = $productoId;
        } elseif ($materiaPrimaId) {
            $sql .= " AND l.materia_prima_id = ?";
            $params[] = $materiaPrimaId;
        }

        $sql .= " GROUP BY l.id HAVING stock > 0 ORDER BY l.fecha_vencimiento ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data, int $usuarioId): int
    {
        try {
            $this->db->beginTransaction();

            $origen = (int)$data['deposito_origen_id'];
            $destino = (int)$data['deposito_destino_id'];

            if ($origen === $destino) {
                throw new Exception('El depósito de origen y destino deben ser distintos.');
            }

            // Depósito virtual "En Tránsito": la mercadería queda ahí hasta recibirla
            $transitoId = (new Deposito())->ensureTransito();

            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new Exception('Debe ingresar al menos un ítem a transferir.');
            }

            $numero = (new Numerador())->siguiente('TRANSFERENCIA');

            $this->db->prepare("
                INSERT INTO transferencias_depositos
                (numero, deposito_origen_id, deposito_destino_id, observaciones, estado, usuario_id)
                VALUES (?, ?, ?, ?, 'EN_TRANSITO', ?)
            ")->execute([$numero, $origen, $destino, $data['observaciones'] ?? null, $usuarioId]);

            $transferId = (int)$this->db->lastInsertId();

            foreach ($items as $item) {
                $tipo = $item['tipo'] ?? 'producto';
                $productoId = $tipo === 'producto' ? (int)($item['producto_id'] ?? 0) : null;
                $materiaPrimaId = $tipo === 'materia_prima' ? (int)($item['materia_prima_id'] ?? 0) : null;
                $cantidad = (float)($item['cantidad'] ?? 0);
                $loteId = !empty($item['lote_id']) ? (int)$item['lote_id'] : null;

                if ($cantidad <= 0) continue;

                // Validar stock disponible en el depósito de origen
                $stockDisponible = $this->stockPorDeposito($origen, $productoId, $materiaPrimaId);

                if ($stockDisponible < $cantidad) {
                    throw new Exception(
                        "Stock insuficiente en el depósito de origen. Disponible: " .
                        number_format($stockDisponible, 3, ',', '.') .
                        ", solicitado: " . number_format($cantidad, 3, ',', '.')
                    );
                }

                // Validar lote en origen si se indicó
                if ($loteId) {
                    $stockLote = $this->db->prepare("
                        SELECT COALESCE(SUM(
                            CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                                 WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                        ), 0) AS stock
                        FROM movimientos_stock m
                        WHERE m.lote_id = ? AND m.deposito_id = ?
                    ");
                    $stockLote->execute([$loteId, $origen]);
                    if ((float)$stockLote->fetchColumn() < $cantidad) {
                        throw new Exception("Stock insuficiente del lote #{$loteId} en el depósito de origen.");
                    }
                }

                $this->db->prepare("
                    INSERT INTO transferencias_detalle
                    (transferencia_id, producto_id, materia_prima_id, cantidad, lote_id)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$transferId, $productoId, $materiaPrimaId, $cantidad, $loteId]);

                // SALIDA del depósito origen
                $obsSalida = 'Transferencia #' . $numero . ' salida de ' . ($data['deposito_origen_nombre'] ?? '');
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, materia_prima_id, deposito_id, lote_id, cantidad, observaciones, usuario_id)
                    VALUES ('SALIDA', 'TRANSFERENCIA', ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $transferId, $productoId, $materiaPrimaId, $origen, $loteId,
                    $cantidad,
                    $obsSalida,
                    $usuarioId
                ]);

                // ENTRADA en el depósito virtual "En Tránsito"
                $obsTransito = 'Transferencia #' . $numero . ' en tránsito hasta ' . ($data['deposito_destino_nombre'] ?? '');
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, materia_prima_id, deposito_id, lote_id, cantidad, observaciones, usuario_id)
                    VALUES ('ENTRADA', 'TRANSFERENCIA', ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $transferId, $productoId, $materiaPrimaId, $transitoId, $loteId,
                    $cantidad,
                    $obsTransito,
                    $usuarioId
                ]);
            }

            $this->db->commit();
            return $transferId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al crear transferencia: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al crear transferencia: ' . $e->getMessage());
        }
    }

    public function recibir(int $id, int $usuarioId): void
    {
        try {
            $this->db->beginTransaction();

            $transfer = $this->find($id);
            if (!$transfer) throw new Exception('Transferencia no encontrada');
            if ($transfer['estado'] === 'COMPLETADA') throw new Exception('La transferencia ya fue recibida');
            if ($transfer['estado'] === 'ANULADA') throw new Exception('La transferencia está anulada y no puede recibirse');

            $destino = (int)$transfer['deposito_destino_id'];
            $transito = (new Deposito())->ensureTransito();

            foreach ($transfer['detalle'] as $item) {
                $cantidad = (float)$item['cantidad'];
                $productoId = !empty($item['producto_id']) ? (int)$item['producto_id'] : null;
                $materiaPrimaId = !empty($item['materia_prima_id']) ? (int)$item['materia_prima_id'] : null;
                $loteId = !empty($item['lote_id']) ? (int)$item['lote_id'] : null;

                // SALIDA del depósito "En Tránsito"
                $obsSalida = 'Transferencia #' . $transfer['numero'] . ' recibida en ' . $transfer['deposito_destino'];
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, materia_prima_id, deposito_id, lote_id, cantidad, observaciones, usuario_id)
                    VALUES ('SALIDA', 'TRANSFERENCIA', ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $id, $productoId, $materiaPrimaId, $transito, $loteId,
                    $cantidad,
                    $obsSalida,
                    $usuarioId
                ]);

                // ENTRADA en el depósito destino
                $obsEntrada = 'Transferencia #' . $transfer['numero'] . ' ingresada en ' . $transfer['deposito_destino'];
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, materia_prima_id, deposito_id, lote_id, cantidad, observaciones, usuario_id)
                    VALUES ('ENTRADA', 'TRANSFERENCIA', ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $id, $productoId, $materiaPrimaId, $destino, $loteId,
                    $cantidad,
                    $obsEntrada,
                    $usuarioId
                ]);
            }

            $this->db->prepare("
                UPDATE transferencias_depositos
                SET estado = 'COMPLETADA', recibida_at = NOW(), recibida_por = ?
                WHERE id = ?
            ")->execute([$usuarioId, $id]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al recibir transferencia: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al recibir transferencia: ' . $e->getMessage());
        }
    }

    public function anular(int $id, int $usuarioId): void
    {
        try {
            $this->db->beginTransaction();

            $transfer = $this->find($id);
            if (!$transfer) throw new Exception('Transferencia no encontrada');
            if ($transfer['estado'] === 'ANULADA') throw new Exception('La transferencia ya está anulada');

            $origen = (int)$transfer['deposito_origen_id'];
            $destino = (int)$transfer['deposito_destino_id'];
            $transito = (new Deposito())->ensureTransito();

            // Si está COMPLETADA, el stock está en el destino. Si está EN_TRANSITO, quedó en el depósito virtual.
            $depositoADescontar = ($transfer['estado'] === 'COMPLETADA') ? $destino : $transito;

            foreach ($transfer['detalle'] as $item) {
                // Revertir: ENTRADA en origen (devuelve lo que salió)
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, materia_prima_id, deposito_id, lote_id, cantidad, observaciones, usuario_id)
                    VALUES ('ENTRADA', 'ANULACION_TRANSFERENCIA', ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $id, $item['producto_id'], $item['materia_prima_id'],
                    $origen, $item['lote_id'], (float)$item['cantidad'],
                    'Anulación de transferencia #' . $transfer['numero'],
                    $usuarioId
                ]);

                // Revertir: SALIDA del depósito donde está (destino si COMPLETADA, tránsito si EN_TRANSITO)
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, materia_prima_id, deposito_id, lote_id, cantidad, observaciones, usuario_id)
                    VALUES ('SALIDA', 'ANULACION_TRANSFERENCIA', ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $id, $item['producto_id'], $item['materia_prima_id'],
                    $depositoADescontar, $item['lote_id'], (float)$item['cantidad'],
                    'Anulación de transferencia #' . $transfer['numero'],
                    $usuarioId
                ]);
            }

            $this->db->prepare("UPDATE transferencias_depositos SET estado = 'ANULADA' WHERE id = ?")
                ->execute([$id]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al anular transferencia: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al anular transferencia: ' . $e->getMessage());
        }
    }
}