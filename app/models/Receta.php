<?php
class Receta extends Model
{
    protected string $table = 'recetas';
    public function all(): array
    {
        return $this->db->query("
            SELECT r.*, p.nombre AS producto,
            GROUP_CONCAT(rd.materia_prima_id SEPARATOR ' - ') AS id_mat_prim,
            GROUP_CONCAT(COALESCE(mp.nombre, CONCAT(pr.nombre, ' (producto)')) SEPARATOR ' - ') AS mat_prim
            FROM recetas r
            JOIN productos p ON p.id = r.producto_id
            LEFT JOIN recetas_detalle rd ON rd.receta_id=r.id
            LEFT JOIN materias_primas mp ON mp.id=rd.materia_prima_id
            LEFT JOIN productos pr ON pr.id=rd.producto_id
            WHERE r.activa = '1'
            GROUP BY r.id, r.nombre
            ORDER BY r.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(int $productoId,string $nombre, array $items, ?string $procedimiento, int $userId, array $procesos = []): int
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare("
                INSERT INTO recetas (producto_id,nombre,proceso_fabrica,user_id)
                VALUES (?,?,?,?)
            ")->execute([$productoId,$nombre,$procedimiento,$userId]);

            $recetaId = (int)$this->db->lastInsertId();
            
            $stmt = $this->db->prepare("
                INSERT INTO recetas_detalle
                (receta_id, materia_prima_id, producto_id, cantidad)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($this->normalizarItems($items) as $item) {
                $stmt->execute([
                    $recetaId,
                    $item['materia_prima_id'],
                    $item['producto_id'],
                    $item['cantidad']
                ]);
            }

            $this->guardarProcesos($recetaId, $procesos);

            $this->db->commit();
            return $recetaId;

        } catch (Exception $e) {
            error_log('Error en la funcion de crear una receta: '.$e->getMessage());
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Normaliza las filas de insumos de una receta. Cada fila puede ser una
     * materia prima (tipo MATERIA_PRIMA) o un producto (tipo PRODUCTO).
     * Se descartan cantidades <= 0 y filas sin referencia. Si una misma
     * materia prima/producto viene repetida, queda la ultima cantidad.
     */
    private function normalizarItems(array $items): array
    {
        $normalizados = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cantidad = (float)str_replace(',', '.', (string)($item['cantidad'] ?? 0));
            if ($cantidad <= 0) {
                continue;
            }

            $tipo = strtoupper(trim((string)($item['tipo'] ?? '')));
            $materiaId = (int)($item['materia_prima_id'] ?? 0);
            $productoId = (int)($item['producto_id'] ?? 0);

            if ($tipo === 'PRODUCTO' || ($productoId > 0 && $materiaId <= 0)) {
                if ($productoId <= 0) {
                    continue;
                }
                $normalizados['prod_'.$productoId] = [
                    'materia_prima_id' => null,
                    'producto_id'      => $productoId,
                    'cantidad'         => $cantidad,
                ];
            } else {
                if ($materiaId <= 0) {
                    continue;
                }
                $normalizados['mp_'.$materiaId] = [
                    'materia_prima_id' => $materiaId,
                    'producto_id'      => null,
                    'cantidad'         => $cantidad,
                ];
            }
        }
        return array_values($normalizados);
    }

    /**
     * Procesos activos de una receta, ordenados.
     */
    public function procesos(int $recetaId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM recetas_procesos
            WHERE receta_id = ? AND activo = 1
            ORDER BY secuencia ASC, id ASC
        ");
        $stmt->execute([$recetaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Guarda los procesos de una receta. Si no viene ninguno, crea "Proceso 1"
     * para cumplir con el minimo de un proceso.
     * Se reemplazan los procesos existentes (las OPs ya creadas conservan su
     * snapshot de nombre/horas/secuencia).
     */
    private function guardarProcesos(int $recetaId, array $procesos): void
    {
        $normalizados = [];
        foreach ($procesos as $proceso) {
            if (!is_array($proceso)) {
                continue;
            }
            $nombre = trim((string)($proceso['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $horas = (float)str_replace(',', '.', (string)($proceso['horas_por_unidad'] ?? $proceso['horas'] ?? 0));
            if ($horas < 0) {
                $horas = 0;
            }
            $costo = (float)str_replace(',', '.', (string)($proceso['costo'] ?? 0));
            if ($costo < 0) {
                $costo = 0;
            }
            $normalizados[] = [
                'nombre' => mb_substr($nombre, 0, 150),
                'horas' => round($horas, 4),
                'costo' => round($costo, 2),
            ];
        }

        if (empty($normalizados)) {
            $normalizados[] = ['nombre' => 'Proceso 1', 'horas' => 0.0, 'costo' => 0.0];
        }

        $this->db->prepare("DELETE FROM recetas_procesos WHERE receta_id = ?")->execute([$recetaId]);

        $stmt = $this->db->prepare("
            INSERT INTO recetas_procesos (receta_id, nombre, horas_por_unidad, costo, secuencia, activo)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $secuencia = 1;
        foreach ($normalizados as $proceso) {
            $stmt->execute([$recetaId, $proceso['nombre'], $proceso['horas'], $proceso['costo'], $secuencia]);
            $secuencia++;
        }
    }

    public function detalle(int $recetaId): array
    {
        $stmt = $this->db->prepare("
            SELECT rd.*, mp.nombre, mp.tipo_gestion, um.nombre AS unidad_medida, esp.presentacion
            FROM recetas_detalle rd
            JOIN materias_primas mp ON mp.id = rd.materia_prima_id
            LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
            LEFT JOIN materias_primas_especificaciones esp ON esp.materia_prima_id = mp.id
            WHERE rd.receta_id = ?
        ");
        $stmt->execute([$recetaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Componentes de la receta que son productos (no materias primas).
     * Se mantiene separado de detalle() para no alterar el flujo de
     * produccion, que reserva/consume solo materias primas.
     */
    public function detalleProductos(int $recetaId): array
    {
        $stmt = $this->db->prepare("
            SELECT rd.*, p.nombre, p.sku, p.precio_venta,
                   um.nombre AS unidad_medida
            FROM recetas_detalle rd
            JOIN productos p ON p.id = rd.producto_id
            LEFT JOIN unidad_medida um ON um.id_medida = p.unidad_medida
            WHERE rd.receta_id = ?
            ORDER BY p.nombre
        ");
        $stmt->execute([$recetaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, p.nombre AS producto, p.precio_venta, p.id AS producto_id
            FROM recetas r
            JOIN productos p ON p.id = r.producto_id
            WHERE r.id = ? AND r.activa = '1'
        ");
        $stmt->execute([$id]);
        $receta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receta) return null;

        $receta['detalle'] = $this->detalle($id);
        $receta['procesos'] = $this->procesos($id);
        return $receta;
    }

    public function addDetalle(int $recetaId, int $mpId, float $cant): bool
    {
        return $this->db->prepare(
            "INSERT INTO recetas_detalle 
            (receta_id, materia_prima_id, cantidad)
            VALUES (:r,:m,:c)"
        )->execute([
            'r'=>$recetaId,
            'm'=>$mpId,
            'c'=>$cant
        ]);
    }

    /**
     * Actualiza la receta y reemplaza su composicion (materias primas y
     * productos). Se borran los renglones actuales y se insertan los que
     * llegan del formulario, lo que permite agregar y quitar insumos.
     */
    public function edit_receta(int $recetaId, string $nombre, ?string $procedimiento, array $items, ?array $procesos = null): bool
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE recetas SET nombre = :n, proceso_fabrica = :p WHERE id = :i"
            );
            $stmt->execute([
                'n' => $nombre,
                'i' => $recetaId,
                'p' => $procedimiento
            ]);

            $this->db->prepare(
                "DELETE FROM recetas_detalle WHERE receta_id = :r"
            )->execute(['r' => $recetaId]);

            $stmtDet = $this->db->prepare(
                "INSERT INTO recetas_detalle (receta_id, materia_prima_id, producto_id, cantidad)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($this->normalizarItems($items) as $item) {
                $stmtDet->execute([
                    $recetaId,
                    $item['materia_prima_id'],
                    $item['producto_id'],
                    $item['cantidad']
                ]);
            }

            if ($procesos !== null) {
                $this->guardarProcesos($recetaId, $procesos);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('error al editar una receta: '.$e->getMessage());
            throw $e;
        }
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

    public function inactivar(int $id): bool
    {
        try {
            $this->db->prepare(
                "UPDATE recetas SET activa = '0' WHERE id = :r"
            )->execute(['r'=>$id]);
            error_log('Receta inactivada correctamente (ID: '.$id.')');
            $_SESSION['success'] = 'Receta inactivada correctamente';
            return true;

        } catch (Exception $e) {
            error_log('Error al inactivar la receta ('.$id.'): '.$e->getMessage());
            $_SESSION['error'] = 'Error al inactivar la receta: '.$e->getMessage();
            return false;
        }
    }
}