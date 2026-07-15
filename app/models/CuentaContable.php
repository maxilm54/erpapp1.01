<?php
require_once BASE_PATH . '/app/core/Model.php';

class CuentaContable extends Model{

    public function getAll(): array{
        $stmt = $this->db->query("
            SELECT * FROM cuentas_contables ORDER BY codigo
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getArbol(): array{
        $cuentas = $this->getAll();
        return $this->buildTree($cuentas, null);
    }

    public function getHojas(): array{
        $stmt = $this->db->query("
            SELECT * FROM cuentas_contables WHERE acepta_movimiento = 1 AND activa = 1 ORDER BY codigo
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("SELECT * FROM cuentas_contables WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCodigo(string $codigo): ?array{
        $stmt = $this->db->prepare("SELECT * FROM cuentas_contables WHERE codigo = :codigo");
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getSaldo(int $cuentaId, ?string $fechaDesde = null, ?string $fechaHasta = null): float{
        $sql = "
            SELECT
                COALESCE(SUM(ad.debe), 0) AS total_debe,
                COALESCE(SUM(ad.haber), 0) AS total_haber
            FROM asientos_detalle ad
            INNER JOIN asientos_contables a ON a.id = ad.asiento_id
            WHERE ad.cuenta_contable_id = :cuenta_id
              AND a.fecha BETWEEN :desde AND :hasta
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cuenta_id' => $cuentaId,
            ':desde'     => $fechaDesde ?? '2000-01-01',
            ':hasta'     => $fechaHasta ?? date('Y-m-d'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Para cuentas de ACTIVO y EGRESO: saldo = debe - haber
        // Para cuentas de PASIVO, PATRIMONIO e INGRESO: saldo = haber - debe
        $cuenta = $this->findById($cuentaId);
        if (!$cuenta) return 0;

        $debe = (float)$row['total_debe'];
        $haber = (float)$row['total_haber'];

        if (in_array($cuenta['tipo'], ['ACTIVO', 'EGRESO'])) {
            return $debe - $haber;
        }
        return $haber - $debe;
    }

    public function getSaldosPorTipo(?string $fechaDesde = null, ?string $fechaHasta = null): array{
        $cuentas = $this->getHojas();
        $saldos = [];
        foreach ($cuentas as $c) {
            $saldo = $this->getSaldo($c['id'], $fechaDesde, $fechaHasta);
            if ($saldo != 0) {
                $saldos[] = [
                    'cuenta'  => $c,
                    'saldo'   => $saldo,
                ];
            }
        }
        return $saldos;
    }

    public function create(array $data): int{
        $stmt = $this->db->prepare("
            INSERT INTO cuentas_contables (codigo, nombre, tipo, padre_id, nivel, acepta_movimiento)
            VALUES (:codigo, :nombre, :tipo, :padre_id, :nivel, :acepta_movimiento)
        ");
        $stmt->execute([
            ':codigo'            => $data['codigo'],
            ':nombre'            => $data['nombre'],
            ':tipo'              => $data['tipo'],
            ':padre_id'          => $data['padre_id'] ?? null,
            ':nivel'             => $data['nivel'] ?? 1,
            ':acepta_movimiento' => $data['acepta_movimiento'] ?? 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool{
        $stmt = $this->db->prepare("
            UPDATE cuentas_contables
            SET codigo = :codigo, nombre = :nombre, tipo = :tipo,
                acepta_movimiento = :acepta_movimiento, activa = :activa
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'                => $id,
            ':codigo'            => $data['codigo'],
            ':nombre'            => $data['nombre'],
            ':tipo'              => $data['tipo'],
            ':acepta_movimiento' => $data['acepta_movimiento'] ?? 1,
            ':activa'            => $data['activa'] ?? 1,
        ]);
    }

    public function getProximoNumero(string $prefijo): string{
        $stmt = $this->db->prepare("
            SELECT codigo FROM cuentas_contables
            WHERE codigo LIKE :prefijo ORDER BY codigo DESC LIMIT 1
        ");
        $stmt->execute([':prefijo' => $prefijo . '%']);
        $last = $stmt->fetchColumn();
        if (!$last) return $prefijo . '01';
        $num = (int)substr($last, -2) + 1;
        return $prefijo . str_pad($num, 2, '0', STR_PAD_LEFT);
    }

    private function buildTree(array $elements, ?int $parentId): array{
        $branch = [];
        foreach ($elements as $element) {
            if ($element['padre_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
}
