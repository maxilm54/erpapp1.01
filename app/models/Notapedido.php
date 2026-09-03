<?php

use Dompdf\Dompdf;
use Dompdf\Options;

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
            SELECT d.cantidad, d.precio, pr.nombre
            FROM notas_pedido_detalle d
            JOIN productos pr ON pr.id = d.producto_id
            WHERE d.nota_pedido_id = ?
        ");
        $stmt->execute([$id]);
        $np['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $empresa = ['nombre'=>'', 'cuit'=>'', 'email'=>'', 'telefono'=>'', 'direccion'=>'', 'logo'=>''];
        $logo = '';
        try {
            $masterDb = Database::getMaster();
            $tenantId = Auth::getTenantId();
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
            }
        } catch (Exception $e) {
            error_log("[PDF NP] Error cargando empresa: " . $e->getMessage());
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
