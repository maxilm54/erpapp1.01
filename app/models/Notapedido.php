<?php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once BASE_PATH . '/app/models/Producto.php';
require_once BASE_PATH . '/app/models/Cliente.php';
require_once BASE_PATH . '/app/models/Configuracion.php';
require_once BASE_PATH . '/app/models/Campopersonalizado.php';
require_once BASE_PATH . '/app/models/Ordenproduccion.php';
require_once BASE_PATH . '/app/models/Deposito.php';
require_once BASE_PATH . '/app/models/Reservaproductonp.php';

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
            SELECT np.*, c.razon_social,
                   (SELECT COUNT(*)
                    FROM notas_pedido_detalle d
                    WHERE d.nota_pedido_id = np.id) AS items_count,
                   (SELECT COALESCE(SUM(d.cantidad), 0)
                    FROM notas_pedido_detalle d
                    WHERE d.nota_pedido_id = np.id) AS total_pedida,
                   (SELECT COALESCE(SUM(rsd.cantidad), 0)
                    FROM remitos_salida rs
                    JOIN remitos_salida_detalle rsd ON rsd.remito_id = rs.id
                    WHERE rs.nota_pedido_id = np.id) AS total_remitida,
                   (SELECT COALESCE(p.nombre, d.descripcion)
                    FROM notas_pedido_detalle d
                    LEFT JOIN productos p ON p.id = d.producto_id
                    WHERE d.nota_pedido_id = np.id
                    ORDER BY d.id ASC
                    LIMIT 1) AS item_descripcion
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            ORDER BY
                CASE
                    WHEN np.estado = 'ANULADA' THEN 2
                    WHEN np.remitido = 'RemitidoCompleto' THEN 1
                    ELSE 0
                END ASC,
                CASE WHEN np.fecha_entrega IS NULL THEN 1 ELSE 0 END ASC,
                np.fecha_entrega ASC,
                np.created_at DESC
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
            (cliente_id, presupuesto_id, usuario_id, observaciones, fecha_entrega)
            VALUES (:c, :p, :u, :o, :fe)
        ");

        $stmt->execute([
            'c' => $data['cliente_id'],
            'p' => $presupuestoId,
            'u' => $data['usuario_id'],
            'o' => $data['observaciones'],
            'fe' => $data['fecha_entrega'] ?? null
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

            $recetaId = null;
            if ($productoId === null && !empty($item['receta_id'])) {
                $stmtReceta = $this->db->prepare(
                    "SELECT id FROM recetas WHERE id = ? AND producto_id IS NULL AND activa = '1'"
                );
                $stmtReceta->execute([(int)$item['receta_id']]);
                if ($stmtReceta->fetchColumn() !== false) {
                    $recetaId = (int)$item['receta_id'];
                }
            }

            $this->db->prepare("
                INSERT INTO notas_pedido_detalle
                (nota_pedido_id, producto_id, receta_id, descripcion, cantidad, precio)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $id,
                $productoId,
                $recetaId,
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
    /**
     * Aprueba la NP. Si $produccionPendiente es true, la NP queda marcada
     * para procesarse desde una receta en el submenu "NP pendientes de producir".
     */
    public function aprobar(int $id, bool $produccionPendiente = false): void
    {
        $this->db->prepare("
            UPDATE notas_pedido
            SET estado = 'APROBADA',
                produccion_pendiente = :pp
            WHERE id = :id AND estado = 'BORRADOR'
        ")->execute([
            'pp' => $produccionPendiente ? 1 : 0,
            'id' => $id,
        ]);
    }

    /**
     * Evalua si la NP se puede "levantar" en una receta para producir.
     * Requiere: un solo producto (sin lineas libres), producto existente y
     * activo. La cantidad devuelta es la pendiente de remitir.
     *
     * Tambien contempla el caso de un item libre con una receta atada: la NP
     * debe tener una sola linea, sin producto, con receta activa.
     */
    public function evaluarProduccionNota(int $id): array
    {
        $resultado = [
            'elegible'    => false,
            'es_libre'    => false,
            'motivo'      => '',
            'producto_id' => null,
            'producto'    => null,
            'cantidad'    => 0.0,
            'receta'      => null,
            'recetas'     => [],
        ];

        $lineas = $this->detalleConPendientes($id);

        if (empty($lineas)) {
            $resultado['motivo'] = 'La nota de pedido no tiene lineas.';
            return $resultado;
        }

        $productos = [];
        $lineasLibres = [];
        $cantidad = 0.0;
        foreach ($lineas as $linea) {
            $cantidad += (float)($linea['pendiente'] ?? 0);
            if (empty($linea['producto_id'])) {
                $lineasLibres[] = $linea;
            } else {
                $productos[(int)$linea['producto_id']] = true;
            }
        }

        if ($cantidad <= 0) {
            $resultado['motivo'] = 'La Nota de Pedido no tiene cantidad pendiente para producir.';
            return $resultado;
        }

        // Item libre: una sola linea sin producto y con una receta activa.
        if (empty($productos) && count($lineas) === 1 && count($lineasLibres) === 1) {
            $linea = $lineasLibres[0];
            $resultado['es_libre'] = true;
            $resultado['producto'] = $linea['nombre'];
            $resultado['cantidad'] = $cantidad;

            if (!(new Configuracion())->activa('usar_recetas_libres', false)) {
                $resultado['motivo'] = 'Las recetas para items libres no estan habilitadas en Configuraciones.';
                return $resultado;
            }
            $recetaId = (int)($linea['receta_id'] ?? 0);
            if ($recetaId <= 0) {
                $resultado['motivo'] = 'El item libre no tiene una receta cargada.';
                return $resultado;
            }

            $stmt = $this->db->prepare(
                "SELECT id, nombre, producto_id
                 FROM recetas
                 WHERE id = ? AND activa = '1'"
            );
            $stmt->execute([$recetaId]);
            $receta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$receta) {
                $resultado['motivo'] = 'La receta del item libre no existe o esta inactiva.';
                return $resultado;
            }

            $resultado['elegible']    = true;
            $resultado['es_libre']    = true;
            $resultado['producto_id'] = null;
            $resultado['producto']    = $linea['nombre'];
            $resultado['cantidad']    = $cantidad;
            $resultado['receta']      = $receta;
            $resultado['recetas']     = [$receta];
            return $resultado;
        }

        if (!empty($lineasLibres)) {
            $resultado['motivo'] = 'La nota de pedido tiene lineas libres o sin producto asociado.';
            return $resultado;
        }

        if (count($productos) !== 1) {
            $resultado['motivo'] = 'La nota de pedido tiene mas de un producto.';
            return $resultado;
        }

        $productoId = (int)array_key_first($productos);

        $stmt = $this->db->prepare("SELECT id, nombre, activo FROM productos WHERE id = ?");
        $stmt->execute([$productoId]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$producto || (int)$producto['activo'] !== 1) {
            $resultado['motivo'] = 'El producto de la nota de pedido no existe o esta inactivo.';
            return $resultado;
        }

        $stmt = $this->db->prepare(
            "SELECT id, nombre, producto_id
             FROM recetas
             WHERE producto_id = ? AND activa = '1'
             ORDER BY id DESC"
        );
        $stmt->execute([$productoId]);
        $recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado['producto_id'] = $productoId;
        $resultado['producto']    = $producto['nombre'];
        $resultado['cantidad']    = $cantidad;
        $resultado['recetas']     = $recetas;

        if (empty($recetas)) {
            $resultado['motivo'] = 'El producto no tiene una receta activa.';
            return $resultado;
        }
        if (count($recetas) > 1) {
            $resultado['motivo'] = 'El producto tiene mas de una receta activa.';
            return $resultado;
        }

        $resultado['elegible'] = true;
        $resultado['receta']   = $recetas[0];
        return $resultado;
    }

    /**
     * Orden de produccion ya generada desde esta NP (si existe).
     */
    public function ordenProduccionDeNota(int $notaPedidoId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT op.id, op.estado, op.cantidad,
                    COALESCE((
                        SELECT SUM(opd.cantidad_producida)
                        FROM orden_produccion_detalle opd
                        WHERE opd.orden_id = op.id
                          AND opd.confirma_produccion IS NOT NULL
                    ), 0) AS producido
             FROM ordenes_produccion op
             WHERE op.nota_pedido_id = ?
             ORDER BY op.id DESC
             LIMIT 1"
        );
        $stmt->execute([$notaPedidoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * NPs aprobadas marcadas para producir que todavia no tienen una OP.
     */
    public function npPendientesProduccion(): array
    {
        return $this->db->query("
            SELECT np.*, c.razon_social,
                   (SELECT GROUP_CONCAT(COALESCE(p.nombre, d.descripcion) SEPARATOR ' / ')
                    FROM notas_pedido_detalle d
                    LEFT JOIN productos p ON p.id = d.producto_id
                    WHERE d.nota_pedido_id = np.id) AS productos,
                   (SELECT COUNT(*) FROM notas_pedido_detalle d
                    WHERE d.nota_pedido_id = np.id) AS lineas,
                   (SELECT COUNT(DISTINCT d.producto_id) FROM notas_pedido_detalle d
                    WHERE d.nota_pedido_id = np.id AND d.producto_id IS NOT NULL) AS productos_distintos,
                   (SELECT COUNT(*) FROM notas_pedido_detalle d
                    WHERE d.nota_pedido_id = np.id AND d.producto_id IS NULL) AS lineas_libres
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            WHERE np.produccion_pendiente = 1
              AND np.estado = 'APROBADA'
              AND COALESCE(np.remitido, 'SinRemitir') <> 'RemitidoCompleto'
              AND NOT EXISTS (
                  SELECT 1 FROM ordenes_produccion op WHERE op.nota_pedido_id = np.id
              )
            ORDER BY np.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea la OP desde la receta del unico producto de la NP. La reserva de
     * materias primas la hace OrdenProduccion::crear().
     *
     * Si $recetaId viene informado se usa esa receta (puede ser de otro
     * producto, por ejemplo una variante con cambios minimos). Si no, se
     * exige que el producto tenga exactamente una receta activa.
     */
    public function crearOrdenProduccionDesdeNota(int $notaPedidoId, int $usuarioId, ?int $recetaId = null): int
    {
        $existente = $this->ordenProduccionDeNota($notaPedidoId);
        if ($existente) {
            throw new Exception(
                'La Nota de Pedido #' . $notaPedidoId . ' ya tiene la Orden de Produccion #'
                . (int)$existente['id'] . '.'
            );
        }

        $evaluacion = $this->evaluarProduccionNota($notaPedidoId);
        if (empty($evaluacion['producto_id']) && empty($evaluacion['es_libre'])) {
            throw new Exception('No se puede generar la orden de produccion: ' . $evaluacion['motivo']);
        }

        $recetaFinal = null;
        if (!empty($evaluacion['es_libre'])) {
            // El item libre usa siempre la receta atada a su linea.
            if (empty($evaluacion['elegible']) || empty($evaluacion['receta'])) {
                throw new Exception('No se puede generar la orden de produccion: ' . $evaluacion['motivo']);
            }
            $recetaFinal = $evaluacion['receta'];
        } elseif ($recetaId !== null && $recetaId > 0) {
            $stmt = $this->db->prepare(
                "SELECT id, nombre, producto_id
                 FROM recetas
                 WHERE id = ? AND activa = '1'"
            );
            $stmt->execute([$recetaId]);
            $recetaFinal = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$recetaFinal) {
                throw new Exception('La receta seleccionada no existe o esta inactiva.');
            }
        } else {
            if (!$evaluacion['elegible']) {
                throw new Exception('No se puede generar la orden de produccion: ' . $evaluacion['motivo']);
            }
            $recetaFinal = $evaluacion['receta'];
        }

        $stmt = $this->db->prepare(
            "SELECT estado, remitido, fecha_entrega, observaciones
             FROM notas_pedido
             WHERE id = ?"
        );
        $stmt->execute([$notaPedidoId]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$nota) {
            throw new Exception('La Nota de Pedido no existe.');
        }
        if ($nota['estado'] !== 'APROBADA') {
            throw new Exception('La Nota de Pedido debe estar aprobada antes de generar la orden de produccion.');
        }
        if (($nota['remitido'] ?? '') === 'RemitidoCompleto') {
            throw new Exception('La Nota de Pedido ya fue remitida por completo.');
        }

        $observaciones = 'Generada desde Nota de Pedido #' . $notaPedidoId;
        if (!empty($nota['observaciones'])) {
            $observaciones .= ': ' . $nota['observaciones'];
        }
        if (!empty($evaluacion['es_libre'])) {
            $observaciones .= ' - Item libre: ' . $evaluacion['producto'];
        } elseif (!empty($recetaFinal['producto_id'])
            && (int)$recetaFinal['producto_id'] !== (int)$evaluacion['producto_id']) {
            $observaciones .= ' - Receta "' . $recetaFinal['nombre'] . '" del producto #'
                . (int)$recetaFinal['producto_id'];
        }

        try {
            $ordenId = (new OrdenProduccion())->crear([
                'producto_id'    => !empty($evaluacion['producto_id']) ? (int)$evaluacion['producto_id'] : null,
                'producto_libre' => !empty($evaluacion['es_libre']) ? $evaluacion['producto'] : null,
                'receta_id'      => (int)$recetaFinal['id'],
                'cantidad'       => (float)$evaluacion['cantidad'],
                'f_entrega'      => $nota['fecha_entrega'] ?? null,
                'observaciones'  => $observaciones,
                'usuario_id'     => $usuarioId,
                'deposito_items' => [],
                'nota_pedido_id' => $notaPedidoId,
            ]);

            // Ya tiene OP: sale de la bandeja de pendientes de producir.
            $this->db->prepare("UPDATE notas_pedido SET produccion_pendiente = 0 WHERE id = ?")
                ->execute([$notaPedidoId]);

            return $ordenId;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                throw new Exception(
                    'La Nota de Pedido #' . $notaPedidoId . ' ya tiene una orden de produccion generada.'
                );
            }
            throw $e;
        }
    }

    /**
     * Reserva manual de productos para una NP aprobada (tenga o no una OP).
     * $items = [producto_id => cantidad].
     * Devuelve la cantidad de lineas reservadas.
     */
    public function reservarProductos(int $notaPedidoId, array $items, int $depositoOrigenId, int $usuarioId): int
    {
        $stmt = $this->db->prepare("SELECT estado, remitido FROM notas_pedido WHERE id = ?");
        $stmt->execute([$notaPedidoId]);
        $np = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$np) {
            throw new Exception('La Nota de Pedido no existe.');
        }
        if ($np['estado'] !== 'APROBADA') {
            throw new Exception('La Nota de Pedido debe estar aprobada para reservar productos.');
        }
        if (($np['remitido'] ?? '') === 'RemitidoCompleto') {
            throw new Exception('La Nota de Pedido ya fue remitida por completo.');
        }
        if ($depositoOrigenId <= 0) {
            throw new Exception('Debe seleccionar un deposito de origen.');
        }
        if (empty($items)) {
            throw new Exception('No se indicaron productos para reservar.');
        }

        $pendientes = [];
        foreach ($this->detalleConPendientes($notaPedidoId) as $linea) {
            if (empty($linea['producto_id'])) {
                continue;
            }
            $productoId = (int)$linea['producto_id'];
            $pendientes[$productoId] = ($pendientes[$productoId] ?? 0) + (float)$linea['pendiente'];
        }

        $reservaModel = new Reservaproductonp();
        $reservado = $reservaModel->resumenPorProducto($notaPedidoId);

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $reservadas = 0;
            foreach ($items as $productoId => $cantidad) {
                $productoId = (int)$productoId;
                $cantidad = (float)str_replace(',', '.', (string)$cantidad);
                if ($cantidad <= 0) {
                    continue;
                }
                if (!isset($pendientes[$productoId])) {
                    throw new Exception('El producto #' . $productoId . ' no pertenece a la Nota de Pedido.');
                }

                $yaReservado = (float)($reservado[$productoId]['disponible'] ?? 0);
                $disponibleParaReservar = $pendientes[$productoId] - $yaReservado;
                if ($cantidad > $disponibleParaReservar + 0.0005) {
                    throw new Exception(
                        'No se puede reservar ' . number_format($cantidad, 3, ',', '.')
                        . ' del producto #' . $productoId . ': pendiente '
                        . number_format($pendientes[$productoId], 3, ',', '.')
                        . ', ya reservado ' . number_format($yaReservado, 3, ',', '.') . '.'
                    );
                }

                $reservaModel->crearManual($notaPedidoId, $productoId, $cantidad, $depositoOrigenId, $usuarioId);
                $reservadas++;
            }

            if ($reservadas === 0) {
                throw new Exception('No se indicaron cantidades para reservar.');
            }

            if (!$inTransaction) {
                $this->db->commit();
            }
            return $reservadas;
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Libera las reservas vigentes de la NP y devuelve el stock al origen.
     */
    public function liberarReservas(int $notaPedidoId, int $usuarioId): float
    {
        return (new Reservaproductonp())->liberar($notaPedidoId, $usuarioId);
    }

    // ❌ Anular
    public function anular(int $id, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new Exception('Debe indicar el motivo de anulación');
        }

        $this->db->beginTransaction();
        try {
            // 1️⃣ Obtener NP para controlar que exista y su estado
            $stmt = $this->db->prepare("
                SELECT presupuesto_id, estado, remitido
                FROM notas_pedido
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $np = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$np) {
                throw new Exception('NP no encontrada');
            }
            if (($np['estado'] ?? '') === 'ANULADA') {
                throw new Exception('La Nota de Pedido ya está anulada.');
            }
            if (($np['remitido'] ?? '') === 'RemitidoCompleto') {
                throw new Exception(
                    'La Nota de Pedido está remitida por completo. No se puede anular. '
                    . 'Para revertir la entrega, generá una devolución desde el módulo Devoluciones.'
                );
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
            // 2️⃣ Liberar presupuesto si existe
            if (!empty($np['presupuesto_id'])) {
                $this->db->prepare("
                    UPDATE presupuestos
                    SET pre_asign = 'LIBRE'
                    WHERE id = ? AND estado <> 'ANULADO'
                ")->execute([$np['presupuesto_id']]);
            }

            // 3️⃣ Devolver al stock los productos reservados para esta NP
            (new Reservaproductonp())->liberar($id, (int)($_SESSION['user_id'] ?? 0));

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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

        $np['detalle'] = $this->detalleConPendientes($id);
        //aca deberia hacer el control de stock para determinar si la np la puedo remitar o no

        return $np;
    }

    /**
     * Detalle de la NP con cantidades remitidas y pendientes, sin efectos
     * secundarios (findWithPendientes avisa y devuelve null si ya esta completa).
     */
    public function detallePendientes(int $id): array
    {
        return $this->detalleConPendientes($id);
    }

    private function detalleConPendientes(int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT x.*, (x.pedida - x.remitida) AS pendiente
            FROM (
                SELECT
                    d.id AS detalle_id,
                    d.producto_id,
                    d.receta_id,
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                 SET producto_id = ?, descripcion = NULL, receta_id = NULL
                 WHERE id = ?"
            )->execute([$productoId, $detalleId]);

            $this->db->commit();
            return $productoId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Linea de detalle con los datos de su nota de pedido, para crearle una
     * receta libre desde el modulo de recetas.
     */
    public function detalleLinea(int $detalleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, np.estado AS nota_estado, np.remitido AS nota_remitido, np.cliente_id
            FROM notas_pedido_detalle d
            JOIN notas_pedido np ON np.id = d.nota_pedido_id
            WHERE d.id = ?
        ");
        $stmt->execute([$detalleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ata una receta libre a una linea de la nota de pedido.
     */
    public function vincularReceta(int $detalleId, int $recetaId): void
    {
        $this->db->prepare(
            "UPDATE notas_pedido_detalle
             SET receta_id = ?
             WHERE id = ? AND producto_id IS NULL"
        )->execute([$recetaId, $detalleId]);
    }

    /**
     * Marca la NP como pendiente de producir (usado al crear una receta libre
     * para una NP que ya estaba aprobada).
     */
    public function marcarProduccionPendiente(int $notaPedidoId): void
    {
        $this->db->prepare(
            "UPDATE notas_pedido
             SET produccion_pendiente = 1
             WHERE id = ? AND estado = 'APROBADA'"
        )->execute([$notaPedidoId]);
    }

    private function renderPdfHtml(int $id): string
    {
        $stmt = $this->db->prepare("
            SELECT np.id, np.created_at, np.observaciones, np.estado, np.fecha_entrega,
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

    /**
     * Notas de pedido con fecha de entrega comprometida que vencen dentro de N dias
     * (o ya vencidas) y que todavia no fueron avisadas hoy. Lo usa el cron
     * app/helpers/notificar_entregas.php. Solo NPs vivas y no remitidas por completo.
     */
    public function pendientesAvisoEntrega(int $dias = 7, bool $ignorarAviso = false): array
    {
        $dias = max(0, $dias);
        $filtroAviso = $ignorarAviso
            ? ''
            : 'AND (np.aviso_entrega_at IS NULL OR DATE(np.aviso_entrega_at) < CURDATE())';

        return $this->db->query("
            SELECT np.id, np.fecha_entrega, np.estado, np.remitido,
                   DATEDIFF(np.fecha_entrega, CURDATE()) AS dias_restantes,
                   c.razon_social
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            WHERE np.fecha_entrega IS NOT NULL
              AND np.estado IN ('BORRADOR','APROBADA')
              AND np.remitido <> 'RemitidoCompleto'
              AND np.fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)
              {$filtroAviso}
            ORDER BY np.fecha_entrega ASC, np.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca las notas de pedido avisadas para no repetir el mail el mismo dia.
     */
    public function marcarAvisadosEntrega(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE notas_pedido SET aviso_entrega_at = NOW() WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
    }
}
