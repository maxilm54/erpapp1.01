<?php
    use Dompdf\Dompdf;
    use Dompdf\Options;
require_once BASE_PATH.'/app/models/Cuentacorrientecliente.php';
require_once BASE_PATH.'/app/models/Numerador.php';
require_once BASE_PATH.'/app/helpers/AsientoAutomatico.php';
class RemitoSalida extends Model
{
    protected string $table = 'remitos_salida';


    public function generarPdf(int $remitoId): void // esta funcion genera y descarga el pdf automaticamente y queda obsoleta
    {
        // Traer datos completos del remito
        $stmt = $this->db->prepare("
            SELECT r.id, r.observaciones,
                c.razon_social AS cliente,
                c.direccion, c.cuit,r.numero,r.created_at
            FROM remitos_salida r
            JOIN notas_pedido np ON np.id = r.nota_pedido_id
            JOIN clientes c ON c.id = np.cliente_id
            WHERE r.id = ?
        ");
        $stmt->execute([$remitoId]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$remito) {
            throw new Exception('Remito no encontrado');
        }

        $stmt = $this->db->prepare("
            SELECT d.cantidad, d.precio_unitario, COALESCE(p.nombre, d.descripcion) AS nombre
            FROM remitos_salida_detalle d
            LEFT JOIN productos p ON p.id = d.producto_id
            WHERE d.remito_id = ?
        ");
        $stmt->execute([$remitoId]);
        $remito['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $logoPath = empresaLogoPath();

        if ($logoPath && file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        } else {
            $logoBase64 = '';
        }
        // Render HTML
        ob_start();
        $logo = $logoBase64;
        $empresa = ['nombre'=>'', 'cuit'=>'', 'email'=>'', 'telefono'=>'', 'direccion'=>''];
        try {
            $masterDb = Database::getMaster();
            $empRow = $masterDb->query("SELECT nombre, cuit, email, telefono, direccion FROM tenants WHERE id = " . (int)(Auth::getTenantId() ?? 0))->fetch(PDO::FETCH_ASSOC);
            if ($empRow) $empresa = $empRow;
        } catch (Exception $e) {}
        require BASE_PATH.'/app/views/pdf/remito_salida.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true); // 🔴 OBLIGATORIO

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Descargar automáticamente
        $dompdf->stream(
            'remito_'.$remitoId.'.pdf',
            ['Attachment' => true]
        );
        exit;
    }
    private function renderPdfHtml(int $remitoId): string
    {
        // Cabecera (soporta remitos con o sin NP)
        $stmt = $this->db->prepare("
            SELECT r.id, r.created_at, r.observaciones, r.numero, r.pdf_path,
                COALESCE(r.cliente_nombre, c.razon_social) AS cliente,
                COALESCE(r.cliente_direccion, c.direccion) AS direccion,
                COALESCE(r.cliente_cuit, c.cuit) AS cuit
            FROM remitos_salida r
            LEFT JOIN notas_pedido np ON np.id = r.nota_pedido_id
            LEFT JOIN clientes c ON c.id = r.cliente_id OR (np.id IS NOT NULL AND c.id = np.cliente_id)
            WHERE r.id = ?
        ");
        $stmt->execute([$remitoId]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$remito) {
            throw new Exception('Remito no encontrado');
        }

        // Detalle (soporta items sin producto)
        $stmt = $this->db->prepare("
            SELECT d.cantidad, d.precio_unitario, d.descripcion, COALESCE(p.nombre, d.descripcion) AS nombre
            FROM remitos_salida_detalle d
            LEFT JOIN productos p ON p.id = d.producto_id
            WHERE d.remito_id = ?
        ");
        $stmt->execute([$remitoId]);
        $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $remito['detalle'] = $detalle;

        // Cargar empresa directamente del master DB - sin depender de Auth/tenant
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
            error_log("[PDF] Error cargando empresa: " . $e->getMessage());
        }

        // Variables disponibles en la vista
        ob_start();
        require BASE_PATH . '/app/views/pdf/remito_salida.php';
        return ob_get_clean();
    }
    public function generarYGuardarPdf(int $remitoId): string
    {
        $html = $this->renderPdfHtml($remitoId);

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
        $dir = empresaStoragePath("remitos/$year/$month");

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = sprintf('remito_%09d.pdf', $remitoId);
        $path = "$dir/$filename";

        file_put_contents($path, $output);

        $hash = hash_file('sha256', $path);

        $this->db->prepare("
            UPDATE remitos_salida
            SET pdf_path = ?, pdf_hash = ?
            WHERE id = ?
        ")->execute([$path, $hash, $remitoId]);

        return $path;
    }


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
                COALESCE(r.cliente_nombre, c.razon_social) AS cliente,
                u.nombre         AS usuario,
                CASE WHEN r.nota_pedido_id IS NULL THEN 'MANUAL' ELSE 'NP' END AS tipo
            FROM remitos_salida r
            LEFT JOIN notas_pedido np 
                ON np.id = r.nota_pedido_id
            LEFT JOIN clientes c 
                ON c.id = r.cliente_id OR (np.id IS NOT NULL AND c.id = np.cliente_id)
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
            SELECT r.*,c.razon_social AS cliente,u.nombre AS usuario, c.id AS cliente_id
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
                d.precio_unitario,
                COALESCE(p.nombre, d.descripcion) AS nombre,
                p.sku,
                d.descripcion
            FROM remitos_salida_detalle d
            LEFT JOIN productos p ON p.id = d.producto_id
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
     * Se agrega la funcionde cuenta corriente para el manejos del libro de cuentas
     */
    public function create(
        int $notaPedidoId,
        int $usuarioId,
        array $items,
        ?string $observaciones = null
        ): int{
        try {
            $numero = (new Numerador())->siguiente('REMITO');
            error_log($numero.'-'.__FILE__.'-'.__LINE__);
            $this->db->beginTransaction();
            $cc = new CuentaCorrienteCliente();

            // 1️⃣ Cabecera
            $stmt = $this->db->prepare("
                INSERT INTO remitos_salida
                (numero, nota_pedido_id, usuario_id, observaciones)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$numero, $notaPedidoId, $usuarioId, $observaciones]);

            $remitoId = (int)$this->db->lastInsertId();
            error_log('Remito de Salida creado con ID: ' . $remitoId . ' - ' . __FILE__ . ':' . __LINE__);

            // 2️⃣ Detalle + impacto stock
            foreach ($items as $productoId => $cantidad) {
                $cantidad = (float)$cantidad;
                if ($cantidad <= 0) continue;

                if (!is_numeric($productoId)) {
                    throw new Exception('ID de producto inválido');
                }

                $stock = $this->stockDisponibleProducto($productoId);
                
                if ($stock <= 0) {
                    $stmtProd = $this->db->prepare("SELECT nombre FROM productos WHERE id = ?");
                    $stmtProd->execute([$productoId]);
                    $nombreProd = $stmtProd->fetchColumn() ?? "ID: $productoId";
                    throw new Exception("El producto '$nombreProd' no tiene stock disponible (Stock: 0). No se puede remitar.");
                }

                if ($stock < $cantidad) {
                    $stmtProd = $this->db->prepare("SELECT nombre FROM productos WHERE id = ?");
                    $stmtProd->execute([$productoId]);
                    $nombreProd = $stmtProd->fetchColumn() ?? "ID: $productoId";
                    throw new Exception("Stock insuficiente para '$nombreProd'. Disponible: " . number_format($stock, 2) . ", Solicitado: " . number_format($cantidad, 2));
                }

                // Detalle
                $this->db->prepare("
                    INSERT INTO remitos_salida_detalle
                    (remito_id, producto_id, cantidad, precio_unitario)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $remitoId,
                    $productoId,
                    $cantidad,
                    0 // Se actualizará con el precio de la NP a continuación
                ]);
                //traer el valor del proiducto en esa np:
                $npdata= (new CuentaCorrienteCliente())->datonp($notaPedidoId,$productoId);
                $cliente_id=$npdata['cliente_id'];
                $precio=$npdata['precio'];
                $subtotal=$precio*$cantidad;

                // Obtener nombre del cliente para ctacte
                $clienteNombre = null;
                $stmtCli = $this->db->prepare("SELECT razon_social FROM clientes WHERE id = ?");
                $stmtCli->execute([$cliente_id]);
                $clienteNombre = $stmtCli->fetchColumn() ?: null;

                // Actualizar precio_unitario en el detalle
                $this->db->prepare("
                    UPDATE remitos_salida_detalle SET precio_unitario = ? 
                    WHERE remito_id = ? AND producto_id = ?
                ")->execute([$precio, $remitoId, $productoId]);
                //llamo al modelo cuenta corriente para registrar el debito
                $cc->registrarDebito(
                    $cliente_id,
                    $subtotal,
                    'REMITO',
                    $remitoId,
                    $usuarioId,
                    'Remito generado desde NP #' . $notaPedidoId,
                    $clienteNombre
                );
                // Generar asiento contable automático
                try {
                    $asientoAuto = new AsientoAutomatico();
                    $asientoAuto->ventaDebito($cliente_id, $subtotal, 'REMITO', $remitoId, $usuarioId);
                } catch (Exception $e) {
                    error_log("Error generando asiento para remito #{$remitoId}: " . $e->getMessage());
                }
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

            

            // ✅ Commit;

            $this->db->commit();
            return $remitoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al generar remito: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al generar remito: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al generar remito: ' . $e->getMessage();
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                remsal.id AS NumRem,
                COALESCE(remsal.cliente_nombre, c.razon_social) AS RazonSocial,
                np.id AS idNpRem, np.presupuesto_id AS PresNpRem, np.observaciones AS obsNpRem, 
                u.nombre AS UserRem, remsal.observaciones AS obsRemRem, remsal.created_at AS fecha, remsal.pdf_path AS pdf_path,
                COALESCE(remsal.cliente_cuit, c.cuit) AS cuit,
                COALESCE(remsal.cliente_direccion, c.direccion) AS direccion,
                COALESCE(remsal.cliente_email, c.email) AS email,
                COALESCE(remsal.cliente_telefono, c.telefono) AS telefono,
                COALESCE(remsal.cliente_localidad, c.localidad) AS localidad,
                remsal.cliente_nombre, remsal.cliente_cuit, remsal.cliente_direccion,
                remsal.cliente_email, remsal.cliente_telefono, remsal.cliente_localidad
             FROM remitos_salida remsal
             LEFT JOIN notas_pedido np ON np.id = remsal.nota_pedido_id
             LEFT JOIN clientes c ON c.id = remsal.cliente_id OR (np.id IS NOT NULL AND c.id = np.cliente_id)
             JOIN users u ON u.id = remsal.usuario_id
             WHERE remsal.id = ?"
        );
        $stmt->execute([$id]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "SELECT remdet.remito_id AS NumRem, remdet.producto_id AS idProdRem, remdet.cantidad AS CantRem, remdet.precio_unitario AS precioUnitario, COALESCE(p.nombre, remdet.descripcion) AS ProdRem, remdet.descripcion
             FROM remitos_salida_detalle remdet
             LEFT JOIN productos p ON p.id = remdet.producto_id
             WHERE remdet.remito_id = ?"
        );
        $stmt->execute([$id]);
        $remito['detalle'] = $stmt->fetchall(PDO::FETCH_ASSOC);

        if (!$remito) return null;
        return $remito;
    }
    
    public function firmar(int $remitoId, int $usuarioId): void
    {
        $this->db->prepare("
            UPDATE remitos_salida
            SET firmado = 1,
                firmado_por = ?,
                firmado_at = NOW()
            WHERE id = ?
        ")->execute([$usuarioId, $remitoId]);
    }

    public function findCompleto(int $id): ?array
    {
        // Cabecera + cliente (soporta remitos con o sin NP)
        $stmt = $this->db->prepare("
            SELECT r.*,
                COALESCE(r.cliente_nombre, c.razon_social) AS razon_social,
                COALESCE(c.email, r.cliente_email) AS email,
                COALESCE(c.direccion, r.cliente_direccion) AS direccion,
                npd.precio AS precio_produto, npd.cantidad AS prod_cantidad
            FROM remitos_salida r
            LEFT JOIN notas_pedido np ON np.id = r.nota_pedido_id
            LEFT JOIN notas_pedido_detalle npd ON npd.nota_pedido_id = r.nota_pedido_id
            LEFT JOIN clientes c ON c.id = r.cliente_id OR (np.id IS NOT NULL AND c.id = np.cliente_id)
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$remito) {
            return null;
        }

        // Detalle
        $stmt = $this->db->prepare("
            SELECT d.cantidad, d.precio_unitario, COALESCE(p.nombre, d.descripcion) AS nombre, d.descripcion
            FROM remitos_salida_detalle d
            LEFT JOIN productos p ON p.id = d.producto_id
            WHERE d.remito_id = ?
        ");
        $stmt->execute([$id]);
        $remito['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Estructura esperada por MailService
        return [
            'id' => $remito['id'],
            'numero' => $remito['numero'],
            'fecha' => $remito['created_at'],
            'pdf_path' => $remito['pdf_path'],
            'cliente' => [
                'razon_social' => $remito['razon_social'],
                'email' => $remito['email'],
                'direccion' => $remito['direccion']
            ],
            'detalle' => $remito['detalle']
        ];
    }

    // =====================================================
    // REMITO MANUAL (sin Nota de Pedido)
    // =====================================================

    /**
     * Crear remito manual (sin NP).
     * $clienteData puede tener:
     *   - 'cliente_id' (cliente existente)
     *   - O bien: cliente_nombre, cliente_cuit, cliente_direccion, cliente_email, cliente_telefono, cliente_localidad
     * $itemsManuales - array de items sin producto: [{descripcion, cantidad, precio}]
     */
    public function createManual(
        int $usuarioId,
        array $items,
        array $itemsManuales = [],
        array $clienteData = [],
        ?string $observaciones = null
    ): int {
        try {
            $this->db->beginTransaction();
            $numero = (new Numerador())->siguiente('REMITO');

            $clienteId = $clienteData['cliente_id'] ?? null;
            $clienteNombre = $clienteData['cliente_nombre'] ?? null;
            $clienteCuit = $clienteData['cliente_cuit'] ?? null;
            $clienteDireccion = $clienteData['cliente_direccion'] ?? null;
            $clienteEmail = $clienteData['cliente_email'] ?? null;
            $clienteTelefono = $clienteData['cliente_telefono'] ?? null;
            $clienteLocalidad = $clienteData['cliente_localidad'] ?? null;

            if ($clienteId) {
                $stmtCli = $this->db->prepare("SELECT * FROM clientes WHERE id = ?");
                $stmtCli->execute([$clienteId]);
                $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
                if ($cli) {
                    $clienteNombre = $cli['razon_social'];
                    $clienteCuit = $cli['cuit'];
                    $clienteDireccion = $cli['direccion'];
                    $clienteEmail = $cli['email'];
                    $clienteTelefono = $cli['telefono'];
                    $clienteLocalidad = $cli['localidad'];
                }
            }

            if (empty($clienteId) && !empty($clienteNombre)) {
                $stmtCheck = $this->db->prepare("SELECT id FROM clientes WHERE id = 9999");
                $stmtCheck->execute();
                if (!$stmtCheck->fetch()) {
                    $this->db->prepare("INSERT IGNORE INTO clientes (id, razon_social, cuit, activo) VALUES (9999, 'CLIENTE OCASIONAL', '00-00000000-0', 1)")->execute();
                }
                $clienteId = 9999;
            }

            if (empty($clienteNombre)) {
                throw new Exception('El nombre o razón social del cliente es obligatorio.');
            }

            if (empty($clienteEmail)) {
                $clienteEmail = 'contacto@alimentostriba.com.ar';
            }

            $stmt = $this->db->prepare("
                INSERT INTO remitos_salida
                (numero, nota_pedido_id, cliente_id, usuario_id, observaciones,
                 cliente_nombre, cliente_cuit, cliente_direccion, cliente_email,
                 cliente_telefono, cliente_localidad)
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero,
                $clienteId,
                $usuarioId,
                $observaciones,
                $clienteNombre,
                $clienteCuit ?: null,
                $clienteDireccion ?: null,
                $clienteEmail,
                $clienteTelefono ?: null,
                $clienteLocalidad ?: null,
            ]);

            $remitoId = (int)$this->db->lastInsertId();

            $totalRemito = 0;

            // 2️⃣ Detalle de productos con stock
            foreach ($items as $productoId => $itemData) {
                $cantidad = is_array($itemData) ? (float)($itemData['cantidad'] ?? 0) : (float)$itemData;
                $precioUnitario = is_array($itemData) ? (float)($itemData['precio'] ?? 0) : 0;
                if ($cantidad <= 0) continue;

                if (!is_numeric($productoId)) {
                    throw new Exception('ID de producto inválido');
                }

                $stock = $this->stockDisponibleProducto($productoId);

                if ($stock <= 0) {
                    $stmtProd = $this->db->prepare("SELECT nombre FROM productos WHERE id = ?");
                    $stmtProd->execute([$productoId]);
                    $nombreProd = $stmtProd->fetchColumn() ?? "ID: $productoId";
                    throw new Exception("El producto '$nombreProd' no tiene stock disponible (Stock: 0).");
                }

                if ($stock < $cantidad) {
                    $stmtProd = $this->db->prepare("SELECT nombre FROM productos WHERE id = ?");
                    $stmtProd->execute([$productoId]);
                    $nombreProd = $stmtProd->fetchColumn() ?? "ID: $productoId";
                    throw new Exception("Stock insuficiente para '$nombreProd'. Disponible: " . number_format($stock, 2) . ", Solicitado: " . number_format($cantidad, 2));
                }

                $this->db->prepare("
                    INSERT INTO remitos_salida_detalle
                    (remito_id, producto_id, cantidad, precio_unitario)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $remitoId,
                    $productoId,
                    $cantidad,
                    $precioUnitario
                ]);

                $totalRemito += $precioUnitario * $cantidad;

                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, producto_id, cantidad, observaciones, usuario_id)
                    VALUES ('SALIDA', 'REMITO_SALIDA', ?, ?, ?, ?, ?)
                ")->execute([
                    $remitoId,
                    $productoId,
                    $cantidad,
                    'Remito manual #' . $remitoId,
                    $usuarioId
                ]);
            }

            // 2b️⃣ Detalle de items manuales (servicios, sin stock)
            foreach ($itemsManuales as $item) {
                if (empty($item['descripcion']) || ($item['cantidad'] ?? 0) <= 0) continue;

                $descripcion = $item['descripcion'];
                $cantidad = (float)($item['cantidad'] ?? 0);
                $precioUnitario = (float)($item['precio'] ?? 0);

                $this->db->prepare("
                    INSERT INTO remitos_salida_detalle
                    (remito_id, producto_id, descripcion, cantidad, precio_unitario)
                    VALUES (?, NULL, ?, ?, ?)
                ")->execute([
                    $remitoId,
                    $descripcion,
                    $cantidad,
                    $precioUnitario
                ]);

                $totalRemito += $precioUnitario * $cantidad;
            }

            // 3️⃣ Registrar débito en cuenta corriente del cliente
            if ($clienteId && $totalRemito > 0) {
                require_once BASE_PATH . '/app/models/Cuentacorrientecliente.php';
                $ccModel = new CuentaCorrienteCliente();
                $ccModel->registrarDebito(
                    $clienteId,
                    $totalRemito,
                    'REMITO',
                    $remitoId,
                    $usuarioId,
                    'Remito manual #' . $remitoId,
                    $clienteNombre
                );

                // 4️⃣ Generar asiento contable automático (DEBE ctacte / HABER ventas)
                try {
                    require_once BASE_PATH . '/app/helpers/AsientoAutomatico.php';
                    $asientoAuto = new AsientoAutomatico();
                    $asientoAuto->ventaDebito($clienteId, $totalRemito, 'REMITO', $remitoId, $usuarioId, $clienteNombre);
                } catch (Exception $e) {
                    error_log("Error generando asiento para remito manual #{$remitoId}: " . $e->getMessage());
                }
            }

            $this->db->commit();
            return $remitoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al crear remito manual: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al crear remito manual: ' . $e->getMessage());
        }
    }

    /**
     * Versión simplificada de find() para remitos manuales.
     */
    public function findManual(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.id AS NumRem,
                r.numero,
                r.cliente_nombre AS RazonSocial,
                r.cliente_cuit AS cuit,
                r.cliente_direccion AS direccion,
                r.cliente_email AS email,
                r.cliente_telefono AS telefono,
                r.cliente_localidad AS localidad,
                r.nota_pedido_id AS idNpRem,
                r.observaciones AS obsRemRem,
                r.created_at AS fecha,
                r.pdf_path,
                u.nombre AS UserRem
            FROM remitos_salida r
            JOIN users u ON u.id = r.usuario_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$remito) return null;

        // Si tiene NP, traer datos del cliente de la NP
        if ($remito['idNpRem']) {
            $stmt2 = $this->db->prepare("
                SELECT c.razon_social, c.cuit, c.direccion, c.email, c.telefono, c.localidad
                FROM notas_pedido np
                JOIN clientes c ON c.id = np.cliente_id
                WHERE np.id = ?
            ");
            $stmt2->execute([$remito['idNpRem']]);
            $cli = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($cli) {
                $remito['RazonSocial'] = $cli['razon_social'];
                $remito['cuit'] = $cli['cuit'];
                $remito['direccion'] = $cli['direccion'];
                $remito['email'] = $cli['email'];
                $remito['telefono'] = $cli['telefono'];
                $remito['localidad'] = $cli['localidad'];
            }
        }

        // Detalle
        $stmt3 = $this->db->prepare("
            SELECT remdet.producto_id AS idProdRem, remdet.cantidad AS CantRem, remdet.precio_unitario AS precioUnitario, COALESCE(p.nombre, remdet.descripcion) AS ProdRem, remdet.descripcion
            FROM remitos_salida_detalle remdet
            LEFT JOIN productos p ON p.id = remdet.producto_id
            WHERE remdet.remito_id = ?
        ");
        $stmt3->execute([$id]);
        $remito['detalle'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        return $remito;
    }
}
