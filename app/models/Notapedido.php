<?php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once BASE_PATH . '/app/models/Producto.php';
require_once BASE_PATH . '/app/models/Cliente.php';
require_once BASE_PATH . '/app/models/Configuracion.php';
require_once BASE_PATH . '/app/models/Campopersonalizado.php';

class NotaPedido extends Model
{
    protected string $table = 'notas_pedido';

    /**
     * Resuelve el producto de una linea segun el nomenclador del cliente
     * (misma logica que Presupuesto::resolverNomencladorLinea):
     * producto existente => variante SKU_BASE-nomenclador; producto libre con
     * el checkbox aplicar_nomenclador => se crea con sufijo en el SKU.
     */
    private function resolverNomencladorLinea(array $item, ?int $productoId, int $clienteId): ?int
    {
        $config = new Configuracion();
        if (!$config->activa('usar_nomenclador', true)) {
            return $productoId;
        }

        $nomenclador = (new Cliente())->nomencladorDe($clienteId);
        if ($nomenclador === null) {
            return $productoId;
        }

        $producto = new Producto();

        if ($productoId !== null) {
            return $producto->crearVarianteCliente($productoId, $nomenclador);
        }

        $descripcion = trim((string)($item['descripcion'] ?? ''));
        if ($descripcion !== '' && !empty($item['crear_producto']) && !empty($item['aplicar_nomenclador'])) {
            $productoId = $producto->crearDesdeLinea(
                $descripcion,
                (float)($item['precio'] ?? 0),
                1,
                $nomenclador
            );
        }

        return $productoId;
    }

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

