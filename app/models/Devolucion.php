<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Numerador.php';
require_once BASE_PATH . '/app/models/Cuentacorrientecliente.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';
require_once BASE_PATH . '/app/helpers/AsientoAutomatico.php';

class Devolucion extends Model
{
    protected string $table = 'devoluciones';

    public function all(): array
    {
        return $this->db->query("
            SELECT d.*,
                   c.razon_social AS nombre_cliente,
                   u.nombre AS nombre_usuario,
                   r.numero AS remito_numero
            FROM devoluciones d
            LEFT JOIN clientes c ON c.id = d.cliente_id
            LEFT JOIN users u ON u.id = d.usuario_id
            LEFT JOIN remitos_salida r ON r.id = d.remito_id
            ORDER BY d.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*,
                   COALESCE(c.razon_social, 'Sin cliente') AS nombre_cliente,
                   c.cuit, c.email AS cliente_email, c.direccion AS cliente_direccion,
                   c.telefono AS cliente_telefono, c.localidad AS cliente_localidad,
                   u.nombre AS nombre_usuario,
                   r.numero AS remito_numero, r.created_at AS remito_fecha
            FROM devoluciones d
            LEFT JOIN clientes c ON c.id = d.cliente_id
            LEFT JOIN users u ON u.id = d.usuario_id
            LEFT JOIN remitos_salida r ON r.id = d.remito_id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $devolucion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$devolucion) return null;

        $stmt = $this->db->prepare("
            SELECT dd.*, p.nombre AS producto_nombre
            FROM devoluciones_detalle dd
            LEFT JOIN productos p ON p.id = dd.producto_id
            WHERE dd.devolucion_id = ?
        ");
        $stmt->execute([$id]);
        $devolucion['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT dr.*, cb.nombre AS caja_nombre
            FROM devoluciones_reembolsos dr
            LEFT JOIN cajas_bancos cb ON cb.id = dr.caja_banco_id
            WHERE dr.devolucion_id = ?
        ");
        $stmt->execute([$id]);
        $devolucion['reembolsos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $devolucion;
    }

    public function findFromRemito(int $remitoId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   COALESCE(r.cliente_nombre, c.razon_social) AS cliente_nombre,
                   COALESCE(r.cliente_id, np.cliente_id) AS cliente_id
            FROM remitos_salida r
            LEFT JOIN notas_pedido np ON np.id = r.nota_pedido_id
            LEFT JOIN clientes c ON c.id = r.cliente_id OR (np.id IS NOT NULL AND c.id = np.cliente_id)
            WHERE r.id = ?
        ");
        $stmt->execute([$remitoId]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$remito) return null;

        $stmt = $this->db->prepare("
            SELECT rsd.*, rsd.cantidad AS CantRem, p.nombre AS producto_nombre,
                   (SELECT COALESCE(SUM(
                       CASE
                           WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                           WHEN tipo = 'SALIDA' THEN -cantidad
                       END
                   ),0) FROM movimientos_stock WHERE producto_id = rsd.producto_id) AS stock_actual
            FROM remitos_salida_detalle rsd
            LEFT JOIN productos p ON p.id = rsd.producto_id
            WHERE rsd.remito_id = ?
        ");
        $stmt->execute([$remitoId]);
        $remito['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT dd.producto_id, COALESCE(SUM(dd.cantidad_total), 0) AS ya_devuelto
            FROM devoluciones d
            JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
            WHERE d.remito_id = ? AND d.estado != 'ANULADA'
            GROUP BY dd.producto_id
        ");
        $stmt->execute([$remitoId]);
        $devueltoMap = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $devueltoMap[(int)$row['producto_id']] = (float)$row['ya_devuelto'];
        }

        foreach ($remito['detalle'] as &$item) {
            $pid = (int)$item['producto_id'];
            $item['ya_devuelto'] = $devueltoMap[$pid] ?? 0;
            $item['pendiente_devolver'] = (float)$item['CantRem'] - ($devueltoMap[$pid] ?? 0);
        }
        unset($item);

        return $remito;
    }

    public function create(array $data, array $items, array $reembolsos): int
    {
        try {
            $this->db->beginTransaction();
            $cc = new CuentaCorrienteCliente();
            $numero = (new Numerador())->siguiente('DEVOLUCION');

            $clienteId = (int)$data['cliente_id'];
            $usuarioId = (int)$data['usuario_id'];
            $remitoId = (int)$data['remito_id'];
            $motivo = $data['motivo'];

            $stmtLock = $this->db->prepare("
                SELECT rsd.producto_id, rsd.cantidad AS CantRem
                FROM remitos_salida_detalle rsd
                WHERE rsd.remito_id = ?
                FOR UPDATE
            ");
            $stmtLock->execute([$remitoId]);
            $remitoCantidades = [];
            while ($row = $stmtLock->fetch(PDO::FETCH_ASSOC)) {
                $remitoCantidades[(int)$row['producto_id']] = (float)$row['CantRem'];
            }

            $stmtDev = $this->db->prepare("
                SELECT dd.producto_id, COALESCE(SUM(dd.cantidad_total), 0) AS ya_devuelto
                FROM devoluciones d
                JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                WHERE d.remito_id = ? AND d.estado != 'ANULADA'
                GROUP BY dd.producto_id
            ");
            $stmtDev->execute([$remitoId]);
            $devueltoMap = [];
            while ($row = $stmtDev->fetch(PDO::FETCH_ASSOC)) {
                $devueltoMap[(int)$row['producto_id']] = (float)$row['ya_devuelto'];
            }

            foreach ($items as $item) {
                $pid = (int)$item['producto_id'];
                if (!isset($remitoCantidades[$pid])) {
                    throw new Exception("El producto #{$pid} no pertenece a este remito.");
                }
                $pendiente = $remitoCantidades[$pid] - ($devueltoMap[$pid] ?? 0.0);
                if ((float)$item['cantidad_total'] > $pendiente + 0.01) {
                    throw new Exception(
                        "La cantidad a devolver del producto #{$pid} (" . number_format((float)$item['cantidad_total'], 2, ',', '.') . ") " .
                        "supera lo pendiente (" . number_format($pendiente, 2, ',', '.') . ")."
                    );
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO devoluciones (numero, remito_id, cliente_id, usuario_id, motivo, estado)
                VALUES (?, ?, ?, ?, ?, 'PENDIENTE')
            ");
            $stmt->execute([$numero, $remitoId, $clienteId, $usuarioId, $motivo]);
            $devolucionId = (int)$this->db->lastInsertId();

            $clienteNombre = $data['cliente_nombre'] ?? null;
            if (empty($clienteNombre)) {
                $stmtCli = $this->db->prepare("SELECT razon_social FROM clientes WHERE id = ?");
                $stmtCli->execute([$clienteId]);
                $clienteNombre = $stmtCli->fetchColumn() ?: null;
            }

            $totalDevolucion = 0;

            foreach ($items as $item) {
                $productoId = (int)$item['producto_id'];
                $cantidadTotal = (float)$item['cantidad_total'];
                $cantidadReingresa = (float)$item['cantidad_reingresa'];
                $cantidadDescarta = (float)$item['cantidad_descarta'];
                $condicion = $item['condicion'];
                $precioUnitario = (float)$item['precio_unitario'];
                $observacionesItem = $item['observaciones'] ?? null;

                if ($cantidadTotal <= 0) continue;

                if ($cantidadReingresa != $cantidadTotal) {
                    throw new Exception("La cantidad reingresa debe ser igual a la cantidad total devuelta para el producto #{$productoId}");
                }
                if ($cantidadDescarta > $cantidadReingresa) {
                    throw new Exception("La cantidad descarta no puede superar la cantidad reingresa para el producto #{$productoId}");
                }

                $this->db->prepare("
                    INSERT INTO devoluciones_detalle
                    (devolucion_id, producto_id, cantidad_total, cantidad_reingresa, cantidad_descarta, condicion, precio_unitario, observaciones)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $devolucionId, $productoId, $cantidadTotal,
                    $cantidadReingresa, $cantidadDescarta,
                    $condicion, $precioUnitario, $observacionesItem
                ]);

                $subtotal = $precioUnitario * $cantidadTotal;
                $totalDevolucion += $subtotal;

                if ($cantidadReingresa > 0) {
                    $this->db->prepare("
                        INSERT INTO movimientos_stock
                        (tipo, origen, referencia_id, producto_id, cantidad, motivo, observaciones, usuario_id)
                        VALUES ('ENTRADA', 'DEVOLUCION', ?, ?, ?, 'Reingreso por devolución', ?, ?)
                    ")->execute([
                        $devolucionId, $productoId, $cantidadReingresa,
                        'Devolución #DEV-' . str_pad($numero, 6, '0', STR_PAD_LEFT),
                        $usuarioId
                    ]);
                }

                if ($cantidadDescarta > 0) {
                    $this->db->prepare("
                        INSERT INTO movimientos_stock
                        (tipo, origen, referencia_id, producto_id, cantidad, motivo, observaciones, usuario_id)
                        VALUES ('SALIDA', 'DEVOLUCION_DESCARTE', ?, ?, ?, 'Descarte por devolución', ?, ?)
                    ")->execute([
                        $devolucionId, $productoId, $cantidadDescarta,
                        'Descarte devolución #DEV-' . str_pad($numero, 6, '0', STR_PAD_LEFT),
                        $usuarioId
                    ]);
                }

                $cc->registrarCredito(
                    $clienteId,
                    $subtotal,
                    'DEVOLUCION',
                    $devolucionId,
                    $usuarioId,
                    'Devolución #DEV-' . str_pad($numero, 6, '0', STR_PAD_LEFT) . ' - ' . ($item['producto_nombre'] ?? ''),
                    $clienteNombre
                );

                try {
                    $asientoAuto = new AsientoAutomatico();
                    $asientoAuto->ventaDevolucion($clienteId, $subtotal, $devolucionId, $usuarioId, $clienteNombre);
                } catch (Exception $e) {
                    error_log("Error generando asiento para devolución #{$devolucionId}: " . $e->getMessage());
                }
            }

            foreach ($reembolsos as $reembolso) {
                $metodo = $reembolso['metodo'];
                $monto = (float)$reembolso['monto'];
                $cajaBancoId = !empty($reembolso['caja_banco_id']) ? (int)$reembolso['caja_banco_id'] : null;
                $obsReembolso = $reembolso['observaciones'] ?? null;

                if ($monto <= 0) continue;

                if (in_array($metodo, ['EFECTIVO', 'TRANSFERENCIA']) && $cajaBancoId) {
                    $stmtCaja = $this->db->prepare("
                        SELECT nombre, saldo_actual FROM cajas_bancos WHERE id = ? FOR UPDATE
                    ");
                    $stmtCaja->execute([$cajaBancoId]);
                    $cajaRow = $stmtCaja->fetch(PDO::FETCH_ASSOC);
                    if (!$cajaRow) {
                        throw new Exception("La caja/banco #{$cajaBancoId} no existe.");
                    }
                    $saldoCaja = (float)$cajaRow['saldo_actual'];
                    if ($saldoCaja < $monto - 0.01) {
                        throw new Exception(
                            "El saldo de «{$cajaRow['nombre']}» ($" . number_format($saldoCaja, 2, ',', '.') . ") " .
                            "no alcanza para cubrir el reembolso de $" . number_format($monto, 2, ',', '.') . " en {$metodo}."
                        );
                    }
                }

                $this->db->prepare("
                    INSERT INTO devoluciones_reembolsos
                    (devolucion_id, metodo, monto, caja_banco_id, observaciones)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$devolucionId, $metodo, $monto, $cajaBancoId, $obsReembolso]);

                if (in_array($metodo, ['EFECTIVO', 'TRANSFERENCIA']) && $cajaBancoId) {
                    $cajaModel = new CajaBanco();
                    $cajaModel->registrarMovimiento([
                        'caja_banco_id'     => $cajaBancoId,
                        'fecha'             => date('Y-m-d'),
                        'tipo'              => 'EGRESO',
                        'monto'             => $monto,
                        'descripcion'       => "Reembolso devolución #DEV-" . str_pad($numero, 6, '0', STR_PAD_LEFT),
                        'referencia_modulo' => 'DEVOLUCIONES',
                        'referencia_tipo'   => 'REEMBOLSO',
                        'referencia_id'     => $devolucionId,
                        'usuario_id'        => $usuarioId,
                    ]);

                    try {
                        $asientoAuto = new AsientoAutomatico();
                        $asientoAuto->ventaReembolso($clienteId, $monto, $devolucionId, $usuarioId, $cajaBancoId, $clienteNombre);
                    } catch (Exception $e) {
                        error_log("Error generando asiento reembolso devolución #{$devolucionId}: " . $e->getMessage());
                    }
                }
            }

            $this->db->prepare("UPDATE devoluciones SET estado = 'PROCESADA' WHERE id = ?")->execute([$devolucionId]);

            $this->db->commit();
            return $devolucionId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al crear devolución: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al crear devolución: ' . $e->getMessage());
        }
    }

    public function anular(int $id, int $usuarioId, string $motivo): void
    {
        try {
            $this->db->beginTransaction();
            $devolucion = $this->find($id);
            if (!$devolucion) throw new Exception('Devolución no encontrada');
            if ($devolucion['estado'] === 'ANULADA') throw new Exception('La devolución ya está anulada');

            $cc = new CuentaCorrienteCliente();
            $clienteId = (int)$devolucion['cliente_id'];
            $clienteNombre = $devolucion['nombre_cliente'] ?? null;

            foreach ($devolucion['detalle'] as $item) {
                $subtotal = (float)$item['precio_unitario'] * (float)$item['cantidad_total'];

                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, cantidad, motivo, observaciones, usuario_id)
                    VALUES ('SALIDA', 'ANULACION_DEVOLUCION', ?, ?, ?, 'Anulación devolución', ?, ?)
                ")->execute([
                    $id, $item['producto_id'], $item['cantidad_total'],
                    'Anulación de devolución #DEV-' . str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT),
                    $usuarioId
                ]);

                if ((float)$item['cantidad_reingresa'] > 0) {
                    $this->db->prepare("
                        INSERT INTO movimientos_stock
                        (tipo, origen, referencia_id, producto_id, cantidad, motivo, observaciones, usuario_id)
                        VALUES ('SALIDA', 'ANULACION_DEVOLUCION', ?, ?, ?, 'Reverso reingreso por anulación', ?, ?)
                    ")->execute([
                        $id, $item['producto_id'], $item['cantidad_reingresa'],
                        'Reverso reingreso devolución #DEV-' . str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT),
                        $usuarioId
                    ]);
                }

                $cc->registrarDebito(
                    $clienteId,
                    $subtotal,
                    'ANULACION_DEVOLUCION',
                    $id,
                    $usuarioId,
                    'Anulación devolución #DEV-' . str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT),
                    $clienteNombre
                );
            }

