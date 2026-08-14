<?php
require_once BASE_PATH . '/app/core/Model.php';

class Numerador extends Model
{
    public function __construct(){
        $this->db = Database::getInstance();
    }

    public function siguiente(string $tipo): int
    {
        $stmt = $this->db->prepare("
            SELECT ultimo_numero FROM numeradores WHERE tipo = ? FOR UPDATE
        ");
        $stmt->execute([$tipo]);
        $num = (int)$stmt->fetchColumn() + 1;

        $this->db->prepare("
            UPDATE numeradores SET ultimo_numero = ? WHERE tipo = ?
        ")->execute([$num, $tipo]);

        return $num;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM numeradores ORDER BY tipo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM numeradores WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByTipo(string $tipo): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM numeradores WHERE tipo = :tipo");
        $stmt->execute([':tipo' => $tipo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO numeradores (tipo, ultimo_numero, incremento, prefijo)
            VALUES (:tipo, :ultimo_numero, :incremento, :prefijo)
        ");
        $stmt->execute([
            ':tipo'           => strtoupper(trim($data['tipo'])),
            ':ultimo_numero'  => (int)($data['ultimo_numero'] ?? 0),
            ':incremento'     => (int)($data['incremento'] ?? 1),
            ':prefijo'        => trim($data['prefijo'] ?? '') ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE numeradores SET
                tipo = :tipo,
                ultimo_numero = :ultimo_numero,
                incremento = :incremento,
                prefijo = :prefijo
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'              => $id,
            ':tipo'            => strtoupper(trim($data['tipo'])),
            ':ultimo_numero'   => (int)($data['ultimo_numero'] ?? 0),
            ':incremento'      => (int)($data['incremento'] ?? 1),
            ':prefijo'         => trim($data['prefijo'] ?? '') ?: null,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM numeradores WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function tipoExists(string $tipo, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM numeradores WHERE tipo = :tipo";
        $params = [':tipo' => strtoupper(trim($tipo))];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params[':id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Ejemplo: obtener el proximo numero con prefijo formateado
     */
    public function siguienteFormateado(string $tipo): string
    {
        $num = $this->siguiente($tipo);
        $numerador = $this->findByTipo($tipo);
        if (!$numerador) return (string)$num;

        $prefijo = $numerador['prefijo'] ?? '';
        $digitos = max(6, strlen((string)$num));

        return $prefijo . str_pad($num, $digitos, '0', STR_PAD_LEFT);
    }
}
