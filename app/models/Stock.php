<?php
class Stock extends Model
{
    public function stockProductos(): array
    {
        return $this->db->query("
            SELECT
                p.id,
                p.sku,
                p.nombre,
                p.stock_minimo,
                p.stock_maximo,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ),0) AS stock
            FROM productos p
            LEFT JOIN movimientos_stock m ON m.producto_id = p.id
            GROUP BY p.id
            ORDER BY p.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function stockMateriasPrimas(): array
    {
        return $this->db->query("
            SELECT
                mp.id,
                mp.nombre,
                mp.unidad_medida,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ),0) AS stock,
            (SELECT SUM(cantidad) FROM reservas_materia_prima WHERE materia_prima_id=mp.id) AS stockreserva
            FROM materias_primas mp
            LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
            GROUP BY mp.id
            ORDER BY mp.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}