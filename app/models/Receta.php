<?php
class Receta extends Model
{
    protected string $table = 'recetas';
    public function all(): array
    {
        return $this->db->query("
            SELECT r.*, p.nombre AS producto,
            GROUP_CONCAT(rd.materia_prima_id SEPARATOR ' - ') AS id_mat_prim,
            GROUP_CONCAT(mp.nombre SEPARATOR ' - ') AS mat_prim
            FROM recetas r
            JOIN productos p ON p.id = r.producto_id
            LEFT JOIN recetas_detalle rd ON rd.receta_id=r.id
            LEFT JOIN materias_primas mp ON mp.id=rd.materia_prima_id
            GROUP BY r.id, r.nombre
            ORDER BY r.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(int $productoId,string $nombre, array $items, string $procedimiento = null): int
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare("
                INSERT INTO recetas (producto_id,nombre,proceso_fabrica)
                VALUES (?,?,?)
            ")->execute([$productoId,$nombre,$procedimiento]);

            $recetaId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("
                INSERT INTO recetas_detalle
                (receta_id, materia_prima_id, cantidad)
                VALUES (?, ?, ?)
            ");

            foreach ($items as $item) {
                $stmt->execute([
                    $recetaId,
                    $item['materia_prima_id'],
                    $item['cantidad']
                ]);
            }

            $this->db->commit();
            return $recetaId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function detalle(int $recetaId): array
    {
        $stmt = $this->db->prepare("
            SELECT rd.*, mp.nombre, mp.unidad_medida
            FROM recetas_detalle rd
            JOIN materias_primas mp ON mp.id = rd.materia_prima_id
            WHERE rd.receta_id = ?
        ");
        $stmt->execute([$recetaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, p.nombre AS producto
            FROM recetas r
            JOIN productos p ON p.id = r.producto_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $receta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receta) return null;

        $receta['detalle'] = $this->detalle($id);
        return $receta;
    }

    public function addDetalle(int $recetaId, int $mpId, float $cant): bool
    {
        return $this->db->prepare(
            "INSERT INTO receta_detalle 
            (receta_id, materia_prima_id, cantidad)
            VALUES (:r,:m,:c)"
        )->execute([
            'r'=>$recetaId,
            'm'=>$mpId,
            'c'=>$cant
        ]);
    }

    public function edit_receta():bool
    {
        try{
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "UPDATE recetas SET nombre = :n, proceso_fabrica = :p WHERE id = :i");
                $stmt->execute([
                'n'=>$_POST['nombre'],
                'i'=>$_POST['receta_id'],
                'p'=>$_POST['procedimiento'] ?? null
            ]);
            foreach($_POST['items'] as $mp_id => $cant){
                if($cant <= 0){
                    $stmt_det = $this->db->prepare(
                    "DELETE FROM recetas_detalle 
                    WHERE receta_id = :r AND materia_prima_id = :m"
                    );
                    $stmt_det->execute([
                        'r'=>$_POST['receta_id'],
                        'm'=>$mp_id
                    ]);
                }else{
                    $stmt_det = $this->db->prepare(
                        "UPDATE recetas_detalle 
                        SET cantidad = :c 
                        WHERE receta_id = :r AND materia_prima_id = :m"
                    );
                    $stmt_det->execute([
                        'c'=>$cant,
                        'r'=>$_POST['receta_id'],
                        'm'=>$mp_id
                    ]);
                }
            }
            $this->db->commit();
        }catch(Exception $e){
            $this->db->rollBack();
            $_SESSION['error'] = 'Error al editar la receta: '.$e->getMessage();
            error_log('error al editar una receta: '.$e->getMessage());
            header('Location: '.BASE_URL.'/recetas/show/'.$_POST['receta_id']);
            exit;
        }
        return true;
    }

    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "DELETE FROM recetas_detalle WHERE receta_id = :r"
            )->execute(['r'=>$id]);

            $this->db->prepare(
                "DELETE FROM recetas WHERE id = :r"
            )->execute(['r'=>$id]);

            $this->db->commit();
            $_SESSION['success'] = 'Receta eliminada correctamente';
            return true;

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al eliminar la receta: '.$e->getMessage();
            $this->db->rollBack();
            throw $e;
        }

    }

    /*public function getOrCreate(int $productoId): int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM recetas WHERE producto_id = :p"
        );
        $stmt->execute(['p'=>$productoId]);
        $r = $stmt->fetch();

        if ($r) return $r['id'];

        $this->db->prepare(
            "INSERT INTO recetas (producto_id) VALUES (:p)"
        )->execute(['p'=>$productoId]);

        return (int)$this->db->lastInsertId();
    }*/
}