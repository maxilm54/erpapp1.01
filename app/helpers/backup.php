<?php
/**
 * Backup de todas las bases del ERP (master + tenants).
 * Script standalone - no depende del framework.
 *
 * Uso: php app/helpers/backup.php [directorio-destino]
 *
 * Genera un .sql por base en <destino>/erp_YYYYMMDD_HHMM/ usando mysqldump.
 * Deja el directorio con la fecha para no pisar respaldos anteriores.
 *
 * OJO: no usar redirect de shell (>) para generar los .sql: en PowerShell 5.1
 * escribe UTF-16 y el dump queda ilegible. Por eso se usa --result-file.
 */

$BASE_PATH = dirname(dirname(__DIR__));

// ---------------------------------------------------------------- configuracion
$env = [];
if (file_exists($BASE_PATH . '/.env')) {
    $env = parse_ini_file($BASE_PATH . '/.env', false, INI_SCANNER_TYPED) ?: [];
}
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? '';

// mysqldump de XAMPP (Windows). En Linux seria 'mysqldump' del PATH.
$mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';
if (!file_exists($mysqldump)) {
    $mysqldump = 'mysqldump';
}

// ---------------------------------------------------------------- destino
$destinoBase = $argv[1] ?? 'C:/xampp/mysql/backup';
$destino = rtrim($destinoBase, '/') . '/erp_' . date('Ymd_His');
if (!is_dir($destino) && !mkdir($destino, 0775, true)) {
    echo "ERROR: no se pudo crear el directorio $destino\n";
    exit(1);
}

echo "=== Backup de bases ERP ===\n";
echo "Destino: $destino\n\n";

// ---------------------------------------------------------------- que bases
$bases = [];
try {
    $pdo = new PDO("mysql:host={$dbHost};dbname=app_master;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $bases['app_master'] = 'Base master (empresas, usuarios, tareas)';
    foreach ($pdo->query("SELECT dbname FROM tenants WHERE activo = 1 ORDER BY id") as $t) {
        $bases[$t['dbname']] = 'Tenant';
    }
} catch (PDOException $e) {
    echo "ERROR: no se pudo conectar a app_master: " . $e->getMessage() . "\n";
    exit(1);
}

// ---------------------------------------------------------------- dump
$errores = 0;
foreach ($bases as $nombre => $tipo) {
    $archivo = $destino . '/' . $nombre . '.sql';
    $cmd = sprintf(
        '"%s" -h %s -u %s %s --skip-lock-tables --default-character-set=utf8mb4 --result-file="%s" %s 2>&1',
        $mysqldump,
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        $dbPass !== '' ? '-p' . escapeshellarg($dbPass) : '',
        $archivo,
        escapeshellarg($nombre)
    );
    exec($cmd, $out, $rc);

    $bytes = file_exists($archivo) ? filesize($archivo) : 0;
    if ($rc === 0 && $bytes > 0) {
        printf("  OK    %-22s %10s bytes  (%s)\n", $nombre, number_format($bytes), $tipo);
    } else {
        printf("  FALLA %-22s %s\n", $nombre, implode(' | ', array_slice($out, 0, 2)));
        $errores++;
    }
    $out = [];
}

// ---------------------------------------------------------------- resumen
echo "\n";
if ($errores > 0) {
    echo "备份 con $errores error(es). Revisar la salida de mysqldump.\n";
    exit(1);
}

$total = 0;
foreach (glob($destino . '/*.sql') as $f) $total += filesize($f);
echo "Backup OK: " . count($bases) . " bases, " . number_format($total / 1048576, 1) . " MB en $destino\n";
echo "Para restaurar: mysql -u root <base> < <destino>/<base>.sql\n";
