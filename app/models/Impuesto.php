<?php
require_once BASE_PATH . '/app/core/Model.php';

class Impuesto extends Model{

    public function getAll(): array{
        $stmt = $this->db->query("SELECT * FROM impuestos ORDER BY porcentaje DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActivos(): array{
        $stmt = $this->db->query("SELECT * FROM impuestos WHERE activo = 1 ORDER BY porcentaje DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("SELECT * FROM impuestos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int{
        $stmt = $this->db->prepare("
            INSERT INTO impuestos (nombre, codigo, porcentaje, activo)
            VALUES (:nombre, :codigo, :porcentaje, :activo)
        ");
        $stmt->execute([
            ':nombre'     => $data['nombre'],
            ':codigo'     => $data['codigo'],
            ':porcentaje' => $data['porcentaje'],
            ':activo'     => $data['activo'] ?? 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool{
        $stmt = $this->db->prepare("
            UPDATE impuestos SET nombre = :nombre, codigo = :codigo,
                   porcentaje = :porcentaje, activo = :activo
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'         => $id,
            ':nombre'     => $data['nombre'],
            ':codigo'     => $data['codigo'],
            ':porcentaje' => $data['porcentaje'],
            ':activo'     => $data['activo'] ?? 1,
        ]);
    }

    public function toggle(int $id): bool{
        $impuesto = $this->findById($id);
        if (!$impuesto) return false;
        return $this->update($id, [
            'nombre'     => $impuesto['nombre'],
            'codigo'     => $impuesto['codigo'],
            'porcentaje' => $impuesto['porcentaje'],
            'activo'     => $impuesto['activo'] ? 0 : 1,
        ]);
    }

    /**
     * Calcula el desglose de IVA dado un monto total que incluye IVA.
     */
    public function calcularDesglose(float $montoTotal, int $impuestoId): array{
        $impuesto = $this->findById($impuestoId);
        if (!$impuesto || $impuesto['porcentaje'] == 0) {
            return [
                'monto_base'     => $montoTotal,
                'monto_impuesto' => 0,
                'monto_total'    => $montoTotal,
                'porcentaje'     => 0,
            ];
        }

        // El monto total incluye IVA: base = total / (1 + porcentaje/100)
        $base = $montoTotal / (1 + $impuesto['porcentaje'] / 100);
        $iva = $montoTotal - $base;

        return [
            'monto_base'     => round($base, 2),
            'monto_impuesto' => round($iva, 2),
            'monto_total'    => $montoTotal,
            'porcentaje'     => (float)$impuesto['porcentaje'],
        ];
    }

    /**
     * Calcula el monto total dado una base + impuesto.
     */
    public function calcularTotal(float $montoBase, int $impuestoId): array{
        $impuesto = $this->findById($impuestoId);
        if (!$impuesto || $impuesto['porcentaje'] == 0) {
            return [
                'monto_base'     => $montoBase,
                'monto_impuesto' => 0,
                'monto_total'    => $montoBase,
                'porcentaje'     => 0,
            ];
        }

        $iva = $montoBase * $impuesto['porcentaje'] / 100;

        return [
            'monto_base'     => $montoBase,
            'monto_impuesto' => round($iva, 2),
            'monto_total'    => round($montoBase + $iva, 2),
            'porcentaje'     => (float)$impuesto['porcentaje'],
        ];
    }
}
