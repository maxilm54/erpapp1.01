<?php

class StockService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function registerMovement(array $data): bool
    {
        $sql = "INSERT INTO movimientos_stock 
                (producto_id, materia_prima_id, deposito_id, lote_id, cantidad, tipo, origen, 
                 referencia_id, observaciones, usuario_id) 
                VALUES (:producto_id, :materia_prima_id, :deposito_id, :lote_id, :cantidad, :tipo, :origen,
                        :referencia_id, :observaciones, :usuario_id)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':producto_id' => $data['producto_id'] ?? null,
            ':materia_prima_id' => $data['materia_prima_id'] ?? null,
            ':deposito_id' => $data['deposito_id'] ?? null,
            ':lote_id' => $data['lote_id'] ?? null,
            ':cantidad' => $data['cantidad'],
            ':tipo' => $data['tipo'],
            ':origen' => $data['origen'],
            ':referencia_id' => $data['referencia_id'] ?? null,
            ':observaciones' => $data['observaciones'] ?? null,
            ':usuario_id' => $data['usuario_id'] ?? null
        ]);
    }

    public function updateStockProducto(int $productoId, float $cantidad, string $tipo = 'ENTRADA', ?int $referenciaId = null, ?string $observaciones = null, ?int $usuarioId = null, ?int $depositoId = null, ?int $loteId = null): bool
    {
        $currentStock = $this->getStockProducto($productoId);
        $nuevoStock = ($tipo === 'ENTRADA') ? $currentStock + $cantidad : $currentStock - $cantidad;
        
        if ($nuevoStock < 0) {
            error_log("Stock insuficiente para producto ID {$productoId}. Stock actual: {$currentStock}, cantidad solicitada: {$cantidad}");
            return false;
        }

        return $this->registerMovement([
            'producto_id' => $productoId,
            'cantidad' => $cantidad,
            'tipo' => $tipo,
            'origen' => 'MANUAL',
            'referencia_id' => $referenciaId,
            'observaciones' => $observaciones,
            'usuario_id' => $usuarioId,
            'deposito_id' => $depositoId,
            'lote_id' => $loteId
        ]);
    }

    public function updateStockMateriaPrima(int $materiaPrimaId, float $cantidad, string $tipo = 'ENTRADA', ?int $referenciaId = null, ?string $observaciones = null, ?int $usuarioId = null, ?int $depositoId = null, ?int $loteId = null): bool
    {
        $currentStock = $this->getStockMateriaPrima($materiaPrimaId);
        $nuevoStock = ($tipo === 'ENTRADA') ? $currentStock + $cantidad : $currentStock - $cantidad;

        if ($nuevoStock < 0) {
            error_log("Stock insuficiente para materia prima ID {$materiaPrimaId}. Stock actual: {$currentStock}, cantidad solicitada: {$cantidad}");
            return false;
        }

        return $this->registerMovement([
            'materia_prima_id' => $materiaPrimaId,
            'cantidad' => $cantidad,
            'tipo' => $tipo,
            'origen' => 'MANUAL',
            'referencia_id' => $referenciaId,
            'observaciones' => $observaciones,
            'usuario_id' => $usuarioId,
            'deposito_id' => $depositoId,
            'lote_id' => $loteId
        ]);
    }

    private function getStockProducto(int $productoId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                     WHEN tipo = 'SALIDA' THEN -cantidad END
            ), 0) as stock
            FROM movimientos_stock WHERE producto_id = :id
        ");
        $stmt->execute([':id' => $productoId]);
        return (float) $stmt->fetchColumn();
    }

    private function getStockMateriaPrima(int $materiaPrimaId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                     WHEN tipo = 'SALIDA' THEN -cantidad END
            ), 0) as stock
            FROM movimientos_stock WHERE materia_prima_id = :id
        ");
        $stmt->execute([':id' => $materiaPrimaId]);
        return (float) $stmt->fetchColumn();
    }

    public function checkStockProductosByReceta(int $recetaId, int $cantidadProducir): array
    {
        $recetaModel = new Receta();
        $receta = $recetaModel->find($recetaId);

        if (!$receta) {
            return ['disponible' => false, 'faltantes' => [], 'stock_disponible' => []];
        }

        $resultado = ['disponible' => true, 'faltantes' => [], 'stock_disponible' => []];

        foreach ($receta['detalle'] as $ingrediente) {
            $stock = $this->getStockProducto($ingrediente['materia_prima_id']);
            $necesario = $ingrediente['cantidad'] * $cantidadProducir;
            
            if ($stock < $necesario) {
                $resultado['disponible'] = false;
                $resultado['faltantes'][] = [
                    'producto_id' => $ingrediente['materia_prima_id'],
                    'nombre' => $ingrediente['nombre'] ?? '',
                    'necesario' => $necesario,
                    'disponible' => $stock
                ];
            }
            $resultado['stock_disponible'][] = [
                'producto_id' => $ingrediente['materia_prima_id'],
                'nombre' => $ingrediente['nombre'] ?? '',
                'disponible' => $stock,
                'necesario' => $necesario
            ];
        }

        return $resultado;
    }

    public function checkStockMateriasPrimasByReceta(int $recetaId, int $cantidadProducir): array
    {
        $recetaModel = new Receta();
        $receta = $recetaModel->find($recetaId);

        if (!$receta) {
            return ['disponible' => false, 'faltantes' => [], 'stock_disponible' => []];
        }

        $resultado = ['disponible' => true, 'faltantes' => [], 'stock_disponible' => []];

        foreach ($receta['detalle'] as $ingrediente) {
            $stock = $this->getStockMateriaPrima($ingrediente['materia_prima_id']);
            $necesario = $ingrediente['cantidad'] * $cantidadProducir;
            
            if ($stock < $necesario) {
                $resultado['disponible'] = false;
                $resultado['faltantes'][] = [
                    'materia_prima_id' => $ingrediente['materia_prima_id'],
                    'nombre' => $ingrediente['nombre'] ?? '',
                    'necesario' => $necesario,
                    'disponible' => $stock
                ];
            }
            $resultado['stock_disponible'][] = [
                'materia_prima_id' => $ingrediente['materia_prima_id'],
                'nombre' => $ingrediente['nombre'] ?? '',
                'disponible' => $stock,
                'necesario' => $necesario
            ];
        }

        return $resultado;
    }

    public function getStockPorDeposito(?int $productoId = null, ?int $materiaPrimaId = null, ?int $depositoId = null): float
    {
        $sql = "
            SELECT COALESCE(SUM(
                CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                     WHEN tipo = 'SALIDA' THEN -cantidad END
            ), 0) as stock
            FROM movimientos_stock WHERE 1=1";
        $params = [];

        if ($productoId) {
            $sql .= " AND producto_id = :producto_id";
            $params[':producto_id'] = $productoId;
        }
        if ($materiaPrimaId) {
            $sql .= " AND materia_prima_id = :materia_prima_id";
            $params[':materia_prima_id'] = $materiaPrimaId;
        }
        if ($depositoId) {
            $sql .= " AND deposito_id = :deposito_id";
            $params[':deposito_id'] = $depositoId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function getStockPorLote(int $loteId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                     WHEN tipo = 'SALIDA' THEN -cantidad END
            ), 0) as stock
            FROM movimientos_stock WHERE lote_id = :id
        ");
        $stmt->execute([':id' => $loteId]);
        return (float)$stmt->fetchColumn();
    }

    public function getStockDesglosado(?int $productoId = null, ?int $materiaPrimaId = null): array
    {
        $sql = "
            SELECT d.id AS deposito_id, d.nombre AS deposito_nombre,
                   l.id AS lote_id, l.codigo_lote, l.fecha_vencimiento,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock
            FROM depositos d
            LEFT JOIN lotes l ON (l.producto_id = :prod_id OR l.materia_prima_id = :mp_id)
                AND l.activo = 1
            LEFT JOIN movimientos_stock m ON m.deposito_id = d.id AND m.lote_id = l.id
                AND (m.producto_id = :prod_id2 OR m.materia_prima_id = :mp_id2)
            WHERE d.activo = 1
            GROUP BY d.id, l.id
            HAVING stock != 0
            ORDER BY d.nombre, l.fecha_vencimiento, l.codigo_lote
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':prod_id' => $productoId,
            ':mp_id' => $materiaPrimaId,
            ':prod_id2' => $productoId,
            ':mp_id2' => $materiaPrimaId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}