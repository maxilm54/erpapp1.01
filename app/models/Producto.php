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
    public function allConStock(): array
    {
        return $this->db->query("
            SELECT p.id, p.nombre, p.sku, p.precio_venta,
                COALESCE(SUM(
                    CASE
                        WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad
                        WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad
                    END
                ),0) AS stock
            FROM productos p
            LEFT JOIN movimientos_stock ms ON ms.producto_id = p.id
            WHERE p.activo = 1
            GROUP BY p.id, p.nombre, p.sku, p.precio_venta
            ORDER BY p.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar productos activos con stock por nombre/sku/codigo de barra.
     */
    public function searchConStock(string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->db->prepare("
            SELECT p.id, p.nombre, p.sku, p.precio_venta,
                GROUP_CONCAT(DISTINCT pc.codigo SEPARATOR ', ') AS codigos_barra,
                COALESCE(SUM(
                    CASE
                        WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad
                        WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad
                    END
                ),0) AS stock
            FROM productos p
            LEFT JOIN movimientos_stock ms ON ms.producto_id = p.id
            LEFT JOIN producto_codigos pc ON pc.producto_id = p.id
            WHERE p.activo = 1
              AND (p.nombre LIKE :q OR p.sku LIKE :q2 OR pc.codigo LIKE :q3)
            GROUP BY p.id, p.nombre, p.sku, p.precio_venta
            ORDER BY CASE WHEN COALESCE(SUM(CASE WHEN ms.tipo IN ('ENTRADA','AJUSTE') THEN ms.cantidad WHEN ms.tipo = 'SALIDA' THEN -ms.cantidad END),0) > 0 THEN 0 ELSE 1 END, p.nombre
            LIMIT 20
        ");
        $stmt->execute([':q' => $like, ':q2' => $like, ':q3' => $like]);
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
            (nombre, sku, descripcion, precio_venta, imagen, user_create, last_user_updated, unidad_medida)
            VALUES (:nombre, :sku, :descripcion, :precio, :imagen, :user_create, :last_user_updated, :unidad_medida)"
            );

            $stmt->execute([
                'nombre' => $data['nombre'],
                'sku' => $data['sku'],
                'descripcion' => $data['descripcion'],
                'precio' => $data['precio_venta'],
                'imagen' => $imagen,
                'user_create' => $_SESSION['user_id'],
                'last_user_updated' => $_SESSION['user_id'],
                'unidad_medida' => $data['unidad_medida']
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

    public function update(int $id, array $data, ?string $imagen = null): bool
    {
        try {
            $sql = "UPDATE productos SET
                        nombre = :nombre,
                        sku = :sku,
                        descripcion = :descripcion,
                        precio_venta = :precio,
                        unidad_medida = :unidad_medida,
                        last_user_updated = :user_update";

            $params = [
                'id' => $id,
                'nombre' => $data['nombre'],
                'sku' => $data['sku'],
                'descripcion' => $data['descripcion'],
                'precio' => $data['precio_venta'],
                'unidad_medida' => $data['unidad_medida'],
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