<?php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once BASE_PATH . '/app/models/Producto.php';

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
            "SELECT d.*, COALESCE(p.nombre, d.descripcion) AS nombre,
                    p.unidad_medida, um.nombre AS nombre_medida
             FROM presupuestos_detalle d
             LEFT JOIN productos p ON p.id = d.producto_id
             LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
             WHERE d.presupuesto_id = ?
             ORDER BY d.id"
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
                "INSERT INTO presupuestos (cliente_id, usuario_id, observaciones)
                 VALUES (:c, :u, :obs)"
            );
            $stmt->execute([
                'c'   => $data['cliente_id'],
                'u'   => $_SESSION['user_id'],
                'obs' => $data['observaciones'] ?? ''
            ]);

            $presupuestoId = (int)$this->db->lastInsertId();

            // Detalle
            $stmtDetalle = $this->db->prepare(
                "INSERT INTO presupuestos_detalle
                (presupuesto_id, producto_id, descripcion, cantidad, precio)
                VALUES (:p, :prod, :desc, :cant, :precio)"
            );

            foreach ($data['items'] as $item) {

                // 🛡️ Validaciones duras
                $cantidad = (float)($item['cantidad'] ?? 0);
                $precio   = (float)($item['precio'] ?? 0);
                if ($cantidad <= 0) {
                    continue;
                }

                $productoId  = !empty($item['producto_id']) ? (int)$item['producto_id'] : null;
                $descripcion = mb_substr(trim((string)($item['descripcion'] ?? '')), 0, 255);

                // Producto libre marcado para agregarse a la base
                if ($productoId === null && $descripcion !== '' && !empty($item['crear_producto'])) {
                    $productoId  = (new Producto())->crearDesdeLinea($descripcion, $precio);
                    $descripcion = null;
                }

                // Sin producto y sin descripcion no hay linea
                if ($productoId === null && $descripcion === '') {
                    continue;
                }

                $stmtDetalle->execute([
                    'p'     => $presupuestoId,
                    'prod'  => $productoId,
                    'desc'  => $productoId === null ? $descripcion : null,
                    'cant'  => $cantidad,
                    'precio'=> $precio
                ]);
            }

            $this->db->commit();
            $_SESSION['success'] = "Presupuesto creado correctamente.";
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
                 SET cliente_id = ?, observaciones = ?
                 WHERE id = ? AND estado = 'BORRADOR'"
            )->execute([
                $data['cliente_id'],
                $data['observaciones'] ?? '',
                $id
            ]);

            $this->db->prepare(
                "DELETE FROM presupuestos_detalle WHERE presupuesto_id = ?"
            )->execute([$id]);

            foreach ($data['items'] as $item) {
                $cantidad = (float)($item['cantidad'] ?? 0);
                $precio   = (float)($item['precio'] ?? 0);
                if ($cantidad <= 0) continue;

                $productoId  = !empty($item['producto_id']) ? (int)$item['producto_id'] : null;
                $descripcion = mb_substr(trim((string)($item['descripcion'] ?? '')), 0, 255);

                // Producto libre marcado para agregarse a la base
                if ($productoId === null && $descripcion !== '' && !empty($item['crear_producto'])) {
                    $productoId  = (new Producto())->crearDesdeLinea($descripcion, $precio);
                    $descripcion = null;
                }

                if ($productoId === null && $descripcion === '') continue;

                $this->db->prepare(
                    "INSERT INTO presupuestos_detalle
                     (presupuesto_id, producto_id, descripcion, cantidad, precio)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([
                    $id,
                    $productoId,
                    $productoId === null ? $descripcion : null,
                    $cantidad,
                    $precio
                ]);
            }

            $this->db->commit();
            $_SESSION['success'] = "Presupuesto actualizado correctamente.";
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

    public function getByCliente($clienteId)
    {
        $sql = "SELECT id, DATE(created_at) AS fecha 
                FROM presupuestos 
                WHERE cliente_id = ? 
                AND estado = 'APROBADO'
                AND pre_asign = 'LIBRE'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function findWithDetalle(int $id): ?array
    {
        // Cabecera
        $stmt = $this->db->prepare("
            SELECT p.id, p.cliente_id, p.created_at
            FROM presupuestos p
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$presupuesto) {
            return null;
        }

        // Detalle
        $stmt = $this->db->prepare("
            SELECT 
                d.id,
                d.producto_id,
                d.descripcion,
                COALESCE(pr.nombre, d.descripcion) AS nombre,
                pr.unidad_medida,
                um.nombre AS nombre_medida,
                d.cantidad,
                d.precio
            FROM presupuestos_detalle d
            LEFT JOIN productos pr ON pr.id = d.producto_id
            LEFT JOIN unidad_medida um ON um.id_medida = pr.unidad_medida
            WHERE d.presupuesto_id = ?
            ORDER BY d.id
        ");
        $stmt->execute([$id]);
        $presupuesto['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $presupuesto;
    }

    public function marcarAsignado(int $id): void
    {
        $this->db->prepare("
            UPDATE presupuestos
            SET pre_asign = 'ASIGNADO'
            WHERE id = ?
        ")->execute([$id]);
    }

    public function getNotaPedidoByPresupuesto(int $id)
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM notas_pedido
            WHERE presupuesto_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    /**
     * Convierte una linea libre de un presupuesto en un producto real y la vincula.
     * Retorna el ID del producto creado.
     */
    public function agregarProductoADetalle(int $presupuestoId, int $detalleId): int
    {
        $stmt = $this->db->prepare(
            "SELECT id, producto_id, descripcion, precio
             FROM presupuestos_detalle
             WHERE id = ? AND presupuesto_id = ?"
        );
        $stmt->execute([$detalleId, $presupuestoId]);
        $linea = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$linea) {
            throw new Exception('La linea no pertenece a este presupuesto.');
        }
        if (!empty($linea['producto_id'])) {
            throw new Exception('La linea ya tiene un producto asociado.');
        }

        $descripcion = trim((string)$linea['descripcion']);
        if ($descripcion === '') {
            throw new Exception('La linea libre no tiene descripcion.');
        }

        $this->db->beginTransaction();
        try {
            $productoId = (new Producto())->crearDesdeLinea($descripcion, (float)$linea['precio']);

            $this->db->prepare(
                "UPDATE presupuestos_detalle
                 SET producto_id = ?, descripcion = NULL
                 WHERE id = ?"
            )->execute([$productoId, $detalleId]);

            $this->db->commit();
            return $productoId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function renderPdfHtml(int $id): string
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.created_at, p.observaciones, p.estado,
                c.razon_social AS cliente, c.direccion, c.cuit
            FROM presupuestos p
            LEFT JOIN clientes c ON c.id = p.cliente_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$presupuesto) {
            throw new Exception('Presupuesto no encontrado');
        }

        $stmt = $this->db->prepare("
            SELECT d.cantidad, d.precio, COALESCE(pr.nombre, d.descripcion) AS nombre
            FROM presupuestos_detalle d
            LEFT JOIN productos pr ON pr.id = d.producto_id
            WHERE d.presupuesto_id = ?
            ORDER BY d.id
        ");
        $stmt->execute([$id]);
        $presupuesto['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $empresa = ['nombre'=>'', 'cuit'=>'', 'email'=>'', 'telefono'=>'', 'direccion'=>'', 'logo'=>''];
        $logo = '';
        try {
            $masterDb = Database::getMaster();
            $tenantId = Auth::getTenantId();
            // Aseguramos que el tenant_id sea el correcto
            if (!$tenantId) {
                error_log('[PDF Presupuesto] tenantId invalido: ' . var_export($tenantId, true));
            }
            $empRow = $masterDb->query("SELECT id, nombre, cuit, email, telefono, direccion, logo FROM tenants WHERE id = " . (int)$tenantId)->fetch(PDO::FETCH_ASSOC);
            if ($empRow) {
                $empresa['nombre']    = $empRow['nombre'] ?? 'Empresa';
                $empresa['cuit']      = $empRow['cuit'] ?? '';
                $empresa['email']     = $empRow['email'] ?? '';
                $empresa['telefono']  = $empRow['telefono'] ?? '';
                $empresa['direccion'] = $empRow['direccion'] ?? '';
                if (!empty($empRow['logo'])) {
                    $logoFile = BASE_PATH . "/public/uploads/img_config/empresa_{$empRow['id']}/{$empRow['logo']}";
                    if (file_exists($logoFile)) {
                        $logo = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile));
                        $empresa['logo'] = $logo;
                    }
                }
            } else {
                error_log('[PDF Presupuesto] No se encontro tenant id=' . $tenantId);
            }
        } catch (Exception $e) {
            error_log("[PDF Presupuesto] Error cargando empresa: " . $e->getMessage());
        }

        ob_start();
        require BASE_PATH . '/app/views/pdf/presupuesto.php';
        return ob_get_clean();
    }

    public function generarYGuardarPdf(int $id): string
    {
        $html = $this->renderPdfHtml($id);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $output = $dompdf->output();

        $dir = empresaStoragePath("presupuestos");
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = sprintf('presupuesto_%09d.pdf', $id);
        $path = "$dir/$filename";

        file_put_contents($path, $output);

        $this->db->prepare("
            UPDATE presupuestos SET pdf_path = ? WHERE id = ?
        ")->execute([$path, $id]);

        return $path;
    }
}    