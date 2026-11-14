<?php

use function Safe\error_log;

require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Materiaprimaespecificacion.php';
class MateriaPrima extends Model
{
    public function all()
    {
        return $this->listadoAbm();
    }

    /**
     * Listado del ABM de materias primas con stock calculado y buscador
     * (nombre, SKU, descripcion/categoria/observaciones, precio y codigos de barra).
     * Con $inactivos = true devuelve las dadas de baja (para reactivarlas).
     */
    public function listadoAbm(?string $buscar = null, bool $inactivos = false): array
    {
        $sql = "SELECT mp.*, cat.categoria_nombre,
                    um.nombre AS nombre_unidad,
                    umc.nombre AS nombre_unidad_compra,
                    esp.presentacion,
                    (SELECT COALESCE(SUM(
                        CASE
                            WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                        END
                    ), 0)
                     FROM movimientos_stock m
                     WHERE m.materia_prima_id = mp.id) AS stock
             FROM materias_primas mp
             LEFT JOIN categorias_mp_id cat ON cat.id_categoria = mp.categoria
             LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
             LEFT JOIN unidad_medida umc ON umc.id_medida = mp.id_unidadcompra
             LEFT JOIN materias_primas_especificaciones esp ON esp.materia_prima_id = mp.id
             WHERE mp.activo = " . ($inactivos ? '0' : '1');

        $params = [];
        $buscar = trim((string)$buscar);
        if ($buscar !== '') {
            $sql .= "
                 AND (mp.nombre LIKE :q1
                      OR mp.sku LIKE :q2
                      OR cat.categoria_nombre LIKE :q3
                      OR esp.obs LIKE :q4
                      OR CAST(mp.precio_actual AS CHAR) LIKE :q5
                      OR EXISTS (SELECT 1 FROM materiaprima_codigos mc
                                 WHERE mc.materiaprima_id = mp.id AND mc.codigo LIKE :q6)";
            $like = '%' . $buscar . '%';
            $params = [
                ':q1' => $like, ':q2' => $like, ':q3' => $like,
                ':q4' => $like, ':q5' => $like, ':q6' => $like,
            ];

            // Precio escrito con separadores de miles o coma decimal (ej. 1.500,50)
            $soloDigitos = preg_replace('/[.,\s]/', '', $buscar);
            if ($soloDigitos !== '' && ctype_digit($soloDigitos)) {
                $sql .= " OR REPLACE(CAST(mp.precio_actual AS CHAR), '.', '') LIKE :q7";
                $params[':q7'] = '%' . $soloDigitos . '%';
            }
            $sql .= ")";
        }

        $sql .= " ORDER BY mp.nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Cantidad de materias primas dadas de baja (para el boton "Inactivas" del ABM).
     */
    public function contarInactivas(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM materias_primas WHERE activo = 0")->fetchColumn();
    }

    /**
     * Reactiva una materia prima dada de baja (activo = 1).
     * No repone stock: el inventario sigue como se ajusto al inactivarla.
     */
    public function reactivar(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE materias_primas SET activo = 1 WHERE id = :id AND activo = 0"
            );
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error al reactivar materia prima ID $id: " . $e->getMessage() . " in " . __FILE__ . ":" . __LINE__);
            return false;
        }
    }

