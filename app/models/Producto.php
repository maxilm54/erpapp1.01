<?php

require_once BASE_PATH . '/app/core/Model.php';

class Producto extends Model
{
    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM productos WHERE activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM productos WHERE id = :id"
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
        $stmt = $this->db->prepare(
            "INSERT INTO productos 
            (nombre, sku, descripcion, precio_venta, imagen,user_create,unidad_medida)
            VALUES (:nombre, :sku, :descripcion, :precio, :imagen,:user_create,:unidad_medida)"
        );

        $stmt->execute([
            'nombre' => $data['nombre'],
            'sku' => $data['sku'],
            'descripcion' => $data['descripcion'],
            'precio' => $data['precio_venta'],
            'imagen' => $imagen,
            'user_create' => $_SESSION['user_id'],
            'unidad_medida' => $data['unidad_medida']
        ]);
        $_SESSION['success'] = "Producto creado correctamente.";
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, ?string $imagen = null): bool //sin imagen y sin barcode
    {
        try {
            $sql = "UPDATE productos SET
                        nombre = :nombre,
                        sku = :sku,
                        descripcion = :descripcion,
                        precio_venta = :precio,
                        unidad_medida = :unidad_medida,
                        last_user_updated = :user_update
                    WHERE id = :id";

            /*if ($imagen) {
                $sql .= ", imagen = :imagen";
            }*/

            $params = [
                'id' => $id,
                'nombre' => $data['nombre'],
                'sku' => $data['sku'],
                'descripcion' => $data['descripcion'],
                'precio' => $data['precio_venta'],
                'unidad_medida' => $data['unidad_medida'],
                'user_update' => $_SESSION['user_id']
            ];

            /*if ($imagen) {
                $params['imagen'] = $imagen;
            }*/
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
            SELECT id, nombre, sku, precio_venta
            FROM productos
            WHERE activo = 1
              AND ( nombre LIKE :q OR sku LIKE :q2)
            ORDER BY nombre
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
}