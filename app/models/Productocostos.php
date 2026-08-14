<?php
require_once BASE_PATH . '/app/core/Model.php';

class Productocostos extends Model
{
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getByProducto(int $productoId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM productos_costos WHERE producto_id = :pid");
        $stmt->execute([':pid' => $productoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function createOrUpdate(int $productoId, array $data): bool
    {
        $existing = $this->getByProducto($productoId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE productos_costos SET
                    precio_compra = :precio_compra,
                    costo_fijo = :costo_fijo,
                    costo_variable_pct = :costo_variable_pct,
                    margen_ganancia_pct = :margen_ganancia_pct
                WHERE producto_id = :producto_id
            ");
            return $stmt->execute([
                ':precio_compra'        => (float)$data['precio_compra'],
                ':costo_fijo'           => (float)$data['costo_fijo'],
                ':costo_variable_pct'   => (float)$data['costo_variable_pct'],
                ':margen_ganancia_pct'  => (float)$data['margen_ganancia_pct'],
                ':producto_id'          => $productoId,
            ]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO productos_costos
            (producto_id, precio_compra, costo_fijo, costo_variable_pct, margen_ganancia_pct)
            VALUES (:producto_id, :precio_compra, :costo_fijo, :costo_variable_pct, :margen_ganancia_pct)
        ");
        return $stmt->execute([
            ':producto_id'          => $productoId,
            ':precio_compra'        => (float)$data['precio_compra'],
            ':costo_fijo'           => (float)$data['costo_fijo'],
            ':costo_variable_pct'   => (float)$data['costo_variable_pct'],
            ':margen_ganancia_pct'  => (float)$data['margen_ganancia_pct'],
        ]);
    }

    public function delete(int $productoId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM productos_costos WHERE producto_id = :pid");
        $stmt->execute([':pid' => $productoId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Calcular el precio de venta sugerido basado en costos
     */
    public static function calcularPrecioVenta(array $costo, float $precioVentaActual = 0): array
    {
        $precioCompra   = (float)$costo['precio_compra'];
        $costoFijo      = (float)$costo['costo_fijo'];
        $costoVarPct    = (float)$costo['costo_variable_pct'];
        $margenPct      = (float)$costo['margen_ganancia_pct'];

        // Costo variable = precio_compra * (costo_variable_pct / 100)
        $costoVariable = $precioCompra * ($costoVarPct / 100);

        // Costo total = precio_compra + costo_fijo + costo_variable
        $costoTotal = $precioCompra + $costoFijo + $costoVariable;

        // Precio venta = costo_total * (1 + margen_ganancia_pct / 100)
        $precioSugerido = $costoTotal * (1 + $margenPct / 100);

        // Ganancia neta = precio_venta_sugerido - costo_total
        $gananciaNeta = $precioSugerido - $costoTotal;

        // Margen real sobre precio de venta
        $margenReal = $precioSugerido > 0 ? ($gananciaNeta / $precioSugerido * 100) : 0;

        return [
            'precio_compra'     => $precioCompra,
            'costo_fijo'        => $costoFijo,
            'costo_variable'    => round($costoVariable, 2),
            'costo_total'       => round($costoTotal, 2),
            'margen_ganancia_pct' => $margenPct,
            'precio_sugerido'   => round($precioSugerido, 2),
            'ganancia_neta'     => round($gananciaNeta, 2),
            'margen_real_pct'   => round($margenReal, 2),
            'precio_venta_actual' => $precioVentaActual,
        ];
    }
}