    public function find(int $id) // trae los datos de materia prima con categoria, unidad de medida y ficha tecnica
    {
        $stmt = $this->db->prepare(
            "SELECT mp.*, cat.categoria_nombre AS nombre_categoria, um.nombre AS nombre_unidad, um.detalle AS detalle_unidad,
                    umc.nombre AS nombre_unidad_compra, umc.detalle AS detalle_unidad_compra,
                    esp.parametro_texto_1, esp.parametro_numerico_1, esp.parametro_numerico_2,
                    esp.presentacion, esp.obs
            FROM materias_primas mp
            LEFT JOIN categorias_mp_id cat ON cat.id_categoria = mp.categoria
            LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
            LEFT JOIN unidad_medida umc ON umc.id_medida = mp.id_unidadcompra
            LEFT JOIN materias_primas_especificaciones esp ON esp.materia_prima_id = mp.id
            WHERE mp.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $d, $img, $barcode, $tipo): bool // crea la materia prima y opcionalmente un código de barra asociado
    {
        try {
            $this->db->beginTransaction();
            $tipoGestion = Materiaprimaespecificacion::normalizarTipoGestion($d['tipo_gestion'] ?? null);
            $unidadCompra = !empty($d['id_unidadcompra']) ? (int)$d['id_unidadcompra'] : null;
            $this->db->prepare("INSERT INTO materias_primas
                (nombre, sku, id_unidadmedida, id_unidadcompra, factor_conversion, categoria, imagen, usa_serie, tipo_gestion)
                VALUES (:n,:s,:u,:uc,:fc,:c,:i,:us,:tg)"
                )->execute([
                    'n' => $d['nombre'],
                    's' => $d['sku'],
                    'u' => $d['id_unidadmedida'] ?? $d['unidad_medida'] ?? null,
                    'uc' => $unidadCompra,
                    'fc' => self::factorDesdeDatos($d),
                    'c' => !empty($d['categoria']) ? $d['categoria'] : null,
                    'i' => $img,
                    'us' => !empty($d['usa_serie']) ? 1 : 0,
                    'tg' => $tipoGestion
                ]);
            $mpId = (int)$this->db->lastInsertId();
            if ($barcode) {
                $this->db->prepare("INSERT INTO materiaprima_codigos (materiaprima_id, codigo, tipo) VALUES (:mpId, :codigo, :tipo)")
                    ->execute(['mpId' => $mpId, 'codigo' => $barcode, 'tipo' => $tipo]);
            }
            if (Materiaprimaespecificacion::camposPresentes($d)) {
                (new Materiaprimaespecificacion())->guardar($mpId, $d);
            }
            $_SESSION['success'] = "Materia Prima creada correctamente.";
            error_log('Materia Prima creada con ID ' . $mpId . ' in ' . __FILE__ . ':' . __LINE__);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al crear materia prima: ' . $e->getMessage() . ' in ' . __FILE__ . ':' . __LINE__);
            $_SESSION['error'] = "Error al crear la materia prima. Por favor, inténtalo de nuevo.";
            return false;
        }
    }

    public function updateStock(int $id, float $cantidad): void //discontinuada, porque el stock se maneja desde movimientos de stock
    {
        /*$this->db->prepare(
            "UPDATE materias_primas
             SET stock_actual = stock_actual + :c
             WHERE id = :id"
        )->execute(['c'=>$cantidad,'id'=>$id]);*/
        $_SESSION['error']= "La función actualizar stock que ha usado está descontinuada. El stock se maneja a través de movimientos de stock. Avise al administrador: mailto:soporte@dmtech.com.ar";
        error_log('se llamo a la funcion updateStock para el producto ID ' . $id . ' con cantidad ' . $cantidad . ' in ' . __FILE__ . ':' . __LINE__);
    }

    public function search(string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT mp.id, mp.nombre, mp.tipo_gestion, mp.usa_serie,
                   mp.id_unidadmedida, mp.id_unidadcompra, mp.factor_conversion,
                   um.nombre AS nombre_medida,
                   COALESCE(umc.nombre, um.nombre) AS nombre_medida_compra
            FROM materias_primas mp
            LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
            LEFT JOIN unidad_medida umc ON umc.id_medida = mp.id_unidadcompra
            WHERE mp.activo = 1
              AND mp.nombre LIKE :q
            ORDER BY mp.nombre
            LIMIT 20
        ");
        $stmt->execute(['q' => "%$q%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Indica si el SKU ya existe (opcionalmente excluyendo una MP, para edicion).
     */
    public function skuExiste(string $sku, ?int $exceptoId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM materias_primas WHERE sku = :sku";
        $params = ['sku' => $sku];
        if ($exceptoId !== null) {
            $sql .= " AND id <> :id";
            $params['id'] = $exceptoId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Normaliza el factor recibido del formulario (acepta coma o punto).
     * Devuelve null si no es un numero mayor a cero.
     */
    public static function normalizarFactor($valor): ?float
    {
        $valor = trim(str_replace(',', '.', (string)$valor));
        if ($valor === '' || !is_numeric($valor)) {
            return null;
        }
        $numero = (float)$valor;
        return $numero > 0 ? round($numero, 6) : null;
    }

    /**
     * Factor canonico guardado en el formulario: unidades de stock por 1 unidad
     * de compra. Si no hay unidad de compra distinta, devuelve 1.
     */
    public static function factorDesdeDatos(array $datos): float
    {
        if (empty($datos['id_unidadcompra'])) {
            return 1.0;
        }
        $factor = (float)($datos['factor_conversion'] ?? 0);
        return $factor > 0 ? round($factor, 6) : 1.0;
    }

    /**
     * Indica si la MP se compra en una unidad distinta a la de stock y tiene
     * un factor valido.
     */
    public static function tieneConversion(array $mp): bool
    {
        if (empty($mp['id_unidadcompra'])) {
            return false;
        }
        if ((int)$mp['id_unidadcompra'] === (int)($mp['id_unidadmedida'] ?? 0)) {
            return false;
        }
        return (float)($mp['factor_conversion'] ?? 0) > 0;
    }

    /**
     * Unidades de stock que entran en 1 unidad de compra. Sin conversion: 1.
     */
    public static function factorCompra(array $mp): float
    {
        return self::tieneConversion($mp) ? (float)$mp['factor_conversion'] : 1.0;
    }

    /**
     * Convierte una cantidad comprada (kg, barras, rollos, bolsas) a la unidad
     * de stock/produccion.
     */
    public static function cantidadStockDesdeCompra(array $mp, float $cantidadCompra): float
    {
        return round($cantidadCompra * self::factorCompra($mp), 6);
    }

    /**
     * Convierte una cantidad de stock a la unidad de compra (para generar OC
     * desde faltantes de produccion).
     */
    public static function cantidadCompraDesdeStock(array $mp, float $cantidadStock): float
    {
        $factor = self::factorCompra($mp);
        return $factor > 0 ? round($cantidadStock / $factor, 6) : $cantidadStock;
    }

    /**
     * Convierte un precio por unidad de compra a precio por unidad de stock.
     * Ej: $1000/kg con factor 1.0101 m/kg -> $990/m.
     */
    public static function precioStockDesdeCompra(array $mp, float $precioCompra): float
    {
        $factor = self::factorCompra($mp);
        return $factor > 0 ? round($precioCompra / $factor, 6) : $precioCompra;
    }

    /**
     * Desglosa una cantidad de stock en paquetes completos y resto, usando la
     * presentacion de la ficha tecnica (ej: 6 m por barra).
     * Es solo una referencia: el stock sigue siendo la cantidad continua.
     */
    public static function desglosePresentacion(float $cantidad, ?float $presentacion): array
    {
        $cantidad = max(0, $cantidad);
        if ($presentacion === null || $presentacion <= 0) {
            return ['paquetes' => null, 'resto' => $cantidad, 'presentacion' => null];
        }

        $paquetes = (int)floor(round($cantidad / $presentacion, 6));
        $resto = round($cantidad - ($paquetes * $presentacion), 6);
        if (abs($resto) < 0.000001) {
            $resto = 0.0;
        }

        return [
            'paquetes' => $paquetes,
            'resto' => $resto,
            'presentacion' => (float)$presentacion,
        ];
    }

    /**
     * Texto de referencia del desglose: "9 × 6 M" o "8 × 6 M + 3 M".
     */
    public static function textoDesglosePresentacion(float $cantidad, ?float $presentacion, string $unidad = ''): string
    {
        $desglose = self::desglosePresentacion($cantidad, $presentacion);
        if ($desglose['presentacion'] === null) {
            return '';
        }

        $formato = static function (float $valor, int $decimales): string {
            $texto = number_format($valor, $decimales, ',', '.');
            return rtrim(rtrim($texto, '0'), ',');
        };

        $unidad = $unidad !== '' ? ' ' . $unidad : '';
        $texto = $desglose['paquetes'] . ' × ' . $formato($desglose['presentacion'], 2) . $unidad;
        if ($desglose['resto'] > 0) {
            $texto .= ' + ' . $formato($desglose['resto'], 3) . $unidad;
        }
        return $texto;
    }

    public function update(int $id, array $d, ?string $imagen = null): bool
    {
        try {
            $this->db->beginTransaction();
            $unidad = $d['id_unidadmedida'] ?? $d['unidad_medida'] ?? null;
            $tipoGestion = Materiaprimaespecificacion::normalizarTipoGestion($d['tipo_gestion'] ?? null);
            $unidadCompra = !empty($d['id_unidadcompra']) ? (int)$d['id_unidadcompra'] : null;
            $sql = "UPDATE materias_primas SET nombre = :n, sku = :s, id_unidadmedida = :u,
                    id_unidadcompra = :uc, factor_conversion = :fc, categoria = :c,
                    usa_serie = :us, tipo_gestion = :tg";
            $params = [
                'n' => $d['nombre'],
                's' => $d['sku'],
                'u' => $unidad,
                'uc' => $unidadCompra,
                'fc' => self::factorDesdeDatos($d),
                'c' => !empty($d['categoria']) ? $d['categoria'] : null,
                'us' => !empty($d['usa_serie']) ? 1 : 0,
                'tg' => $tipoGestion,
                'id' => $id
            ];

            if ($imagen !== null) {
                $sql .= ", imagen = :imagen";
                $params['imagen'] = $imagen;
            }

            $sql .= " WHERE id = :id";
            $this->db->prepare($sql)->execute($params);

            if (Materiaprimaespecificacion::camposPresentes($d)) {
                (new Materiaprimaespecificacion())->guardar($id, $d);
            }

            $this->db->commit();
            $_SESSION['success'] = "Materia Prima actualizada correctamente.";
            error_log('Materia Prima ID ' . $id . ' actualizada con éxito en ' . __FILE__ . ':' . __LINE__);
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al actualizar materia prima ID $id: " . $e->getMessage() . " in " . __FILE__ . ":" . __LINE__);
            $_SESSION['error'] = "Error al actualizar la materia prima. Por favor, inténtalo de nuevo.";
            return false;
        }
    }

    /**
     * Baja lógica de la materia prima (activo = 0). No se borra físicamente
     * porque puede tener movimientos, recetas y códigos asociados.
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE materias_primas SET activo = 0 WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $_SESSION['success'] = "Materia Prima inactivada correctamente.";
            error_log('Materia Prima ID ' . $id . ' inactivada en ' . __FILE__ . ':' . __LINE__);
            return true;
        } catch (Exception $e) {
            error_log("Error al inactivar materia prima ID $id: " . $e->getMessage() . " in " . __FILE__ . ":" . __LINE__);
            $_SESSION['error'] = "Error al inactivar la materia prima. Por favor, inténtalo de nuevo.";
            return false;
        }
    }

    public function categoriasMP(): array
    {
        try {
        $stmt = $this->db->query("SELECT id_categoria, categoria_nombre 
                                  FROM categorias_mp_id
                                  ORDER BY categoria_nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener categorías de materias primas: " . $e->getMessage() . " in " . __FILE__ . ":" . __LINE__);
        return [];
    }
    }
    public function umedidaMP(): array
    {
        try {
            $stmt = $this->db->query("SELECT id_medida,nombre,detalle FROM unidad_medida");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener unidades de medida: " . $e->getMessage() . " in " . __FILE__ . ":" . __LINE__);
            // Log o manejo de error
            return [];
        }
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
                FROM movimientos_stock m WHERE m.materia_prima_id = :id_prod ORDER BY m.created_at DESC"
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

    public function paramStocks($idprod, $data){
        try {
            $this->db->beginTransaction();
            $sql = "UPDATE materias_primas SET stock_minimo = :stock_minimo, stock_maximo = :stock_maximo, stock_critico = :stock_critico
                WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt-> bindValue(':id', $idprod, PDO::PARAM_INT);
            $stmt-> bindValue(':stock_minimo', $data['stock_minimo'], PDO::PARAM_STR);
            $stmt-> bindValue(':stock_maximo', $data['stock_maximo'], PDO::PARAM_STR);
            $stmt-> bindValue(':stock_critico', $data['stock_critico'], PDO::PARAM_STR);
            $stmt->execute();
            $this->db->commit();
            $_SESSION['success'] = "Parametros de stock actualizados correctamente.";
            error_log('Parametros de stock (min-cri-max) para producto ID ' . $idprod . ' actualizados con éxito en ' . __FILE__ . ':' . __LINE__);
            header('Location: ' . BASE_URL . '/materiasprimas/stockdata/' . $idprod);
            exit;
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['error'] = "Error al modificar los parametros de stock: " . $e->getMessage();
            error_log('Error al modificar los parametros de stock: ' . $e->getMessage().__FILE__.':'.__LINE__);
            header('Location: ' . BASE_URL . '/materiasprimas/stockdata/' . $idprod);
            exit;
        }

    }
    public function getBarcodesByMPId($id): array
    {
        $stmt = $this->db->prepare("SELECT id_mpcodigos, codigo, tipo, materiaprima_id FROM materiaprima_codigos WHERE materiaprima_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addBarcode($id_prod, $codigo, $tipo): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("INSERT INTO materiaprima_codigos (materiaprima_id, codigo, tipo) VALUES (:id_prod, :codigo, :tipo)");
            $stmt->bindValue(':id_prod', $id_prod, PDO::PARAM_INT);
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->execute();
            $this->db->commit();
            $_SESSION['success'] = "Código de barra agregado correctamente.";
            error_log('Código de barra agregado para producto ID ' . $id_prod . ' en ' . __FILE__ . ':' . __LINE__);
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Error al agregar código de barra para producto ID ' . $id_prod . ': ' . $e->getMessage() . ' in ' . __FILE__ . ':' . __LINE__);
            $_SESSION['error'] = "Error al agregar el código de barra. Por favor, inténtalo de nuevo.";
            return false;
        }
    }

    public function updateBarcodes($id_codigo, $codigo, $tipo): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE materiaprima_codigos SET codigo = :codigo, tipo = :tipo WHERE id_mpcodigos = :id_codigo");
            $stmt->bindValue(':id_codigo', $id_codigo, PDO::PARAM_INT);
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->execute();
            $this->db->commit();
            $_SESSION['success'] = "Código de barra actualizado correctamente.";
            error_log('Código de barra ID ' . $id_codigo . ' actualizado en ' . __FILE__ . ':' . __LINE__);
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Error al actualizar código de barra ID ' . $id_codigo . ': ' . $e->getMessage() . ' in ' . __FILE__ . ':' . __LINE__);
            $_SESSION['error'] = "Error al actualizar el código de barra. Por favor, inténtalo de nuevo.";
            return false;
        }
    }

    /**
     * Obtener el ultimo precio de compra de una materia prima desde ordenes de compra,
     * convertido a precio por unidad de STOCK (ver factor_conversion).
     */
    public function getUltimoPrecioCompra(int $materiaPrimaId): ?float
    {
        $stmt = $this->db->prepare("
            SELECT ocd.precio_unitario,
                   mp.id_unidadmedida, mp.id_unidadcompra, mp.factor_conversion
            FROM ordenes_compra_detalle ocd
            JOIN ordenes_compra oc ON oc.id = ocd.orden_compra_id
            JOIN materias_primas mp ON mp.id = ocd.materia_prima_id
            WHERE ocd.materia_prima_id = :mp_id
              AND oc.estado IN ('APROBADA','RECIBIDA','PARCIAL')
            ORDER BY oc.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':mp_id' => $materiaPrimaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return null;
        }
        return self::precioStockDesdeCompra($result, (float)$result['precio_unitario']);
    }

    /**
     * Obtener ultimos precios de compra para multiples materias primas (batch),
     * convertidos a precio por unidad de STOCK.
     */
    public function getUltimosPreciosCompra(array $materiaPrimaIds): array
    {
        if (empty($materiaPrimaIds)) return [];

        $placeholders = implode(',', array_fill(0, count($materiaPrimaIds), '?'));
        $stmt = $this->db->prepare("
            SELECT ocd.materia_prima_id, ocd.precio_unitario,
                   mp.id_unidadmedida, mp.id_unidadcompra, mp.factor_conversion
            FROM ordenes_compra_detalle ocd
            JOIN ordenes_compra oc ON oc.id = ocd.orden_compra_id
            JOIN materias_primas mp ON mp.id = ocd.materia_prima_id
            WHERE ocd.materia_prima_id IN ($placeholders)
              AND oc.estado IN ('APROBADA','RECIBIDA','PARCIAL')
            ORDER BY oc.created_at DESC
        ");
        $stmt->execute($materiaPrimaIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $precios = [];
        foreach ($rows as $row) {
            $mpId = (int)$row['materia_prima_id'];
            if (!isset($precios[$mpId])) {
                $precios[$mpId] = self::precioStockDesdeCompra($row, (float)$row['precio_unitario']);
            }
        }
        return $precios;
    }

    public function findSkus(array $skus): array
    {
        if (empty($skus)) return [];
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $stmt = $this->db->prepare("SELECT sku FROM materias_primas WHERE sku IN ($placeholders)");
        $stmt->execute($skus);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function createBulk(array $items): array
    {
        $imported = 0;
        $errors = [];
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "INSERT INTO materias_primas (nombre, sku, id_unidadmedida, categoria, imagen, tipo_gestion)
                 VALUES (:nombre, :sku, :id_unidadmedida, :categoria, :imagen, :tipo_gestion)"
            );
            $barcodeStmt = $this->db->prepare(
                "INSERT INTO materiaprima_codigos (materiaprima_id, codigo, tipo)
                 VALUES (:materiaprima_id, :codigo, :tipo)"
            );
            foreach ($items as $row) {
                try {
                    $imagen = $this->copiarImagenPorDefectoBulk();
                    $stmt->execute([
                        'nombre'         => $row['nombre'],
                        'sku'            => $row['sku'],
                        'id_unidadmedida' => $row['id_unidadmedida'] ?? 1,
                        'categoria'      => $row['categoria'] ?? null,
                        'imagen'         => $imagen,
                        'tipo_gestion'   => Materiaprimaespecificacion::normalizarTipoGestion($row['tipo_gestion'] ?? null),
                    ]);
                    $mpId = $this->db->lastInsertId();
                    if (!empty($row['barcode'])) {
                        $barcodeStmt->execute([
                            'materiaprima_id' => $mpId,
                            'codigo'          => $row['barcode'],
                            'tipo'            => $row['barcode_tipo'] ?? 'INTERNO',
                        ]);
                    }
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
        $uploadDir = empresaUploadPath('materiasprimas');
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
                stock_actual,
                stock_minimo,
                stock_maximo,
                stock_critico,
                CASE 
                    WHEN stock_critico > 0 AND stock_actual <= stock_critico THEN 'critico'
                    WHEN stock_minimo > 0 AND stock_actual <= stock_minimo THEN 'bajo'
                    WHEN stock_maximo > 0 AND stock_actual >= stock_maximo THEN 'alto'
                    ELSE 'normal'
                END AS estado
            FROM materias_primas
            WHERE activo = 1
            ORDER BY 
                CASE 
                    WHEN stock_critico > 0 AND stock_actual <= stock_critico THEN 1
                    WHEN stock_minimo > 0 AND stock_actual <= stock_minimo THEN 2
                    ELSE 3
                END,
                stock_actual ASC
            LIMIT 50
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