        try {
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

            $cantidad = (float)($item['cantidad'] ?? 0);
            $precio   = (float)($item['precio'] ?? 0);
            if ($cantidad <= 0) continue;

            $productoId  = !empty($item['producto_id']) ? (int)$item['producto_id'] : null;
            $descripcion = mb_substr(trim((string)($item['descripcion'] ?? '')), 0, 255);

            // Nomenclador del cliente: variante existente o producto libre con sufijo
            $productoId = $this->resolverNomencladorLinea($item, $productoId, (int)$data['cliente_id']);

            // Producto libre marcado para agregarse a la base (sin nomenclador)
            if ($productoId === null && $descripcion !== '' && !empty($item['crear_producto'])) {
                $productoId  = (new Producto())->crearDesdeLinea($descripcion, $precio);
                $descripcion = null;
            }

            if ($productoId === null && $descripcion === '') continue;

            $this->db->prepare("
                INSERT INTO notas_pedido_detalle
                (nota_pedido_id, producto_id, descripcion, cantidad, precio)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $id,
                $productoId,
                $productoId === null ? $descripcion : null,
                $cantidad,
                $precio
            ]);
        }

        $this->db->commit();
        return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
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
            SELECT d.*, COALESCE(p.nombre, d.descripcion) AS nombre
            FROM notas_pedido_detalle d
            LEFT JOIN productos p ON p.id = d.producto_id
            WHERE d.nota_pedido_id = ?
            ORDER BY d.id
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
            SELECT id, razon_social, nomenclador FROM clientes WHERE activo=1 ORDER BY razon_social
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

        // Detalle con pendientes y stock disponible (soporta lineas libres sin producto)
        $stmt = $this->db->prepare("
            SELECT x.*, (x.pedida - x.remitida) AS pendiente
            FROM (
                SELECT
                    d.id AS detalle_id,
                    d.producto_id,
                    COALESCE(p.nombre, d.descripcion) AS nombre,
                    p.unidad_medida,
                    p.usa_serie,
                    um.nombre AS unidad_nombre,
                    d.cantidad AS pedida,
                    d.precio AS precio,
                    (d.cantidad * d.precio) AS total_linea,
                    COALESCE((
                        SELECT SUM(rsd.cantidad)
                        FROM remitos_salida rs
                        JOIN remitos_salida_detalle rsd ON rsd.remito_id = rs.id
                        WHERE rs.nota_pedido_id = d.nota_pedido_id
                          AND (
                               (d.producto_id IS NOT NULL AND rsd.producto_id = d.producto_id)
                               OR (d.producto_id IS NULL AND rsd.producto_id IS NULL AND rsd.descripcion = d.descripcion)
                          )
                    ), 0) AS remitida,
                    CASE WHEN d.producto_id IS NULL THEN 0 ELSE COALESCE((
                        SELECT SUM(
                            CASE
                                WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad
                                WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad
                                ELSE 0
                            END
                        )
                        FROM movimientos_stock ms
                        WHERE ms.producto_id = d.producto_id
                    ), 0) END AS stock_disponible
                FROM notas_pedido_detalle d
                LEFT JOIN productos p ON p.id = d.producto_id
                LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
                WHERE d.nota_pedido_id = ?
            ) x
            ORDER BY x.detalle_id
        ");
        $stmt->execute([$id]);
        $np['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //aca deberia hacer el control de stock para determinar si la np la puedo remitar o no  

        return $np;
    }

    /**
     * Convierte una linea libre de una NP en un producto real y la vincula.
     * Retorna el ID del producto creado.
     */
    public function agregarProductoADetalle(int $notaPedidoId, int $detalleId): int
    {
        $stmt = $this->db->prepare(
            "SELECT id, producto_id, descripcion, precio
             FROM notas_pedido_detalle
             WHERE id = ? AND nota_pedido_id = ?"
        );
        $stmt->execute([$detalleId, $notaPedidoId]);
        $linea = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$linea) {
            throw new Exception('La linea no pertenece a esta nota de pedido.');
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
                "UPDATE notas_pedido_detalle
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
            SELECT np.id, np.created_at, np.observaciones, np.estado,
                c.razon_social AS cliente, c.direccion, c.cuit
            FROM notas_pedido np
            LEFT JOIN clientes c ON c.id = np.cliente_id
            WHERE np.id = ?
        ");
        $stmt->execute([$id]);
        $np = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$np) {
            throw new Exception('Nota de Pedido no encontrada');
        }

        $stmt = $this->db->prepare("
            SELECT d.cantidad, d.precio, COALESCE(pr.nombre, d.descripcion) AS nombre
            FROM notas_pedido_detalle d
            LEFT JOIN productos pr ON pr.id = d.producto_id
            WHERE d.nota_pedido_id = ?
            ORDER BY d.id
        ");
        $stmt->execute([$id]);
        $np['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Campos personalizados con valores (incluye los heredados del presupuesto)
        $np['campos_personalizados'] = (new Campopersonalizado())->valoresVisibles('NOTA_PEDIDO', (int)$id);

        $empresa = ['nombre'=>'', 'cuit'=>'', 'email'=>'', 'telefono'=>'', 'direccion'=>'', 'logo'=>''];
        $logo = '';
        try {
            $masterDb = Database::getMaster();
            $tenantId = Auth::getTenantId();
            error_log("[PDF NotaPedido] tenantId detectado: " . var_export($tenantId, true));
            
            $empRow = $masterDb->query("SELECT id, nombre, cuit, email, telefono, direccion, logo FROM tenants WHERE id = " . (int)$tenantId)->fetch(PDO::FETCH_ASSOC);
            error_log("[PDF NotaPedido] Datos de tenant: " . var_export($empRow, true));
            
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
                error_log("[PDF NotaPedido] No se encontró datos para tenantId: " . var_export($tenantId, true));
            }
        } catch (Exception $e) {
            error_log("[PDF NotaPedido] Error cargando empresa: " . $e->getMessage() . " en " . __FILE__ . ":" . __LINE__);
        }

        ob_start();
        require BASE_PATH . '/app/views/pdf/nota_pedido.php';
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

        $dir = empresaStoragePath("notas_pedido");
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = sprintf('nota_pedido_%09d.pdf', $id);
        $path = "$dir/$filename";

        file_put_contents($path, $output);

        $this->db->prepare("
            UPDATE notas_pedido SET pdf_path = ? WHERE id = ?
        ")->execute([$path, $id]);

        return $path;
    }
}
