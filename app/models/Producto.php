<?php

require_once BASE_PATH . '/app/core/Model.php';

class Producto extends Model
{
    public function all(): array
    {
        return $this->db->query(
            "SELECT p.*, um.nombre AS nombre_medida, um.detalle AS detalle_medida 
             FROM productos p
             LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
             WHERE p.activo = 1 ORDER BY p.nombre"
        )->fetchAll();
    }

    /**
     * Productos activos con stock calculado.
     */
    public function allConStock(?int $depositoId = null): array
    {
        $depositoFilter = ' ON ms.producto_id = p.id';
        $params = [];
        if ($depositoId) {
            $depositoFilter = " ON ms.producto_id = p.id AND ms.deposito_id = :deposito_id";
            $params[':deposito_id'] = $depositoId;
        }
        $stmt = $this->db->prepare("
            SELECT p.id, p.nombre, p.sku, p.precio_venta,
                COALESCE(SUM(
                    CASE
                        WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad
                        WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad
                    END
                ),0) AS stock
            FROM productos p
            LEFT JOIN movimientos_stock ms{$depositoFilter}
            WHERE p.activo = 1
            GROUP BY p.id, p.nombre, p.sku, p.precio_venta
            ORDER BY p.nombre
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar productos activos con stock por nombre/sku/codigo de barra.
     */
    public function searchConStock(string $query, ?int $depositoId = null): array
    {
        $like = '%' . $query . '%';
        $depositoFilter = ' ON ms.producto_id = p.id';
        $params = [':q' => $like, ':q2' => $like, ':q3' => $like];
        if ($depositoId) {
            $depositoFilter = " ON ms.producto_id = p.id AND ms.deposito_id = :deposito_id";
            $params[':deposito_id'] = $depositoId;
        }
        $stmt = $this->db->prepare("
            SELECT p.id, p.nombre, p.sku, p.precio_venta, p.usa_serie,
                GROUP_CONCAT(DISTINCT pc.codigo SEPARATOR ', ') AS codigos_barra,
                COALESCE(SUM(
                    CASE
                        WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad
                        WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad
                    END
                ),0) AS stock
            FROM productos p
            LEFT JOIN movimientos_stock ms{$depositoFilter}
            LEFT JOIN producto_codigos pc ON pc.producto_id = p.id
            WHERE p.activo = 1
              AND (p.nombre LIKE :q OR p.sku LIKE :q2 OR pc.codigo LIKE :q3)
            GROUP BY p.id, p.nombre, p.sku, p.precio_venta, p.usa_serie
            ORDER BY CASE WHEN COALESCE(SUM(CASE WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad END),0) > 0 THEN 0 ELSE 1 END, p.nombre
            LIMIT 20
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, um.nombre AS nombre_medida, um.detalle AS detalle_medida FROM productos p
            LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
            WHERE p.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function stockByProductoId_movstock($id_prod): array
    {
        try{
            $stmt = $this->db->prepare(
                "SELECT
                    COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                    ),0) AS stock
                FROM movimientos_stock m WHERE m.producto_id = :id_prod ORDER BY m.created_at DESC"
            );
            $stmt->execute(['id_prod' => $id_prod]);
            $data = $stmt->fetch();
            error_log('Stock data for product ID '.$id_prod.': '.print_r($data, true).__FILE__.':'.__LINE__);
            return $data ? $data : ['stock' => 0];
        } catch (Exception $e) {
            error_log('Error fetching stock movements for product ID '.$id_prod.': '.$e->getMessage());
            $_SESSION['error'] = "Error al obtener los movimientos de stock: " . $e->getMessage();
            return [];
        }

    }

    public function getBarcodeByProductoId($id_prod): array
    {
        try{
            $stmt = $this->db->prepare(
                "SELECT * FROM producto_codigos WHERE producto_id = :id_prod"
            );
            $stmt->execute(['id_prod' => $id_prod]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error fetching barcodes for product ID '.$id_prod.': '.$e->getMessage());
            $_SESSION['error'] = "Error al obtener los códigos de barra: " . $e->getMessage();
            return [];
        }

    }

    public function create(array $data, ?string $imagen): int
    {
        try {
            $stmt = $this->db->prepare(
            "INSERT INTO productos
            (nombre, sku, descripcion, precio_venta, imagen, user_create, last_user_updated, unidad_medida, usa_serie)
            VALUES (:nombre, :sku, :descripcion, :precio, :imagen, :user_create, :last_user_updated, :unidad_medida, :usa_serie)"
            );

            $stmt->execute([
                'nombre' => $data['nombre'],
                'sku' => $data['sku'],
                'descripcion' => $data['descripcion'],
                'precio' => $data['precio_venta'],
                'imagen' => $imagen,
                'user_create' => $_SESSION['user_id'],
                'last_user_updated' => $_SESSION['user_id'],
                'unidad_medida' => $data['unidad_medida'],
                'usa_serie' => !empty($data['usa_serie']) ? 1 : 0
            ]);
            $_SESSION['success'] = "Producto creado correctamente.";
            error_log('Producto creado con ID '.$this->db->lastInsertId().' y data: '.print_r($data, true).' - '.__FILE__.':'.__LINE__);
            return (int)$this->db->lastInsertId();
        } catch (Exception $th) {
            $_SESSION['error'] = "Error al crear el producto: " . $th->getMessage();
            error_log('Error creating product: ' . $th->getMessage());
            return 0;
        }
    }

    /**
     * Crea un producto a partir de una linea libre de Presupuesto / Nota de Pedido.
     * Genera un SKU unico y lo marca como PRESENTACION con la unidad "Unidad" por defecto.
     * Si se pasa $sufijoNomenclador (del cliente del documento), el SKU queda LIBRE-XXXX-nomenclador.
     */
    public function crearDesdeLinea(string $nombre, float $precio, int $unidadMedida = 1, ?string $sufijoNomenclador = null): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new Exception('El nombre del producto libre es obligatorio.');
        }
        $nombre = mb_substr($nombre, 0, 150);

        // Si la unidad indicada no existe, usar la primera disponible
        $stmtUm = $this->db->prepare("SELECT id_medida FROM unidad_medida WHERE id_medida = ?");
        $stmtUm->execute([$unidadMedida]);
        if (!$stmtUm->fetchColumn()) {
            $primeraUm = $this->db->query("SELECT id_medida FROM unidad_medida ORDER BY id_medida LIMIT 1")->fetchColumn();
            $unidadMedida = $primeraUm !== false ? (int)$primeraUm : 1;
        }

        // Sufijo del nomenclador del cliente (se valida para que no rompa el SKU)
        $sufijoNomenclador = self::normalizarSufijo($sufijoNomenclador);
        $sufijoSku = $sufijoNomenclador !== null ? '-' . $sufijoNomenclador : '';

        // SKU unico generado (los productos libres no tienen SKU propio)
        do {
            $sku = 'LIBRE-' . strtoupper(bin2hex(random_bytes(4))) . $sufijoSku;
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM productos WHERE sku = ?");
            $stmt->execute([$sku]);
        } while ((int)$stmt->fetchColumn() > 0);

        $stmt = $this->db->prepare(
            "INSERT INTO productos
             (nombre, sku, descripcion, precio_venta, imagen, user_create, last_user_updated, unidad_medida, usa_serie, tipo)
             VALUES (:nombre, :sku, :descripcion, :precio, NULL, :user_create, :last_user_updated, :unidad_medida, 0, 'PRESENTACION')"
        );

        $stmt->execute([
            'nombre'            => $nombre,
            'sku'               => $sku,
            'descripcion'       => $sufijoNomenclador !== null
                ? 'Creado desde un documento (producto libre, nomenclador ' . $sufijoNomenclador . ')'
                : 'Creado desde un documento (producto libre)',
            'precio'            => $precio,
            'user_create'       => $_SESSION['user_id'] ?? 0,
            'last_user_updated' => $_SESSION['user_id'] ?? 0,
            'unidad_medida'     => $unidadMedida,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Normaliza un sufijo de nomenclador: solo letras/numeros/guion, sin espacios.
     * Devuelve NULL si el sufijo queda vacio.
     */
    public static function normalizarSufijo(?string $sufijo): ?string
    {
        $sufijo = strtoupper(trim((string)$sufijo));
        $sufijo = preg_replace('/[^A-Z0-9\-]/', '', $sufijo);
        return $sufijo !== '' ? $sufijo : null;
    }

    /**
     * Devuelve (buscando o creando) la variante de un producto para un cliente
     * con nomenclador: SKU = SKU_BASE-nomenclador. La variante clona nombre,
     * precio, unidad, serie y tipo del producto base.
     *
     * @return int ID del producto variante a usar en el documento.
     */
    public function crearVarianteCliente(int $productoBaseId, string $nomenclador): int
    {
        $sufijo = self::normalizarSufijo($nomenclador);
        if ($sufijo === null) {
            return $productoBaseId;
        }

        $stmt = $this->db->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$productoBaseId]);
        $base = $stmt->fetch();
        if (!$base) {
            return $productoBaseId;
        }

        $skuVariante = mb_substr($base['sku'], 0, 90) . '-' . $sufijo;

        // La variante ya existe? (activa o inactiva, se reactiva su uso)
        $stmt = $this->db->prepare("SELECT id FROM productos WHERE sku = ?");
        $stmt->execute([$skuVariante]);
        $idVariante = $stmt->fetchColumn();
        if ($idVariante !== false) {
            return (int)$idVariante;
        }

        try {
            $insert = $this->db->prepare(
                "INSERT INTO productos
                 (nombre, sku, descripcion, precio_venta, imagen, user_create, last_user_updated,
                  unidad_medida, usa_serie, tipo, producto_base_id)
                 VALUES (:nombre, :sku, :descripcion, :precio, :imagen, :user_create, :last_user_updated,
                  :unidad_medida, :usa_serie, 'PRESENTACION', :producto_base_id)"
            );
            $insert->execute([
                'nombre'            => $base['nombre'],
                'sku'               => $skuVariante,
                'descripcion'       => 'Variante de cliente (nomenclador ' . $sufijo . ') de ' . $base['sku'],
                'precio'            => $base['precio_venta'],
                'imagen'            => $base['imagen'] ?: null,
                'user_create'       => $_SESSION['user_id'] ?? 0,
                'last_user_updated' => $_SESSION['user_id'] ?? 0,
                'unidad_medida'     => $base['unidad_medida'],
                'usa_serie'         => $base['usa_serie'],
                'producto_base_id'  => $base['producto_base_id'] ?: $base['id'],
            ]);
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            // Carrera: otro proceso la creo a la vez -> la busco y devuelvo
            $stmt = $this->db->prepare("SELECT id FROM productos WHERE sku = ?");
            $stmt->execute([$skuVariante]);
            $idVariante = $stmt->fetchColumn();
            if ($idVariante !== false) {
                return (int)$idVariante;
            }
            error_log('[Producto] Error creando variante de cliente: ' . $e->getMessage());
            return $productoBaseId;
        }
    }

    public function update(int $id, array $data, ?string $imagen = null): bool
    {
        try {
            $sql = "UPDATE productos SET
                        nombre = :nombre,
                        sku = :sku,
                        descripcion = :descripcion,
                        precio_venta = :precio,
                        unidad_medida = :unidad_medida,
                        usa_serie = :usa_serie,
                        last_user_updated = :user_update";

            $params = [
                'id' => $id,
                'nombre' => $data['nombre'],
                'sku' => $data['sku'],
                'descripcion' => $data['descripcion'],
                'precio' => $data['precio_venta'],
                'unidad_medida' => $data['unidad_medida'],
                'usa_serie' => !empty($data['usa_serie']) ? 1 : 0,
                'user_update' => $_SESSION['user_id']
            ];

            if ($imagen !== null) {
                $sql .= ", imagen = :imagen";
                $params['imagen'] = $imagen;
            }

            $sql .= " WHERE id = :id";

            $_SESSION['success'] = "Producto actualizado correctamente.";
            error_log('Updating product ID '.$id.' with data: '.print_r($params, true));
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            $_SESSION['error'] = "Error al actualizar el producto: " . $e->getMessage();
            error_log('Error updating product: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE productos SET activo = 0 WHERE id = :id"
        )->execute(['id' => $id]);
    }

    /**
     * 📋 Listado del ABM de productos con stock calculado y buscador
     * (nombre, SKU, descripcion, precio y codigos de barra).
     * Con $inactivos = true devuelve los dados de baja (para reactivarlos).
     */
    public function listadoAbm(?string $buscar = null, bool $inactivos = false): array
    {
        $sql = "
            SELECT p.*, um.nombre AS nombre_medida, um.detalle AS detalle_medida,
                (SELECT COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ), 0)
                 FROM movimientos_stock m
                 WHERE m.producto_id = p.id) AS stock_total,
                (SELECT GROUP_CONCAT(pc.codigo SEPARATOR ', ')
                 FROM producto_codigos pc
                 WHERE pc.producto_id = p.id) AS codigos_barra
            FROM productos p
            LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
            WHERE p.activo = " . ($inactivos ? '0' : '1') . "
        ";
        $params = [];
        $buscar = trim((string)$buscar);
        if ($buscar !== '') {
            $sql .= "
              AND (p.nombre LIKE :q1
                   OR p.sku LIKE :q2
                   OR p.descripcion LIKE :q3
                   OR CAST(p.precio_venta AS CHAR) LIKE :q4
                   OR EXISTS (SELECT 1 FROM producto_codigos pc2
                              WHERE pc2.producto_id = p.id AND pc2.codigo LIKE :q5)";
            $like = '%' . $buscar . '%';
            $params = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like];

            // Precio escrito con separadores de miles o coma decimal (ej. 1.500,50)
            $soloDigitos = preg_replace('/[.,\s]/', '', $buscar);
            if ($soloDigitos !== '' && ctype_digit($soloDigitos)) {
                $sql .= " OR REPLACE(CAST(p.precio_venta AS CHAR), '.', '') LIKE :q6";
                $params[':q6'] = '%' . $soloDigitos . '%';
            }
            $sql .= ")";
        }
        $sql .= " ORDER BY p.nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cantidad de productos dados de baja (para el boton "Inactivos" del ABM).
     */
    public function contarInactivos(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM productos WHERE activo = 0")->fetchColumn();
    }

    /**
     * Reactiva un producto dado de baja (activo = 1).
     * No repone stock: el inventario sigue como se ajusto al inactivarlo.
     */
    public function reactivar(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE productos SET activo = 1, last_user_updated = :usuario
                 WHERE id = :id AND activo = 0"
            );
            $stmt->execute(['usuario' => (int)($_SESSION['user_id'] ?? 0), 'id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log('[Producto] Error al reactivar producto ID ' . $id . ': ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            return false;
        }
    }

    /**
     * Stock total del producto (event sourcing sobre movimientos_stock).
     */
    public function stockTotal(int $id): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                    WHEN tipo = 'SALIDA' THEN -cantidad
                END
            ), 0)
            FROM movimientos_stock
            WHERE producto_id = :id
        ");
        $stmt->execute(['id' => $id]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Motivos por los que un producto no puede inactivarse.
     * Bloquea solo mientras exista compromiso real:
     *  - presupuesto APROBADO cuya entrega sigue pendiente (sin NP o con NP no
     *    remitida completa),
     *  - NP APROBADA con entrega pendiente (`remitido` <> RemitidoCompleto),
     *  - remito con entrega pendiente de confirmar (`entregado` = 0),
     *  - OC APROBADA/PARCIAL con recepcion pendiente.
     */
    public function motivosBloqueoInactivacion(int $id): array
    {
        $motivos = [];

        $stmt = $this->db->prepare("
            SELECT DISTINCT pr.id
            FROM presupuestos_detalle d
            JOIN presupuestos pr ON pr.id = d.presupuesto_id
            WHERE d.producto_id = :id AND pr.estado = 'APROBADO'
              AND NOT EXISTS (
                  SELECT 1 FROM notas_pedido np2
                  WHERE np2.presupuesto_id = pr.id
                    AND np2.estado = 'APROBADA'
                    AND np2.remitido = 'RemitidoCompleto'
              )
            ORDER BY pr.id
        ");
        $stmt->execute(['id' => $id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $motivos[] = 'Está en el/los presupuesto(s) aprobado(s) ' . self::formatearIds($ids)
                . ' con entrega pendiente.';
        }

        $stmt = $this->db->prepare("
            SELECT DISTINCT np.id
            FROM notas_pedido_detalle d
            JOIN notas_pedido np ON np.id = d.nota_pedido_id
            WHERE d.producto_id = :id
              AND np.estado = 'APROBADA'
              AND COALESCE(np.remitido, 'SinRemitir') <> 'RemitidoCompleto'
            ORDER BY np.id
        ");
        $stmt->execute(['id' => $id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $motivos[] = 'Tiene entrega pendiente en la(s) NP ' . self::formatearIds($ids)
                . ' (no están remitidas completas).';
        }

        $stmt = $this->db->prepare("
            SELECT DISTINCT r.id
            FROM remitos_salida_detalle d
            JOIN remitos_salida r ON r.id = d.remito_id
            WHERE d.producto_id = :id AND COALESCE(r.entregado, 0) = 0
            ORDER BY r.id
        ");
        $stmt->execute(['id' => $id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $motivos[] = 'Está en el/los remito(s) con entrega pendiente de confirmar ' . self::formatearIds($ids) . '.';
        }

        $stmt = $this->db->prepare("
            SELECT DISTINCT oc.id
            FROM ordenes_compra_detalle d
            JOIN ordenes_compra oc ON oc.id = d.orden_compra_id
            WHERE d.producto_id = :id AND oc.estado IN ('APROBADA','PARCIAL')
            ORDER BY oc.id
        ");
        $stmt->execute(['id' => $id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $motivos[] = 'Está en la(s) OC aprobada(s) con recepción pendiente ' . self::formatearIds($ids) . ' (primero debe anularse la OC).';
        }

        return $motivos;
    }

    /**
     * Arma una lista corta de numeros de comprobante (#1, #2 y 3 mas).
     */
    private static function formatearIds(array $ids, int $max = 5): string
    {
        $ids = array_values(array_map('intval', $ids));
        $texto = '#' . implode(', #', array_slice($ids, 0, $max));
        if (count($ids) > $max) {
            $texto .= ' y ' . (count($ids) - $max) . ' más';
        }
        return $texto;
    }

    /**
     * Stock del producto desglosado por deposito y lote (solo filas distintas de 0).
     */
    public function stockPorUbicacion(int $productoId): array
    {
        $stmt = $this->db->prepare("
            SELECT deposito_id, lote_id,
                SUM(CASE
                        WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                        WHEN tipo = 'SALIDA' THEN -cantidad
                    END) AS stock
            FROM movimientos_stock
            WHERE producto_id = :id
            GROUP BY deposito_id, lote_id
            HAVING stock <> 0
        ");
        $stmt->execute(['id' => $productoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inactiva el producto (baja logica).
     *
     * - Bloquea la baja si hay compromiso (presupuesto aprobado, NP aprobada o con
     *   entrega pendiente, remito sin confirmar, OC aprobada pendiente de recepcion).
     * - Si hay stock y no se confirmo la baja, devuelve requiere_confirmacion para
     *   que la vista vuelva a preguntar.
     * - Al confirmar con stock, registra un ajuste de inventario a 0 por cada
     *   deposito/lote y pasa las series DISPONIBLES a BAJA.
     *
     * @return array{ok:bool, mensaje:string, requiere_confirmacion?:bool, stock?:float}
     */
    public function inactivar(int $id, bool $confirmarStock = false): array
    {
        $producto = $this->find($id);
        if (!$producto) {
            return ['ok' => false, 'mensaje' => 'Producto no encontrado.'];
        }
        if ((int)$producto['activo'] === 0) {
            return ['ok' => false, 'mensaje' => 'El producto ya se encuentra inactivo.'];
        }

        $bloqueos = $this->motivosBloqueoInactivacion($id);
        if (!empty($bloqueos)) {
            return [
                'ok' => false,
                'mensaje' => 'No se puede inactivar "' . $producto['nombre'] . '": ' . implode(' ', $bloqueos)
                    . ' Anule o finalice esos documentos antes de inactivarlo.',
            ];
        }

        $stock = $this->stockTotal($id);
        if ($stock > 0 && !$confirmarStock) {
            return [
                'ok' => false,
                'requiere_confirmacion' => true,
                'stock' => $stock,
                'mensaje' => 'El producto tiene stock (' . number_format($stock, 3, ',', '.') . '). '
                    . 'Si lo inactiva igual, se registrará un ajuste de inventario a 0.',
            ];
        }

        require_once BASE_PATH . '/app/models/Deposito.php';

        try {
            $this->db->beginTransaction();

            $ajustes = 0;
            if ($stock != 0) {
                $principalId = (new Deposito())->principalId();
                $motivo = 'Inactivación de producto: ajuste de inventario a 0';
                $obs = 'Ajuste automático al inactivar el producto ' . $producto['sku'];
                $usuarioId = (int)($_SESSION['user_id'] ?? 0);

                $stmtMov = $this->db->prepare("
                    INSERT INTO movimientos_stock
                        (tipo, origen, producto_id, deposito_id, lote_id, cantidad, motivo, observaciones, usuario_id)
                    VALUES
                        (:tipo, 'AJUSTE_STOCK', :producto_id, :deposito_id, :lote_id, :cantidad, :motivo, :observaciones, :usuario_id)
                ");

                foreach ($this->stockPorUbicacion($id) as $fila) {
                    $cant = (float)$fila['stock'];
                    if ($cant == 0) {
                        continue;
                    }
                    $stmtMov->execute([
                        ':tipo'         => $cant > 0 ? 'SALIDA' : 'ENTRADA',
                        ':producto_id'  => $id,
                        ':deposito_id'  => $fila['deposito_id'] !== null ? (int)$fila['deposito_id'] : ($principalId ?: null),
                        ':lote_id'      => $fila['lote_id'] !== null ? (int)$fila['lote_id'] : null,
                        ':cantidad'     => abs($cant),
                        ':motivo'       => $motivo,
                        ':observaciones'=> $obs,
                        ':usuario_id'   => $usuarioId,
                    ]);
                    $ajustes++;
                }
            }

            // Las unidades con numero de serie que quedaban disponibles pasan a BAJA
            $this->db->prepare(
                "UPDATE series SET estado = 'BAJA', updated_at = NOW()
                 WHERE producto_id = :id AND estado = 'DISPONIBLE' AND activo = 1"
            )->execute(['id' => $id]);

            $this->db->prepare(
                "UPDATE productos SET activo = 0, last_user_updated = :usuario WHERE id = :id"
            )->execute(['usuario' => (int)($_SESSION['user_id'] ?? 0), 'id' => $id]);

            $this->db->commit();

            $mensaje = 'Producto "' . $producto['nombre'] . '" inactivado correctamente.';
            if ($ajustes > 0) {
                $mensaje .= ' Se registró un ajuste de inventario a 0 en ' . $ajustes . ' ubicación(es).';
            }
            return ['ok' => true, 'mensaje' => $mensaje];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[Producto] Error al inactivar producto ID ' . $id . ': ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            return ['ok' => false, 'mensaje' => 'Error al inactivar el producto: ' . $e->getMessage()];
        }
    }

    /**
     * 🔍 Buscar productos para presupuestos / NP
     */
    public function search(string $q): array
    {
        $sql = "
            SELECT p.id, p.nombre, p.sku, p.precio_venta, um.nombre AS nombre_medida
            FROM productos p
            LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
            WHERE p.activo = 1
              AND ( p.nombre LIKE :q OR p.sku LIKE :q2)
            ORDER BY p.nombre
            LIMIT 20
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'q' => "%$q%",
            'q2' => "%$q%"
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paramStocks($idprod, $data){
        try {
            $this->db->beginTransaction();
            $sql = "UPDATE productos SET stock_minimo = :stock_minimo, stock_maximo = :stock_maximo, stock_critico = :stock_critico
                WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt-> bindValue(':id', $idprod, PDO::PARAM_INT);
            $stmt-> bindValue(':stock_minimo', $data['stock_minimo'], PDO::PARAM_STR);
            $stmt-> bindValue(':stock_maximo', $data['stock_maximo'], PDO::PARAM_STR);
            $stmt-> bindValue(':stock_critico', $data['stock_critico'], PDO::PARAM_STR);
            $stmt->execute();
            $this->db->commit();
            $_SESSION['success'] = "Parametros de stock actualizados correctamente.";
            header('Location: ' . BASE_URL . '/productos/stockdata/' . $idprod);
            exit;
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['error'] = "Error al modificar los parametros de stock: " . $e->getMessage();
            error_log('Error al modificar los parametros de stock: ' . $e->getMessage().__FILE__.':'.__LINE__);
            header('Location: ' . BASE_URL . '/productos/stockdata/' . $idprod);
            exit;
        }

    }

    public function unidadProd(): array
    {
        return $this->db->query("SELECT id_medida,nombre,detalle FROM unidad_medida")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSkus(array $skus): array
    {
        if (empty($skus)) return [];
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $stmt = $this->db->prepare("SELECT sku FROM productos WHERE sku IN ($placeholders)");
        $stmt->execute($skus);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function createBulk(array $productos): array
    {
        $imported = 0;
        $errors = [];
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "INSERT INTO productos (nombre, sku, descripcion, precio_venta, imagen, user_create, last_user_updated, unidad_medida)
                 VALUES (:nombre, :sku, :descripcion, :precio_venta, :imagen, :user_create, :last_user_updated, :unidad_medida)"
            );
            $userId = $_SESSION['user_id'];
            foreach ($productos as $row) {
                try {
                    $imagen = $this->copiarImagenPorDefectoBulk();
                    $stmt->execute([
                        'nombre'         => $row['nombre'],
                        'sku'            => $row['sku'],
                        'descripcion'    => $row['descripcion'] ?? '',
                        'precio_venta'   => $row['precio_venta'],
                        'imagen'         => $imagen,
                        'user_create'    => $userId,
                        'last_user_updated' => $userId,
                        'unidad_medida'  => $row['unidad_medida'] ?? 1,
                    ]);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = ['row' => $row['_row'] ?? '?', 'sku' => $row['sku'] ?? '', 'error' => $e->getMessage()];
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            $errors[] = ['row' => '-', 'sku' => '', 'error' => 'Error en la transacción: ' . $e->getMessage()];
        }
        return ['imported' => $imported, 'errors' => $errors];
    }

    private function copiarImagenPorDefectoBulk(): ?string
    {
        $origen = BASE_PATH . '/storage/imgpordefecto.jpg';
        if (!file_exists($origen)) return null;
        $uploadDir = empresaUploadPath('productos');
        $destino = $uploadDir . '/sin-imagen.jpg';
        if (file_exists($destino)) return 'sin-imagen.jpg';
        if (copy($origen, $destino)) return 'sin-imagen.jpg';
        return null;
    }

    public function getStockStatus(): array
    {
        $stmt = $this->db->query("
            SELECT 
                id,
                nombre,
                stock,
                stock_minimo,
                stock_maximo,
                stock_critico,
                CASE 
                    WHEN stock_critico > 0 AND stock <= stock_critico THEN 'critico'
                    WHEN stock_minimo > 0 AND stock <= stock_minimo THEN 'bajo'
                    WHEN stock_maximo > 0 AND stock >= stock_maximo THEN 'alto'
                    ELSE 'normal'
                END AS estado
            FROM productos
            WHERE activo = 1
            ORDER BY 
                CASE 
                    WHEN stock_critico > 0 AND stock <= stock_critico THEN 1
                    WHEN stock_minimo > 0 AND stock <= stock_minimo THEN 2
                    ELSE 3
                END,
                stock ASC
            LIMIT 50
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}