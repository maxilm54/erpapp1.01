<?php
    use Dompdf\Dompdf;
    use Dompdf\Options;
require_once BASE_PATH.'/app/models/CuentaCorrienteCliente.php';
require_once BASE_PATH.'/app/models/Numerador.php';
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
            SELECT d.cantidad, p.nombre
            FROM remitos_salida_detalle d
            JOIN productos p ON p.id = d.producto_id
            WHERE d.remito_id = ?
        ");
        $stmt->execute([$remitoId]);
        $remito['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $logoPath = BASE_PATH . '/public/uploads/img_config/triba_log.png';

        if (!file_exists($logoPath)) {
            throw new Exception('Logo no encontrado');
        }

        $logoBase64 = 'data:image/png;base64,' . base64_encode(
            file_get_contents($logoPath)
        );
        // Render HTML
        ob_start();
        $logo = $logoBase64;
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
        // Cabecera
        $stmt = $this->db->prepare("
            SELECT r.id, r.created_at, r.observaciones,
                c.razon_social as cliente, c.direccion, c.cuit,r.numero,r.pdf_path
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

        // Detalle
        $stmt = $this->db->prepare("
            SELECT d.cantidad, p.nombre,(SELECT precio FROM notas_pedido_detalle npdd WHERE npdd.nota_pedido_id=rs.nota_pedido_id AND npdd.producto_id=d.producto_id) AS precioremitado
            FROM remitos_salida_detalle d
            JOIN productos p ON p.id = d.producto_id
            LEFT JOIN remitos_salida rs ON rs.id=d.remito_id
            LEFT JOIN notas_pedido_detalle npd ON npd.nota_pedido_id = rs.nota_pedido_id
            WHERE d.remito_id = ?
            GROUP BY d.producto_id
        ");
        $stmt->execute([$remitoId]);
        $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $logoPath = BASE_PATH . '/public/uploads/img_config/triba_log.png';
        $logoBase64 = 'data:image/png;base64,' . base64_encode(
            file_get_contents($logoPath)
        );
        $remito['detalle'] = $detalle; //precioporproductoenremito=precioremitado
        $logo = $logoBase64;
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
        $dir = BASE_PATH . "/storage/remitos/$year/$month";

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
                //traer el valor del proiducto en esa np:
                $npdata= (new CuentaCorrienteCliente())->datonp($notaPedidoId,$productoId);
                $cliente_id=$npdata['cliente_id'];
                $precio=$npdata['precio'];
                $subtotal=$precio*$cantidad;
                //llamo al modelo cuenta corriente para registrar el debito
                $cc->registrarDebito(
                    $cliente_id,
                    $subtotal,
                    'REMITO',
                    $remitoId,
                    $usuarioId,
                    'Remito generado desde NP #' . $notaPedidoId
                );
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
            throw new Exception('Error al generar remito: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al generar remito: ' . $e->getMessage();
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT  
                remsal.id AS NumRem, c.razon_social AS RazonSocial, np.id AS idNpRem, np.presupuesto_id AS PresNpRem,np.observaciones AS obsNpRem, 
                u.nombre AS UserRem,remsal.observaciones AS obsRemRem, remsal.created_at AS fecha,pdf_path
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
        // Cabecera + cliente
        $stmt = $this->db->prepare("
            SELECT r.*, c.razon_social, c.email, c.direccion, npd.precio AS precio_produto, npd.cantidad AS prod_cantidad
            FROM remitos_salida r
            JOIN notas_pedido np ON np.id = r.nota_pedido_id
            LEFT JOIN notas_pedido_detalle npd ON npd.nota_pedido_id = r.nota_pedido_id
            JOIN clientes c ON c.id = np.cliente_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$remito) {
            return null;
        }

        // Detalle
        $stmt = $this->db->prepare("
            SELECT d.cantidad, p.nombre
            FROM remitos_salida_detalle d
            JOIN productos p ON p.id = d.producto_id
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
}