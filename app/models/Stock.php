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
                p.stock_critico,
                stock,
                CASE
                    WHEN p.stock_critico > 0 AND stock <= p.stock_critico THEN 'critico'
                    WHEN p.stock_minimo > 0 AND stock <= p.stock_minimo THEN 'bajo'
                    WHEN p.stock_maximo > 0 AND stock >= p.stock_maximo THEN 'alto'
                    ELSE 'normal'
                END AS estado
            FROM (
                SELECT
                    p.id,
                    p.sku,
                    p.nombre,
                    p.stock_minimo,
                    p.stock_maximo,
                    p.stock_critico,
                    COALESCE(SUM(
                        CASE
                            WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                        END
                    ),0) AS stock
                FROM productos p
                LEFT JOIN movimientos_stock m ON m.producto_id = p.id
                GROUP BY p.id
            ) AS p
            ORDER BY
                CASE
                    WHEN p.stock_critico > 0 AND stock <= p.stock_critico THEN 1
                    WHEN p.stock_maximo > 0 AND stock >= p.stock_maximo THEN 2
                    WHEN p.stock_minimo > 0 AND stock <= p.stock_minimo THEN 3
                    ELSE 4
                END,
                stock ASC;
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function stockMateriasPrimas(): array
    {
        return $this->db->query("
            SELECT
                mp.id,
                mp.nombre,
                um.nombre AS unidad_medida,
                mp.stock_minimo,
                mp.stock_maximo,
                mp.stock_critico,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ),0) AS stock,
                (
                    SELECT COALESCE(
                        SUM(
                            CASE
                                WHEN estado = 'RESERVADO' THEN cantidad
                                WHEN estado IN ('CONSUMIDO','LIBERADO') THEN -cantidad
                                ELSE 0
                            END
                        ),0
                    )
                    FROM reservas_materia_prima r
                    WHERE r.materia_prima_id = mp.id
                ) AS stockreserva,
                CASE 
                    WHEN mp.stock_critico > 0
                        AND COALESCE(SUM(
                            CASE
                                WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                                WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                            END
                        ),0) <= mp.stock_critico
                    THEN 'critico'
                    WHEN mp.stock_minimo > 0
                        AND COALESCE(SUM(
                            CASE
                                WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                                WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                            END
                        ),0) <= mp.stock_minimo
                    THEN 'bajo'
                    WHEN mp.stock_maximo > 0
                        AND COALESCE(SUM(
                            CASE
                                WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                                WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                            END
                        ),0) >= mp.stock_maximo
                    THEN 'alto'
                    ELSE 'normal'
                END AS estado
            FROM materias_primas mp
            LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
            LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
            GROUP BY mp.id
            ORDER BY
                CASE
                    WHEN mp.stock_critico > 0
                        AND COALESCE(SUM(
                            CASE
                                WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                                WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                            END
                        ),0) <= mp.stock_critico THEN 1
                    WHEN mp.stock_maximo > 0
                        AND COALESCE(SUM(
                            CASE
                                WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                                WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                            END
                        ),0) >= mp.stock_maximo THEN 2
                    WHEN mp.stock_minimo > 0
                        AND COALESCE(SUM(
                            CASE
                                WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                                WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                            END
                        ),0) <= mp.stock_minimo THEN 3
                    ELSE 4
                END,
                stock ASC;
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}