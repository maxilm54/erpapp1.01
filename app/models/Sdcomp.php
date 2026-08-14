<?php
use Dompdf\Dompdf;
use Dompdf\Options;

require_once BASE_PATH . '/app/core/Model.php';

class Sdcomp extends Model
{
    protected string $table = 'movimientos_no_declarados';

    public function tablasExistentes(): bool
    {
        $stmt = $this->db->query("SHOW TABLES LIKE 'movimientos_no_declarados'");
        if ($stmt->rowCount() === 0) return false;

        $stmt = $this->db->query("SHOW COLUMNS FROM movimientos_no_declarados_detalle LIKE 'materia_prima_id'");
        return $stmt->rowCount() > 0;
    }


    public function create(
        string $tipo,
        array $items,
        ?int $clienteId = null,
        ?int $proveedorId = null,
        ?string $razonSocial = null,
        ?string $cuit = null,
        ?string $descripcion = null,
        ?string $observaciones = null
    ): int {
        $this->db->beginTransaction();

        try {
            $montoTotal = 0;
            foreach ($items as $item) {
                $montoTotal += (float)$item['cantidad'] * (float)($item['precio_unitario'] ?? 0);
            }

            $stmt = $this->db->prepare("
                INSERT INTO movimientos_no_declarados
                (tipo, cliente_id, proveedor_id, razon_social, cuit, descripcion, monto_total, estado, saldo_pendiente, observaciones, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?)
            ");
            $stmt->execute([
                $tipo, $clienteId, $proveedorId, $razonSocial, $cuit,
                $descripcion, $montoTotal, $montoTotal, $observaciones, $_SESSION['user_id']
            ]);
            $movId = (int)$this->db->lastInsertId();

            foreach ($items as $item) {
                $productoId = !empty($item['producto_id']) ? (int)$item['producto_id'] : null;
                $materiaPrimaId = !empty($item['materia_prima_id']) ? (int)$item['materia_prima_id'] : null;
                $cantidad = (float)$item['cantidad'];
                $precioUnitario = (float)($item['precio_unitario'] ?? 0);
                $tipoItem = $item['tipo_item'] ?? 'PRODUCTO';

                if ($cantidad <= 0) continue;

                $detProductoId = $tipoItem === 'PRODUCTO' ? $productoId : null;
                $detMPId = $tipoItem === 'MATERIAPRIMA' ? $materiaPrimaId : null;

                // Descripción para items manuales o extraer de producto/materia prima
                $itemDescripcion = $item['descripcion'] ?? null;
                if ($tipoItem === 'MANUAL' && !$itemDescripcion) {
                    continue; // Skip manual items without descripcion
                }
                if (!$itemDescripcion && $productoId) {
                    $stmtProd = $this->db->prepare("SELECT nombre FROM productos WHERE id = ?");
                    $stmtProd->execute([$productoId]);
                    $itemDescripcion = $stmtProd->fetchColumn();
                }
                if (!$itemDescripcion && $materiaPrimaId) {
                    $stmtMP = $this->db->prepare("SELECT nombre FROM materias_primas WHERE id = ?");
                    $stmtMP->execute([$materiaPrimaId]);
                    $itemDescripcion = $stmtMP->fetchColumn();
                }

                $this->db->prepare("
                    INSERT INTO movimientos_no_declarados_detalle
                    (mov_no_declarado_id, producto_id, materia_prima_id, descripcion, cantidad, precio_unitario)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$movId, $detProductoId, $detMPId, $itemDescripcion, $cantidad, $precioUnitario]);

                // Solo registrar movimiento de stock si hay producto_id o materia_prima_id
                if ($tipoItem === 'MATERIAPRIMA' && $materiaPrimaId) {
                    $this->db->prepare("
                        INSERT INTO movimientos_stock
                        (tipo, origen, referencia_id, producto_id, materia_prima_id, cantidad, motivo, usuario_id)
                        VALUES (?, ?, ?, NULL, ?, ?, ?, ?)
                    ")->execute(['SALIDA', 'AJUSTE_SDCOMP', $movId, $materiaPrimaId, $cantidad, 'Ajuste de stock - Comprobante #' . $movId, $_SESSION['user_id']]);
                } elseif ($tipoItem !== 'MANUAL' && $productoId) {
                    $this->db->prepare("
                        INSERT INTO movimientos_stock
                        (tipo, origen, referencia_id, producto_id, materia_prima_id, cantidad, motivo, usuario_id)
                        VALUES (?, ?, ?, ?, NULL, ?, ?, ?)
                    ")->execute(['SALIDA', 'AJUSTE_SDCOMP', $movId, $productoId, $cantidad, 'Ajuste de stock - Comprobante #' . $movId, $_SESSION['user_id']]);
                }
            }

            $this->db->commit();
            return $movId;

        } catch (Exception $e) {
            $this->db->rollBack();
            empresaLog('Error al crear comprobante: ' . $e->getMessage(), 'ERROR');
            throw new Exception('Error al crear comprobante: ' . $e->getMessage());
        }
    }

    public function all(array $filtros = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[] = 'm.tipo = ?';
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'm.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'm.created_at >= ?';
            $params[] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'm.created_at <= ?';
            $params[] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(m.razon_social LIKE ? OR m.descripcion LIKE ?)';
            $busqueda = '%' . $filtros['buscar'] . '%';
            $params[] = $busqueda;
            $params[] = $busqueda;
        }

        $sql = "
            SELECT m.*,
                COALESCE(c.razon_social, m.razon_social) AS cliente_nombre,
                COALESCE(p.razon_social, m.razon_social) AS proveedor_nombre,
                u.nombre AS usuario_nombre,
                (SELECT COUNT(*) FROM movimientos_no_declarados_pagos WHERE mov_no_declarado_id = m.id) AS cantidad_pagos
            FROM movimientos_no_declarados m
            LEFT JOIN clientes c ON c.id = m.cliente_id
            LEFT JOIN proveedores p ON p.id = m.proveedor_id
            JOIN users u ON u.id = m.usuario_id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY m.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                COALESCE(c.razon_social, m.razon_social) AS cliente_nombre,
                COALESCE(p.razon_social, m.razon_social) AS proveedor_nombre,
                u.nombre AS usuario_nombre
            FROM movimientos_no_declarados m
            LEFT JOIN clientes c ON c.id = m.cliente_id
            LEFT JOIN proveedores p ON p.id = m.proveedor_id
            JOIN users u ON u.id = m.usuario_id
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) return null;

        $stmt = $this->db->prepare("
            SELECT d.*,
                COALESCE(p.nombre, mp.nombre, d.descripcion) AS item_nombre,
                COALESCE(p.sku, mp.sku) AS sku,
                CASE 
                    WHEN d.producto_id IS NOT NULL THEN 'PRODUCTO' 
                    WHEN d.materia_prima_id IS NOT NULL THEN 'MATERIAPRIMA' 
                    ELSE 'MANUAL' 
                END AS tipo_item
            FROM movimientos_no_declarados_detalle d
            LEFT JOIN productos p ON p.id = d.producto_id
            LEFT JOIN materias_primas mp ON mp.id = d.materia_prima_id
            WHERE d.mov_no_declarado_id = ?
        ");
        $stmt->execute([$id]);
        $mov['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT pg.*, u.nombre AS usuario_nombre
            FROM movimientos_no_declarados_pagos pg
            JOIN users u ON u.id = pg.usuario_id
            WHERE pg.mov_no_declarado_id = ?
            ORDER BY pg.fecha DESC
        ");
        $stmt->execute([$id]);
        $mov['pagos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $mov;
    }

    public function registrarPago(int $movId, float $monto, ?string $descripcion = null): void
    {
        $mov = $this->find($movId);
        if (!$mov) throw new Exception('Comprobante no encontrado');
        if ($mov['estado'] === 'ANULADO') throw new Exception('No se puede registrar pago a un comprobante anulado');
        if ($mov['estado'] === 'COBRADO') throw new Exception('El comprobante ya esta completo');

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                INSERT INTO movimientos_no_declarados_pagos
                (mov_no_declarado_id, monto, descripcion, usuario_id)
                VALUES (?, ?, ?, ?)
            ")->execute([$movId, $monto, $descripcion, $_SESSION['user_id']]);

            $nuevoSaldo = (float)$mov['saldo_pendiente'] - $monto;
            $nuevoEstado = ($nuevoSaldo <= 0) ? 'COBRADO' : 'PARCIAL';

            $this->db->prepare("
                UPDATE movimientos_no_declarados
                SET saldo_pendiente = GREATEST(?, 0), estado = ?
                WHERE id = ?
            ")->execute([max($nuevoSaldo, 0), $nuevoEstado, $movId]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function anular(int $movId): void
    {
        $mov = $this->find($movId);
        if (!$mov) throw new Exception('Comprobante no encontrado');
        if ($mov['estado'] === 'ANULADO') throw new Exception('El comprobante ya esta anulado');

        $this->db->beginTransaction();
        try {
            foreach ($mov['detalle'] as $d) {
                $tipoStock = ($mov['tipo'] === 'VENTA') ? 'ENTRADA' : 'SALIDA';
                $origen = 'ANULACION_AJUSTE_SDCOMP';
                $motivo = 'Anulacion comprobante #' . $movId;

                $esMP = ($d['tipo_item'] === 'MATERIAPRIMA');

                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, materia_prima_id, cantidad, motivo, usuario_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $tipoStock, $origen, $movId,
                    $esMP ? null : $d['producto_id'],
                    $esMP ? $d['materia_prima_id'] : null, // Corregido: d['materia_prima_id'] si es MP
                    $d['cantidad'], $motivo, $_SESSION['user_id']
                ]);
            }

            $this->db->prepare("
                UPDATE movimientos_no_declarados
                SET estado = 'ANULADO', saldo_pendiente = 0
                WHERE id = ?
            ")->execute([$movId]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function dashboard(): array
    {
        $stmt = $this->db->query("
            SELECT tipo, estado, COUNT(*) AS cantidad,
                SUM(monto_total) AS total_monto,
                SUM(saldo_pendiente) AS total_pendiente
            FROM movimientos_no_declarados
            WHERE estado != 'ANULADO'
            GROUP BY tipo, estado
        ");
        $resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->query("
            SELECT razon_social, COUNT(*) AS cantidad,
                SUM(saldo_pendiente) AS pendiente
            FROM movimientos_no_declarados
            WHERE tipo = 'VENTA' AND estado IN ('PENDIENTE','PARCIAL')
            GROUP BY razon_social
            ORDER BY pendiente DESC
            LIMIT 10
        ");
        $deudores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->query("
            SELECT razon_social, COUNT(*) AS cantidad,
                SUM(monto_total) AS total
            FROM movimientos_no_declarados
            WHERE tipo = 'COMPRA' AND estado IN ('PENDIENTE','PARCIAL')
            GROUP BY razon_social
            ORDER BY total DESC
            LIMIT 10
        ");
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->query("
            SELECT
                SUM(CASE WHEN tipo='VENTA' AND estado!='ANULADO' THEN monto_total ELSE 0 END) AS total_ventas,
                SUM(CASE WHEN tipo='VENTA' AND estado IN ('PENDIENTE','PARCIAL') THEN saldo_pendiente ELSE 0 END) AS ventas_pendientes,
                SUM(CASE WHEN tipo='COMPRA' AND estado!='ANULADO' THEN monto_total ELSE 0 END) AS total_compras,
                SUM(CASE WHEN tipo='COMPRA' AND estado IN ('PENDIENTE','PARCIAL') THEN saldo_pendiente ELSE 0 END) AS compras_pendientes,
                SUM(CASE WHEN tipo='VENTA' AND estado='COBRADO' THEN monto_total ELSE 0 END) AS ventas_cobradas,
                SUM(CASE WHEN tipo='COMPRA' AND estado='COBRADO' THEN monto_total ELSE 0 END) AS compras_cobradas
            FROM movimientos_no_declarados
        ");
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'resumen' => $resumen,
            'deudores' => $deudores,
            'proveedores' => $proveedores,
            'totales' => $totales
        ];
    }

    public function getProductos(): array
    {
        $stmt = $this->db->query("
            SELECT p.id, p.nombre, p.sku, p.precio_venta AS precio,
                'PRODUCTO' AS tipo_item,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ), 0) AS stock_actual
            FROM productos p
            LEFT JOIN movimientos_stock m ON m.producto_id = p.id
            WHERE p.activo = 1
            GROUP BY p.id
            ORDER BY p.nombre
        ");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->query("
            SELECT mp.id, mp.nombre, mp.sku, mp.precio_actual AS precio,
                'MATERIAPRIMA' AS tipo_item,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ), 0) AS stock_actual
            FROM materias_primas mp
            LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
            WHERE mp.activo = 1
            GROUP BY mp.id
            ORDER BY mp.nombre
        ");
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($productos, $materias);
    }

    public function getClientes(): array
    {
        $stmt = $this->db->query("SELECT id, razon_social, cuit FROM clientes WHERE activo = 1 ORDER BY razon_social");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProveedores(): array
    {
        $stmt = $this->db->query("SELECT id, razon_social, cuit FROM proveedores WHERE activo = 1 ORDER BY razon_social");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchItems(string $q): array
    {
        $busqueda = '%' . $q . '%';

        $stmt = $this->db->prepare("
            SELECT p.id, p.nombre, p.sku, p.precio_venta AS precio,
                'PRODUCTO' AS tipo_item,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ), 0) AS stock_actual
            FROM productos p
            LEFT JOIN movimientos_stock m ON m.producto_id = p.id
            WHERE p.activo = 1 AND (p.nombre LIKE ? OR p.sku LIKE ?)
            GROUP BY p.id
            ORDER BY p.nombre
            LIMIT 20
        ");
        $stmt->execute([$busqueda, $busqueda]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT mp.id, mp.nombre, mp.sku, mp.precio_actual AS precio,
                'MATERIAPRIMA' AS tipo_item,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ), 0) AS stock_actual
            FROM materias_primas mp
            LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
            WHERE mp.activo = 1 AND (mp.nombre LIKE ? OR mp.sku LIKE ?)
            GROUP BY mp.id
            ORDER BY mp.nombre
            LIMIT 20
        ");
        $stmt->execute([$busqueda, $busqueda]);
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($productos, $materias);
    }

    private function renderPdfHtml(int $movId): string
    {
        $movimiento = $this->find($movId);
        if (!$movimiento) {
            throw new Exception('Movimiento no declarado no encontrado.');
        }

        $logoPath = empresaLogoPath();
        if ($logoPath && file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        } else {
            $logoBase64 = '';
        }

        // Variables disponibles en la vista
        ob_start();
        $mov = $movimiento;
        $logo = $logoBase64;
        $empresa = ['nombre'=>'', 'cuit'=>'', 'email'=>'', 'telefono'=>'', 'direccion'=>''];
        try {
            $masterDb = Database::getMaster();
            $empRow = $masterDb->query("SELECT nombre, cuit, email, telefono, direccion FROM tenants WHERE id = " . (int)(Auth::getTenantId() ?? 0))->fetch(PDO::FETCH_ASSOC);
            if ($empRow) $empresa = $empRow;
        } catch (Exception $e) {}
        require BASE_PATH . '/app/views/pdf/sdcomp_movimiento.php';
        return ob_get_clean();
    }

    public function generarYGuardarPdf(int $movId): string
    {
        $html = $this->renderPdfHtml($movId);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $output = $dompdf->output();

        $year = date('Y');
        $month = date('m');
        $dir = empresaStoragePath("sdcomp/$year/$month");

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = sprintf('sdcomp_%09d.pdf', $movId);
        $path = "$dir/$filename";

        file_put_contents($path, $output);

        $hash = hash_file('sha256', $path);

        $this->db->prepare("
            UPDATE movimientos_no_declarados
            SET pdf_path = ?, pdf_hash = ?
            WHERE id = ?
        ")->execute([$path, $hash, $movId]);

        return $path;
    }
}