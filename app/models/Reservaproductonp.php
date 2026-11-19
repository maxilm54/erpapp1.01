<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Deposito.php';

/**
 * Reservas logicas de productos para una Nota de Pedido.
 *
 * El stock fisico reservado vive en el deposito "Deposito Productos Reservados
 * (NP)". Esta tabla registra cuanto de ese stock le corresponde a cada NP y
 * permite consumirlo en el remito o liberarlo si la NP se anula.
 */
class Reservaproductonp extends Model
{
    protected string $table = 'reservas_producto_np';

    public function depositoReservadoId(): int
    {
        return (new Deposito())->ensureReservadoNp();
    }

    /**
     * Cantidad reservada disponible (no consumida ni liberada) para una NP/producto.
     */
    public function disponible(int $notaPedidoId, int $productoId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(cantidad - cantidad_consumida), 0)
            FROM reservas_producto_np
            WHERE nota_pedido_id = ?
              AND producto_id = ?
              AND estado = 'RESERVADO'
        ");
        $stmt->execute([$notaPedidoId, $productoId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Resumen por producto para una NP: total reservado, consumido y disponible.
     * Devuelve [producto_id => ['cantidad', 'consumida', 'disponible']].
     */
    public function resumenPorProducto(int $notaPedidoId): array
    {
        $stmt = $this->db->prepare("
            SELECT producto_id,
                   SUM(cantidad) AS cantidad,
                   SUM(cantidad_consumida) AS consumida,
                   SUM(cantidad - cantidad_consumida) AS disponible
            FROM reservas_producto_np
            WHERE nota_pedido_id = ?
              AND estado = 'RESERVADO'
            GROUP BY producto_id
        ");
        $stmt->execute([$notaPedidoId]);

        $resumen = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $resumen[(int)$fila['producto_id']] = [
                'cantidad'   => (float)$fila['cantidad'],
                'consumida'  => (float)$fila['consumida'],
                'disponible' => (float)$fila['disponible'],
            ];
        }
        return $resumen;
    }

    /**
     * Historial de reservas de una NP (activas, consumidas y liberadas).
     */
    public function porNota(int $notaPedidoId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   p.nombre AS producto,
                   po.nombre AS deposito_origen_nombre,
                   (r.cantidad - r.cantidad_consumida) AS disponible
            FROM reservas_producto_np r
            LEFT JOIN productos p ON p.id = r.producto_id
            LEFT JOIN depositos po ON po.id = r.deposito_origen_id
            WHERE r.nota_pedido_id = ?
            ORDER BY r.id ASC
        ");
        $stmt->execute([$notaPedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra la reserva logica de una produccion. El movimiento ENTRADA al
     * deposito reservado ya lo hizo OrdenProduccion::aplicarAvanceConfirmado().
     * No maneja transacciones: la controla quien llama.
     */
    public function registrarProduccion(
        int $notaPedidoId,
        int $productoId,
        float $cantidad,
        int $ordenProduccionId,
        int $usuarioId
    ): int {
        if ($cantidad <= 0) {
            throw new Exception('La cantidad producida debe ser mayor a cero.');
        }

        $depositoId = $this->depositoReservadoId();
        $stmt = $this->db->prepare("
            INSERT INTO reservas_producto_np
            (nota_pedido_id, producto_id, orden_produccion_id, deposito_origen_id,
             deposito_id, cantidad, estado, origen, usuario_id)
            VALUES (?, ?, ?, NULL, ?, ?, 'RESERVADO', 'PRODUCCION', ?)
        ");
        $stmt->execute([
            $notaPedidoId,
            $productoId,
            $ordenProduccionId,
            $depositoId,
            $cantidad,
            $usuarioId ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Reserva manual: mueve stock de un deposito normal al deposito reservado
     * y deja la reserva logica para la NP.
     */
    public function crearManual(
        int $notaPedidoId,
        int $productoId,
        float $cantidad,
        int $depositoOrigenId,
        int $usuarioId
    ): int {
        if ($cantidad <= 0) {
            throw new Exception('La cantidad a reservar debe ser mayor a cero.');
        }
        if ($depositoOrigenId <= 0) {
            throw new Exception('Debe seleccionar un deposito de origen.');
        }

        $depositoReservadoId = $this->depositoReservadoId();
        if ($depositoOrigenId === $depositoReservadoId) {
            throw new Exception('No se puede usar el deposito reservado como origen.');
        }

        $deposito = (new Deposito())->find($depositoOrigenId);
        if (!$deposito || (int)($deposito['activo'] ?? 1) !== 1) {
            throw new Exception('El deposito de origen no existe o esta inactivo.');
        }
        if (!empty($deposito['es_transito']) || !empty($deposito['es_sobrante']) || !empty($deposito['es_reservado_np'])) {
            throw new Exception('El deposito de origen no es valido para reservar productos.');
        }

        $stmtProd = $this->db->prepare("SELECT nombre, usa_serie FROM productos WHERE id = ?");
        $stmtProd->execute([$productoId]);
        $producto = $stmtProd->fetch(PDO::FETCH_ASSOC);
        if (!$producto) {
            throw new Exception('El producto no existe.');
        }
        if ((int)($producto['usa_serie'] ?? 0) === 1) {
            throw new Exception(
                'El producto "' . $producto['nombre'] . '" usa series: la reserva manual de productos con serie '
                . 'todavia no esta soportada. Gestionelo con el flujo de series.'
            );
        }

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stock = $this->stockEnDeposito($productoId, $depositoOrigenId);
            if ($stock + 0.0005 < $cantidad) {
                throw new Exception(
                    'Stock insuficiente en el deposito de origen. Disponible: '
                    . number_format($stock, 3, ',', '.')
                    . ', solicitado: ' . number_format($cantidad, 3, ',', '.') . '.'
                );
            }

            $observaciones = 'Reserva de producto para NP #' . $notaPedidoId;

            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo, origen, referencia_id, producto_id, cantidad, deposito_id, observaciones, usuario_id)
                VALUES ('SALIDA', 'RESERVA_NP', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $notaPedidoId,
                $productoId,
                $cantidad,
                $depositoOrigenId,
                $observaciones,
                $usuarioId ?: null,
            ]);

            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo, origen, referencia_id, producto_id, cantidad, deposito_id, observaciones, usuario_id)
                VALUES ('ENTRADA', 'RESERVA_NP', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $notaPedidoId,
                $productoId,
                $cantidad,
                $depositoReservadoId,
                $observaciones,
                $usuarioId ?: null,
            ]);

            $this->db->prepare("
                INSERT INTO reservas_producto_np
                (nota_pedido_id, producto_id, orden_produccion_id, deposito_origen_id,
                 deposito_id, cantidad, estado, origen, usuario_id)
                VALUES (?, ?, NULL, ?, ?, ?, 'RESERVADO', 'MANUAL', ?)
            ")->execute([
                $notaPedidoId,
                $productoId,
                $depositoOrigenId,
                $depositoReservadoId,
                $cantidad,
                $usuarioId ?: null,
            ]);
            $id = (int)$this->db->lastInsertId();

            if (!$inTransaction) {
                $this->db->commit();
            }
            return $id;
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Consume reservas FIFO para un remito. No inserta movimientos de stock:
     * el remito registra la SALIDA contra el deposito reservado. Debe llamarse
     * dentro de una transaccion.
     *
     * Devuelve la cantidad efectivamente consumida de las reservas.
     */
    public function consumir(int $notaPedidoId, int $productoId, float $cantidad): float
    {
        if ($cantidad <= 0) {
            return 0.0;
        }

        $stmt = $this->db->prepare("
            SELECT id, cantidad, cantidad_consumida
            FROM reservas_producto_np
            WHERE nota_pedido_id = ?
              AND producto_id = ?
              AND estado = 'RESERVADO'
              AND (cantidad - cantidad_consumida) > 0
            ORDER BY created_at ASC, id ASC
            FOR UPDATE
        ");
        $stmt->execute([$notaPedidoId, $productoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $restante = $cantidad;
        $consumido = 0.0;
        $updConsumido = $this->db->prepare("
            UPDATE reservas_producto_np
            SET cantidad_consumida = cantidad, estado = 'CONSUMIDO'
            WHERE id = ?
        ");
        $updParcial = $this->db->prepare("
            UPDATE reservas_producto_np
            SET cantidad_consumida = ?
            WHERE id = ?
        ");

        foreach ($filas as $fila) {
            if ($restante <= 0.000001) {
                break;
            }

            $disponibleFila = (float)$fila['cantidad'] - (float)$fila['cantidad_consumida'];
            if ($disponibleFila <= 0.000001) {
                continue;
            }

            $tomar = min($restante, $disponibleFila);
            $nuevoConsumido = (float)$fila['cantidad_consumida'] + $tomar;

            if ($nuevoConsumido + 0.000001 >= (float)$fila['cantidad']) {
                $updConsumido->execute([(int)$fila['id']]);
            } else {
                $updParcial->execute([$nuevoConsumido, (int)$fila['id']]);
            }

            $restante -= $tomar;
            $consumido += $tomar;
        }

        return round($consumido, 6);
    }

    /**
     * Libera las reservas vigentes de una NP y devuelve el stock al deposito
     * de origen (o al principal si vino de produccion).
     * Devuelve la cantidad total liberada.
     */
    public function liberar(int $notaPedidoId, int $usuarioId): float
    {
        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM reservas_producto_np
                WHERE nota_pedido_id = ?
                  AND estado = 'RESERVADO'
                  AND (cantidad - cantidad_consumida) > 0
                ORDER BY id ASC
                FOR UPDATE
            ");
            $stmt->execute([$notaPedidoId]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($filas)) {
                if (!$inTransaction) {
                    $this->db->commit();
                }
                return 0.0;
            }

            $depositoReservadoId = $this->depositoReservadoId();
            $principalId = (new Deposito())->principalId();

            $total = 0.0;
            $upd = $this->db->prepare("
                UPDATE reservas_producto_np
                SET cantidad_consumida = cantidad, estado = 'LIBERADO'
                WHERE id = ?
            ");

            foreach ($filas as $fila) {
                $cantidad = (float)$fila['cantidad'] - (float)$fila['cantidad_consumida'];
                if ($cantidad <= 0.000001) {
                    continue;
                }

                $destinoId = !empty($fila['deposito_origen_id'])
                    ? (int)$fila['deposito_origen_id']
                    : 0;

                if ($destinoId <= 0 || $destinoId === $depositoReservadoId) {
                    $destinoId = $principalId;
                } else {
                    $destino = (new Deposito())->find($destinoId);
                    if (!$destino || (int)($destino['activo'] ?? 1) !== 1) {
                        $destinoId = $principalId;
                    }
                }

                if ($destinoId <= 0) {
                    throw new Exception('No hay deposito destino para liberar la reserva.');
                }

                $stockReservado = $this->stockEnDeposito((int)$fila['producto_id'], $depositoReservadoId);
                if ($stockReservado + 0.0005 < $cantidad) {
                    throw new Exception(
                        'No hay stock suficiente en el deposito reservado para liberar el producto #'
                        . (int)$fila['producto_id'] . '. Disponible: '
                        . number_format($stockReservado, 3, ',', '.')
                        . ', a liberar: ' . number_format($cantidad, 3, ',', '.') . '.'
                    );
                }

                $observaciones = 'Liberacion de reserva NP #' . $notaPedidoId;

                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, cantidad, deposito_id, observaciones, usuario_id)
                    VALUES ('SALIDA', 'LIBERACION_NP', ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $notaPedidoId,
                    (int)$fila['producto_id'],
                    $cantidad,
                    $depositoReservadoId,
                    $observaciones,
                    $usuarioId ?: null,
                ]);

                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, cantidad, deposito_id, observaciones, usuario_id)
                    VALUES ('ENTRADA', 'LIBERACION_NP', ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $notaPedidoId,
                    (int)$fila['producto_id'],
                    $cantidad,
                    $destinoId,
                    $observaciones,
                    $usuarioId ?: null,
                ]);

                $upd->execute([(int)$fila['id']]);
                $total += $cantidad;
            }

            if (!$inTransaction) {
                $this->db->commit();
            }
            return round($total, 6);
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Stock de un producto en un deposito. Si el deposito es el principal,
     * incluye los movimientos historicos sin deposito_id (imputados al principal).
     */
    public function stockEnDeposito(int $productoId, int $depositoId): float
    {
        $principalId = (new Deposito())->principalId();
        $filtro = ($depositoId === $principalId)
            ? ' AND (deposito_id = ? OR deposito_id IS NULL)'
            : ' AND deposito_id = ?';

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                    WHEN tipo = 'SALIDA' THEN -cantidad
                END
            ), 0) AS stock
            FROM movimientos_stock
            WHERE producto_id = ?{$filtro}
        ");
        $stmt->execute([$productoId, $depositoId]);
        return (float)$stmt->fetchColumn();
    }
}
