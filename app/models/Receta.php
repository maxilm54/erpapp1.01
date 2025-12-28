<?php
class Receta extends Model
{
    public function getOrCreate(int $productoId): int
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
    }

    public function detalle(int $recetaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT rd.*, mp.nombre, mp.unidad_medida
             FROM receta_detalle rd
             JOIN materias_primas mp ON mp.id = rd.materia_prima_id
             WHERE rd.receta_id = :r"
        );
        $stmt->execute(['r'=>$recetaId]);
        return $stmt->fetchAll();
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
}