<?php
/**
 * Migraciones de la BD MASTER (app_master).
 *
 * El MigrationManager de tenants NO alcanza para el schema master: las tareas,
 * por ejemplo, viven en la master porque las administer el superadmin.
 *
 * Uso:
 *   $mm = new MasterMigrationManager();
 *   $mm->getPending();   // migraciones master pendientes
 *   $mm->runAll();       // aplica las pendientes
 *   $mm->ensureSchema(); // aplica las pendientes si hay (idempotente)
 *
 * Los archivos van en app/helpers/squemadb/master_migrations/NNN_descripcion.sql
 * y se aplican en orden numerico, de a uno en uno.
 */
class MasterMigrationManager
{
    private string $migrationsPath;
    private PDO $masterDb;

    public function __construct()
    {
        $this->masterDb = Database::getMaster();
        $this->migrationsPath = BASE_PATH . '/app/helpers/squemadb/master_migrations';
    }

    /**
     * Crea la tabla de control si no existe. Es lo que permite que un
     * schema master nuevo funcione sobre instalaciones ya-existing.
     */
    private function ensureControlTable(): void
    {
        $this->masterDb->exec(
            "CREATE TABLE IF NOT EXISTS `master_migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `version` int(11) NOT NULL,
                `filename` varchar(255) NOT NULL,
                `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Todas las migraciones master disponibles, ordenadas por numero.
     */
    public function getAllMigrations(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        $migrations = [];

        foreach ($files as $file) {
            $basename = basename($file, '.sql');
            if (preg_match('/^(\d+)_(.+)$/', $basename, $matches)) {
                $migrations[] = [
                    'number'   => (int)$matches[1],
                    'name'     => $matches[2],
                    'filename' => $basename . '.sql',
                    'filepath' => $file,
                ];
            }
        }

        usort($migrations, fn($a, $b) => $a['number'] <=> $b['number']);
        return $migrations;
    }

    public function getApplied(): array
    {
        $this->ensureControlTable();
        $stmt = $this->masterDb->query("SELECT version, filename, applied_at FROM master_migrations ORDER BY version");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPending(): array
    {
        $this->ensureControlTable();
        $applied = array_map(fn($r) => (int)$r['version'], $this->getApplied());
        return array_values(array_filter(
            $this->getAllMigrations(),
            fn($m) => !in_array((int)$m['number'], $applied, true)
        ));
    }

    public function getLatestVersion(): int
    {
        $all = $this->getAllMigrations();
        return !empty($all) ? (int)end($all)['number'] : 0;
    }

    /**
     * Aplica las migraciones master pendientes. Devuelve los numeros aplicadas.
     */
    public function runAll(): array
    {
        $this->ensureControlTable();
        $pending = $this->getPending();
        $applied = [];

        foreach ($pending as $m) {
            $sql = file_get_contents($m['filepath']);
            if (trim($sql) === '') continue;

            try {
                foreach ($this->splitSqlStatements($sql) as $statement) {
                    $this->masterDb->exec($statement);
                }
                $this->masterDb->prepare(
                    "INSERT INTO master_migrations (version, filename) VALUES (:v, :f)
                     ON DUPLICATE KEY UPDATE filename = VALUES(filename), applied_at = current_timestamp()"
                )->execute([':v' => $m['number'], ':f' => $m['filename']]);
                $applied[] = $m['number'];
                error_log("[MasterMigrationManager] Migración {$m['filename']} aplicada.");
            } catch (PDOException $e) {
                error_log("[MasterMigrationManager] Error en {$m['filename']}: " . $e->getMessage());
                break;
            }
        }

        return $applied;
    }

    /**
     * Aplica las pendientes si hay. Idempotente: se puede llamar en cada request
     * sin efectos secundarios cuando el schema ya esta al dia.
     */
    public function ensureSchema(): void
    {
        if (!empty($this->getPending())) {
            $this->runAll();
        }
    }

    /**
     * Divide SQL en statements respetando strings entrecomillados.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$inDoubleQuote) {
                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                    $current .= "''";
                    $i++;
                } else {
                    $inSingleQuote = !$inSingleQuote;
                    $current .= $char;
                }
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $current .= $char;
            } elseif ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $trimmed = trim($current);
                if ($trimmed !== '') $statements[] = $trimmed;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $trimmed = trim($current);
        if ($trimmed !== '') $statements[] = $trimmed;

        return $statements;
    }
}
