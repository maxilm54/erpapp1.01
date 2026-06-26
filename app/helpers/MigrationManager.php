<?php
/**
 * Sistema de migraciones para tenants multi-tenant.
 *
 * Uso:
 *   $mm = new MigrationManager();
 *   $mm->runAll();          // Aplica migraciones pendientes a TODOS los tenants
 *   $mm->runForTenant(2);   // Aplica pendientes solo al tenant ID 2
 *   $mm->getPending(2);     // Lista migraciones pendientes para tenant ID 2
 *   $mm->getApplied(2);     // Lista migraciones ya aplicadas al tenant ID 2
 */
class MigrationManager {

    private string $migrationsPath;
    private PDO $masterDb;

    public function __construct() {
        $this->masterDb = Database::getMaster();
        $this->migrationsPath = BASE_PATH . '/app/helpers/migrations';
    }

    /**
     * Escanea y retorna todas las migraciones disponibles (ordenadas por número).
     */
    public function getAllMigrations(): array {
        $files = glob($this->migrationsPath . '/*.sql');
        $migrations = [];

        foreach ($files as $file) {
            $basename = basename($file, '.sql');
            // Formato esperado: 001_descripcion
            if (preg_match('/^(\d+)_(.+)$/', $basename, $matches)) {
                $migrations[] = [
                    'number'     => (int)$matches[1],
                    'name'       => $matches[2],
                    'filename'   => $basename . '.sql',
                    'filepath'   => $file,
                ];
            }
        }

        usort($migrations, fn($a, $b) => $a['number'] <=> $b['number']);
        return $migrations;
    }

    /**
     * Retorna el schema_version actual de un tenant.
     */
    public function getVersion(int $tenantId): int {
        $stmt = $this->masterDb->prepare("SELECT schema_version FROM tenants WHERE id = :id");
        $stmt->execute([':id' => $tenantId]);
        $version = $stmt->fetchColumn();
        return $version !== false ? (int)$version : 0;
    }

    /**
     * Actualiza el schema_version de un tenant.
     */
    public function setVersion(int $tenantId, int $version): void {
        $stmt = $this->masterDb->prepare("UPDATE tenants SET schema_version = :v WHERE id = :id");
        $stmt->execute([':v' => $version, ':id' => $tenantId]);
    }

    /**
     * Retorna migraciones pendientes para un tenant.
     */
    public function getPending(int $tenantId): array {
        $current = $this->getVersion($tenantId);
        $all = $this->getAllMigrations();

        return array_filter($all, fn($m) => $m['number'] > $current);
    }

    /**
     * Retorna migraciones ya aplicadas a un tenant.
     */
    public function getApplied(int $tenantId): array {
        $current = $this->getVersion($tenantId);
        $all = $this->getAllMigrations();

        return array_filter($all, fn($m) => $m['number'] <= $current);
    }

    /**
     * Ejecuta migraciones pendientes para un tenant específico.
     * Retorna array con los números de migración aplicados.
     */
    public function runForTenant(int $tenantId): array {
        $tenant = $this->getTenantInfo($tenantId);
        if (!$tenant) {
            error_log("[MigrationManager] Tenant {$tenantId} no encontrado.");
            return [];
        }

        $pending = $this->getPending($tenantId);
        if (empty($pending)) {
            error_log("[MigrationManager] Tenant '{$tenant['nombre']}' (ID: {$tenantId}) ya está actualizado.");
            return [];
        }

        // Conectar a la BD del tenant
        $dsn = "mysql:host={$tenant['host']};dbname={$tenant['dbname']};charset=utf8mb4";
        $config = require BASE_PATH . '/app/config/database.php';
        $tenantDb = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $applied = [];
        $lastVersion = $this->getVersion($tenantId);

        foreach ($pending as $migration) {
            $sql = file_get_contents($migration['filepath']);
            if (empty(trim($sql))) {
                continue;
            }

            error_log("[MigrationManager] Aplicando migración {$migration['filename']} a '{$tenant['nombre']}'...");

            try {
                // Ejecutar cada statement por separado
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => !empty($s) && $s[0] !== '-'
                );

                foreach ($statements as $statement) {
                    $trimmed = ltrim($statement);
                    if (empty($trimmed) || $trimmed[0] === '-') {
                        continue;
                    }
                    $tenantDb->exec($statement);
                }

                $lastVersion = $migration['number'];
                $applied[] = $migration['number'];
                error_log("[MigrationManager] ✓ Migración {$migration['filename']} aplicada.");
            } catch (PDOException $e) {
                error_log("[MigrationManager] ✗ Error en {$migration['filename']}: " . $e->getMessage());
                // Si falla, cortar y guardar hasta dónde llegó
                break;
            }
        }

        // Actualizar versión en master
        if (!empty($applied)) {
            $this->setVersion($tenantId, $lastVersion);
        }

        return $applied;
    }

    /**
     * Ejecuta migraciones pendientes para TODOS los tenants activos.
     * Retorna array con resultados por tenant.
     */
    public function runAll(): array {
        $stmt = $this->masterDb->query("SELECT id, nombre FROM tenants WHERE activo = 1 ORDER BY id");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($tenants as $t) {
            $results[$t['id']] = [
                'nombre'   => $t['nombre'],
                'applied'  => $this->runForTenant($t['id']),
                'pending'  => count($this->getPending($t['id'])),
                'version'  => $this->getVersion($t['id']),
            ];
        }

        return $results;
    }

    /**
     * Retorna info básica de un tenant.
     */
    private function getTenantInfo(int $tenantId): ?array {
        $stmt = $this->masterDb->prepare("SELECT * FROM tenants WHERE id = :id");
        $stmt->execute([':id' => $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Retorna la última migración disponible (número más alto).
     */
    public function getLatestVersion(): int {
        $all = $this->getAllMigrations();
        return !empty($all) ? end($all)['number'] : 0;
    }
}