            foreach ($devolucion['reembolsos'] as $reembolso) {
                if (in_array($reembolso['metodo'], ['EFECTIVO', 'TRANSFERENCIA']) && $reembolso['caja_banco_id']) {
                    $cajaModel = new CajaBanco();
                    $cajaModel->registrarMovimiento([
                        'caja_banco_id'     => $reembolso['caja_banco_id'],
                        'fecha'             => date('Y-m-d'),
                        'tipo'              => 'INGRESO',
                        'monto'             => (float)$reembolso['monto'],
                        'descripcion'       => "Reverso reembolso devolución #DEV-" . str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT),
                        'referencia_modulo' => 'DEVOLUCIONES',
                        'referencia_tipo'   => 'ANULACION_REEMBOLSO',
                        'referencia_id'     => $id,
                        'usuario_id'        => $usuarioId,
                    ]);
                }
            }

            $this->db->prepare("
                UPDATE devoluciones
                SET estado = 'ANULADA', motivo_anulacion = ?, anulado_at = NOW()
                WHERE id = ?
            ")->execute([$motivo, $id]);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al anular devolución: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al anular devolución: ' . $e->getMessage());
        }
    }

    public function generarYGuardarPdf(int $id): string
    {
        $devolucion = $this->find($id);
        if (!$devolucion) throw new Exception('Devolución no encontrada');

        $html = $this->renderPdfHtml($devolucion);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $dir = empresaStoragePath("devoluciones");
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $filename = "devolucion_" . str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT) . ".pdf";
        $fullPath = $dir . '/' . $filename;
        file_put_contents($fullPath, $dompdf->output());

        $hash = hash_file('sha256', $fullPath);
        $this->db->prepare("UPDATE devoluciones SET pdf_path = ?, pdf_hash = ? WHERE id = ?")
            ->execute([$fullPath, $hash, $id]);

        return $fullPath;
    }

    private function renderPdfHtml(array $devolucion): string
    {
        $empresa = ['nombre'=>'', 'cuit'=>'', 'email'=>'', 'telefono'=>'', 'direccion'=>'', 'logo'=>'', 'logo_file'=>null];
        $logo = '';
        try {
            $masterDb = Database::getMaster();
            $empRow = $masterDb->query("SELECT id, nombre, cuit, email, telefono, direccion, logo FROM tenants WHERE id = " . (int)(Auth::getTenantId() ?? 0))->fetch(PDO::FETCH_ASSOC);
            if ($empRow) {
                $empresa['nombre']    = $empRow['nombre'] ?? 'Empresa';
                $empresa['cuit']      = $empRow['cuit'] ?? '';
                $empresa['email']     = $empRow['email'] ?? '';
                $empresa['telefono']  = $empRow['telefono'] ?? '';
                $empresa['direccion'] = $empRow['direccion'] ?? '';
                $empresa['logo_file'] = $empRow['logo'] ?? null;
                if (!empty($empRow['logo'])) {
                    $logoFile = BASE_PATH . "/public/uploads/img_config/empresa_{$empRow['id']}/{$empRow['logo']}";
                    if (file_exists($logoFile)) {
                        $empresa['logo'] = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile));
                        $logo = $empresa['logo'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("[DEVOLUCION PDF] Error cargando empresa: " . $e->getMessage());
        }

        ob_start();
        require BASE_PATH . '/app/views/pdf/devolucion.php';
        return ob_get_clean();
    }

    public function search(string $q): array
    {
        if (strlen($q) < 2) return [];

        return $this->db->query("
            SELECT d.id, d.numero, d.estado, d.created_at,
                   COALESCE(c.razon_social, 'Sin cliente') AS nombre_cliente,
                   r.numero AS remito_numero
            FROM devoluciones d
            LEFT JOIN clientes c ON c.id = d.cliente_id
            LEFT JOIN remitos_salida r ON r.id = d.remito_id
            WHERE d.numero LIKE '%{$q}%'
               OR c.razon_social LIKE '%{$q}%'
            ORDER BY d.id DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
