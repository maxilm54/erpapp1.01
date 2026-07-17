<?php
require_once BASE_PATH . '/app/core/Model.php';

class AsientoContable extends Model{

    /**
     * Libro diario con filtros.
     */
    public function getAll(array $filters = []): array{
        $sql = "
            SELECT a.*, u.nombre AS usuario_nombre,
                   COALESCE(SUM(ad.debe), 0) AS total_debe,
                   COALESCE(SUM(ad.haber), 0) AS total_haber
            FROM asientos_contables a
            LEFT JOIN users u ON u.id = a.usuario_id
            LEFT JOIN asientos_detalle ad ON ad.asiento_id = a.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND a.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND a.fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['tipo'])) {
            $sql .= " AND a.tipo = :tipo";
            $params[':tipo'] = $filters['tipo'];
        }
        if (!empty($filters['buscar'])) {
            $sql .= " AND (a.descripcion LIKE :buscar OR a.observaciones LIKE :buscar2)";
            $params[':buscar'] = '%' . $filters['buscar'] . '%';
            $params[':buscar2'] = '%' . $filters['buscar'] . '%';
        }

        $sql .= " GROUP BY a.id ORDER BY a.fecha DESC, a.numero DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle completo de un asiento.
     */
    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT a.*, u.nombre AS usuario_nombre
            FROM asientos_contables a
            LEFT JOIN users u ON u.id = a.usuario_id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $asiento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$asiento) return null;

        $stmt2 = $this->db->prepare("
            SELECT ad.*, cc.codigo, cc.nombre AS cuenta_nombre, cc.tipo AS cuenta_tipo
            FROM asientos_detalle ad
            INNER JOIN cuentas_contables cc ON cc.id = ad.cuenta_contable_id
            WHERE ad.asiento_id = :asiento_id
            ORDER BY cc.codigo
        ");
        $stmt2->execute([':asiento_id' => $id]);
        $asiento['detalle'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $totalDebe = 0;
        $totalHaber = 0;
        foreach ($asiento['detalle'] as $linea) {
            $totalDebe += (float)$linea['debe'];
            $totalHaber += (float)$linea['haber'];
        }
        $asiento['total_debe'] = $totalDebe;
        $asiento['total_haber'] = $totalHaber;
        $asiento['balanceado'] = abs($totalDebe - $totalHaber) < 0.01;

        return $asiento;
    }

    /**
     * Crear asiento con detalle (partida doble).
     * $lineas = [['cuenta_contable_id' => N, 'debe' => X, 'haber' => Y], ...]
     */
    public function create(array $data, array $lineas): int{
        try {
            // Generar número de asiento
            $numero = $this->proximoNumero();

            // Insertar cabecera
            $stmt = $this->db->prepare("
                INSERT INTO asientos_contables
                (numero, fecha, descripcion, tipo, origen_modulo, origen_tipo, origen_id, usuario_id, observaciones)
                VALUES (:numero, :fecha, :descripcion, :tipo, :origen_modulo, :origen_tipo, :origen_id, :usuario_id, :observaciones)
            ");
            $stmt->execute([
                ':numero'         => $numero,
                ':fecha'          => $data['fecha'],
                ':descripcion'    => $data['descripcion'],
                ':tipo'           => $data['tipo'] ?? 'OPERACION',
                ':origen_modulo'  => $data['origen_modulo'] ?? null,
                ':origen_tipo'    => $data['origen_tipo'] ?? null,
                ':origen_id'      => $data['origen_id'] ?? null,
                ':usuario_id'     => $data['usuario_id'],
                ':observaciones'  => $data['observaciones'] ?? null,
            ]);
            $asientoId = (int)$this->db->lastInsertId();

            // Insertar detalle
            $stmt2 = $this->db->prepare("
                INSERT INTO asientos_detalle (asiento_id, cuenta_contable_id, debe, haber)
                VALUES (:asiento_id, :cuenta_contable_id, :debe, :haber)
            ");

            $totalDebe = 0;
            $totalHaber = 0;

            foreach ($lineas as $linea) {
                $debe = (float)($linea['debe'] ?? 0);
                $haber = (float)($linea['haber'] ?? 0);

                if ($debe == 0 && $haber == 0) continue;

                $stmt2->execute([
                    ':asiento_id'         => $asientoId,
                    ':cuenta_contable_id' => $linea['cuenta_contable_id'],
                    ':debe'               => $debe,
                    ':haber'              => $haber,
                ]);

                $totalDebe += $debe;
                $totalHaber += $haber;
            }

            // Verificar que esté balanceado
            if (abs($totalDebe - $totalHaber) >= 0.01) {
                throw new Exception("El asiento no está balanceado. Debe: \$" . number_format($totalDebe, 2) . " | Haber: \$" . number_format($totalHaber, 2));
            }

            return $asientoId;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Busca asientos por origen (módulo + tipo + id).
     */
    public function findByOrigen(string $modulo, string $tipo, int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT * FROM asientos_contables
            WHERE origen_modulo = :modulo AND origen_tipo = :tipo AND origen_id = :id
            LIMIT 1
        ");
        $stmt->execute([':modulo' => $modulo, ':tipo' => $tipo, ':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Anular un asiento (genera asiento inverso).
     */
    public function anular(int $asientoId, int $usuarioId): int{
        $original = $this->findById($asientoId);
        if (!$original) throw new Exception("Asiento no encontrado");

        $lineasInversas = [];
        foreach ($original['detalle'] as $linea) {
            $lineasInversas[] = [
                'cuenta_contable_id' => $linea['cuenta_contable_id'],
                'debe'               => $linea['haber'],  // Invertir
                'haber'              => $linea['debe'],   // Invertir
            ];
        }

        return $this->create([
            'fecha'          => date('Y-m-d'),
            'descripcion'    => "ANULACIÓN del asiento #{$original['numero']}: {$original['descripcion']}",
            'tipo'           => 'AJUSTE',
            'origen_modulo'  => 'CONTABILIDAD',
            'origen_tipo'    => 'ASIENTO',
            'origen_id'      => $asientoId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => "Asiento inverso del #{$original['numero']}",
        ], $lineasInversas);
    }

    /**
     * Próximo número de asiento.
     */
    public function proximoNumero(): int{
        $stmt = $this->db->query("SELECT COALESCE(MAX(numero), 0) + 1 FROM asientos_contables");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Verifica si existe un asiento para un movimiento dado.
     */
    public function existeAsiento(string $modulo, string $tipo, int $id): bool{
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM asientos_contables
            WHERE origen_modulo = :modulo AND origen_tipo = :tipo AND origen_id = :id
        ");
        $stmt->execute([':modulo' => $modulo, ':tipo' => $tipo, ':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
