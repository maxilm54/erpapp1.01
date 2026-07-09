<?php

use function Safe\error_log;

require_once BASE_PATH . '/app/core/Model.php';
class MateriaPrima extends Model
{
    public function all()
    {
        return $this->db->query(
            "SELECT * FROM materias_primas WHERE activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function find(int $id) // trae los datos de materia prima con cateogria y unidad de medida
    {
        $stmt = $this->db->prepare(
            "SELECT mp.*, cat.categoria_nombre AS nombre_categoria, um.nombre AS nombre_unidad, um.detalle AS detalle_unidad FROM materias_primas mp
            LEFT JOIN categorias_mp_id cat ON cat.id_categoria = mp.categoria
            LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
            WHERE mp.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $d, $img, $barcode, $tipo): bool // crea la maeteria prima y opcionalmente un código de barra asociado
    {
        try {
            $this->db->beginTransaction();
            $this->db->prepare("INSERT INTO materias_primas (nombre, sku, id_unidadmedida, categoria, imagen)
                VALUES (:n,:s,:u,:c,:i)"
                )->execute(['n'=>$d['nombre'],'s'=>$d['sku'],'u'=>$d['id_unidadmedida'],'c'=>$d['categoria'],'i'=>$img]);
            $mpId = $this->db->lastInsertId();
            if ($barcode) {
                $this->db->prepare("INSERT INTO materiaprima_codigos (materiaprima_id, codigo, tipo) VALUES (:mpId, :codigo, :tipo)")
                    ->execute(['mpId' => $mpId, 'codigo' => $barcode, 'tipo' => $tipo]);
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
            SELECT mp.id, mp.nombre, um.nombre AS nombre_medida
            FROM materias_primas mp
            LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
            WHERE mp.activo = 1
              AND mp.nombre LIKE :q
            ORDER BY mp.nombre
            LIMIT 20
        ");
        $stmt->execute(['q' => "%$q%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $d): bool
    {
        try {
            $this->db->beginTransaction();
            $this->db->prepare(
            "UPDATE materias_primas SET nombre = :n, sku = :s, id_unidadmedida = :u, categoria = :c WHERE id = :id"
            )->execute(['n'=>$d['nombre'],'s'=>$d['sku'],'u'=>$d['unidad_medida'],'c'=>$d['categoria'],'id'=>$id]);
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
