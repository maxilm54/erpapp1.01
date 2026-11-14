<?php

require_once BASE_PATH . '/app/core/Model.php';

/**
 * Configuraciones del tenant (clave/valor), administradas desde el menu
 * Empresa -> Configuraciones. Vive en la BD del tenant: cada empresa
 * tiene su propia configuracion.
 *
 * Claves actuales:
 *   - usar_nomenclador: '1' | '0' — activa el uso de nomencladores por cliente.
 *   - usar_productos_recetas: '1' | '0' — permite agregar productos como
 *     componentes de una receta (ademas de materias primas).
 */
class Configuracion extends Model
{
    /**
     * Valor de una clave de configuracion, o el default si no existe.
     */
    public function get(string $clave, ?string $default = null): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT valor FROM configuraciones WHERE clave = :clave"
        );
        $stmt->execute(['clave' => $clave]);
        $valor = $stmt->fetchColumn();
        return $valor !== false && $valor !== null ? (string)$valor : $default;
    }

    /**
     * True si la clave esta en '1' (o si no existe y el default es '1').
     */
    public function activa(string $clave, bool $defaultActiva = true): bool
    {
        return $this->get($clave, $defaultActiva ? '1' : '0') === '1';
    }

    /**
     * Guarda una clave (crea o actualiza).
     */
    public function set(string $clave, ?string $valor): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO configuraciones (clave, valor)
             VALUES (:clave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        );
        return $stmt->execute(['clave' => $clave, 'valor' => $valor]);
    }

    /**
     * Todas las configuraciones, ordenadas por clave.
     */
    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM configuraciones ORDER BY clave"
        )->fetchAll();
    }
}
