<?php
require_once BASE_PATH . '/app/core/Model.php';

/**
 * Modelo de unidades individuales con número de serie.
 * Cada producto/MP con usa_serie=1 se recepciona y remite unidad a unidad.
 * Estados: DISPONIBLE → REMITIDO → (EN_DEVOLUCION) → DISPONIBLE / BAJA
 */
class Serie extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /** Crea una serie individual. Retorna el ID. */
    public function create(array $d): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO series (producto_id, materia_prima_id, numero_serie, lote_id, deposito_id, estado, ingreso_id)
            VALUES (:p, :mp, :ns, :l, :d, :e, :i)
        ");
        $stmt->execute([
            'p'   => $d['producto_id'] ?? null,
            'mp'  => $d['materia_prima_id'] ?? null,
            'ns'  => $d['numero_serie'],
            'l'   => $d['lote_id'] ?? null,
            'd'   => $d['deposito_id'] ?? null,
            'e'   => $d['estado'] ?? 'DISPONIBLE',
            'i'   => $d['ingreso_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Busca una serie por número (único). */
    public function findByNumero(string $numero): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM series WHERE numero_serie = ? LIMIT 1");
        $stmt->execute([$numero]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Busca una serie por ID. */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM series WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Lista las series DISPONIBLES en un depósito para un producto o MP.
     * Retorna array con numero_serie, lote, fecha_vencimiento.
     */
    public function disponibles(int $depositoId, ?int $productoId = null, ?int $materiaPrimaId = null): array
    {
        $sql = "
            SELECT s.*, l.codigo_lote, l.fecha_vencimiento
            FROM series s
            LEFT JOIN lotes l ON l.id = s.lote_id
            WHERE s.estado = 'DISPONIBLE' AND s.activo = 1 AND s.deposito_id = :dep
        ";
        $params = ['dep' => $depositoId];
        if ($productoId !== null) {
            $sql .= " AND s.producto_id = :p";
            $params['p'] = $productoId;
        }
        if ($materiaPrimaId !== null) {
            $sql .= " AND s.materia_prima_id = :mp";
            $params['mp'] = $materiaPrimaId;
        }
        $sql .= " ORDER BY s.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cuenta series disponibles en un depósito para un producto/MP. */
    public function countDisponibles(int $depositoId, ?int $productoId = null, ?int $materiaPrimaId = null): int
    {
        $sql = "SELECT COUNT(*) FROM series WHERE estado = 'DISPONIBLE' AND activo = 1 AND deposito_id = :dep";
        $params = ['dep' => $depositoId];
        if ($productoId !== null) {
            $sql .= " AND producto_id = :p";
            $params['p'] = $productoId;
        }
        if ($materiaPrimaId !== null) {
            $sql .= " AND materia_prima_id = :mp";
            $params['mp'] = $materiaPrimaId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Marca una serie como remitida (asociada a un remito de salida). */
    public function marcarRemitida(int $serieId, int $remitoId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE series SET estado = 'REMITIDO', remito_salida_id = :r, updated_at = NOW()
            WHERE id = :id AND estado = 'DISPONIBLE'
        ");
        return $stmt->execute(['r' => $remitoId, 'id' => $serieId]);
    }

    /** Marca una serie como EN_DEVOLUCION. */
    public function marcarEnDevolucion(int $serieId, int $devolucionId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE series SET estado = 'EN_DEVOLUCION', devolucion_id = :d, updated_at = NOW()
            WHERE id = :id AND estado = 'REMITIDO'
        ");
        return $stmt->execute(['d' => $devolucionId, 'id' => $serieId]);
    }

    /** Marca una serie como DISPONIBLE (reingreso por devolución). */
    public function marcarDisponible(int $serieId, ?int $depositoId = null): bool
    {
        $sql = "UPDATE series SET estado = 'DISPONIBLE', updated_at = NOW()";
        $params = ['id' => $serieId];
        if ($depositoId !== null) {
            $sql .= ", deposito_id = :dep";
            $params['dep'] = $depositoId;
        }
        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /** Baja lógica de una serie. */
    public function delete(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE series SET activo = 0, estado = 'BAJA', updated_at = NOW() WHERE id = ?"
        )->execute([$id]);
    }

    /**
     * Transfiere una serie de un depósito a otro (salida del origen, entrada al destino).
     */
    public function transferir(int $serieId, int $depositoDestinoId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE series SET deposito_id = :dep, updated_at = NOW()
            WHERE id = :id AND estado = 'DISPONIBLE'
        ");
        return $stmt->execute(['dep' => $depositoDestinoId, 'id' => $serieId]);
    }

    /** Todas las series de un producto (con nombre y estado). */
    public function byProducto(int $productoId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, d.nombre AS deposito_nombre, l.codigo_lote, l.fecha_vencimiento
            FROM series s
            LEFT JOIN depositos d ON d.id = s.deposito_id
            LEFT JOIN lotes l ON l.id = s.lote_id
            WHERE s.producto_id = ? AND s.activo = 1
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$productoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Todas las series de una materia prima. */
    public function byMateriaPrima(int $mpId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, d.nombre AS deposito_nombre, l.codigo_lote, l.fecha_vencimiento
            FROM series s
            LEFT JOIN depositos d ON d.id = s.deposito_id
            LEFT JOIN lotes l ON l.id = s.lote_id
            WHERE s.materia_prima_id = ? AND s.activo = 1
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$mpId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Verifica si un número de serie ya existe. */
    public function existe(string $numero): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM series WHERE numero_serie = ?");
        $stmt->execute([$numero]);
        return (int) $stmt->fetchColumn() > 0;
    }
}