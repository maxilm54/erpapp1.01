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

    public function find(int $id)
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

    public function create(array $d, $img): bool
    {
        return $this->db->prepare(
            "INSERT INTO materias_primas
            (nombre, sku, id_unidadmedida, categoria, imagen)
            VALUES (:n,:s,:u,:c,:i)"
        )->execute([
            'n'=>$d['nombre'],
            's'=>$d['sku'],
            'u'=>$d['id_unidadmedida'],
            'c'=>$d['categoria'],
            'i'=>$img
        ]);
    }

    public function updateStock(int $id, float $cantidad): void
    {
        $this->db->prepare(
            "UPDATE materias_primas
             SET stock_actual = stock_actual + :c
             WHERE id = :id"
        )->execute(['c'=>$cantidad,'id'=>$id]);
    }

    public function search(string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre
            FROM materias_primas
            WHERE nombre LIKE :q
            ORDER BY nombre
            LIMIT 10
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
        return $this->db->query("SELECT id_categoria, categoria_nombre FROM categorias_mp_id ORDER BY categoria_nombre")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function umedidaMP(): array
    {
        return $this->db->query("SELECT id_medida,nombre,detalle FROM unidad_medida")->fetchAll(PDO::FETCH_ASSOC);
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
}