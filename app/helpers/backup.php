<?php
/**
 * Backup de las bases del ERP (master + tenants). Script CLI.
 *
 * Usa el mismo servicio que el panel admin (app/services/BackupService.php),
 * asi que un respaldo hecho desde el panel y uno por cron son identicos.
 *
 * Uso:
 *   php app/helpers/backup.php                  full por defecto
 *   php app/helpers/backup.php --master         solo la BD master
 *   php app/helpers/backup.php --tenants        solo los tenants (uno por BD)
 *   php app/helpers/backup.php --full           master + todos los tenants
 *   php app/helpers/backup.php --daily          full rotativo (cron diario)
 *   php app/helpers/backup.php --daily --keep=7 full rotativo, deja 7 copias
 *   php app/helpers/backup.php --destino=D:/respaldos   destino alternativo
 *
 * El destino por defecto queda FUERA del webroot (se cambia con BACKUP_PATH
 * en el .env o con --destino=). Un respaldo que viva bajo C:/xampp/htdocs
 * quedaria servido por Apache, con los hashes de password a la vista.
 *
 * Cron:
 *   Windows (Programador de tareas):
 *     php.exe C:\xampp\htdocs\app\app\helpers\backup.php --daily
 *   Linux (crontab -e), a las 2:00:
 *     0 2 * * * php /var/www/app/app/helpers/backup.php --daily
 *
 * OJO: --daily con --keep=1 pisa el unico respaldo que tenes. Si la BD se
 * corrompe antes de que corra el cron, respaldas la corrupcion y encima
 * tiras la ultima copia buena. Con --keep=7 tenes una semana de margen.
 */

// mysqldump y la clave de la BD salen del .env, igual que en la app.
$BASE_PATH = dirname(dirname(__DIR__));
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $BASE_PATH);
}

// Requerimos config.php (igual que bootstrap.php) para que el CLI use la MISMA
// zona horaria que la app. Sin esto PHP usa la del php.ini y los respaldos del
// cron se nombrarian con una hora distinta a los hechos desde el panel.
require_once BASE_PATH . '/app/config/config.php';
ini_set('display_errors', '1'); // en CLI conviene ver los errores

require_once BASE_PATH . '/app/services/BackupService.php';

// ---------------------------------------------------------------- opciones
$modo     = 'full';
$keep     = 0;
$destino  = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--daily') {
        // Rotativo: mantiene solo los N mas recientes (1 por defecto).
        $modo = 'full';
        $keep = ($keep > 0) ? $keep : 1;
    } elseif ($arg === '--master') {
        $modo = 'master';
    } elseif ($arg === '--tenants') {
        $modo = 'tenants';
    } elseif ($arg === '--full') {
        $modo = 'full';
    } elseif (str_starts_with($arg, '--keep=')) {
        $keep = max(0, (int) substr($arg, 7));
    } elseif (str_starts_with($arg, '--destino=')) {
        $destino = substr($arg, 10);
    } elseif ($arg === '--help' || $arg === '-h') {
        // El uso esta arriba, en el docblock del archivo.
        echo "Uso: php app/helpers/backup.php [--master|--tenants|--full|--daily] [--keep=N] [--destino=RUTA]\n";
        exit(0);
    } else {
        echo "Opcion desconocida: {$arg}\n";
        echo "Uso: php app/helpers/backup.php [--master|--tenants|--full|--daily] [--keep=N] [--destino=RUTA]\n";
        exit(1);
    }
}

if ($destino !== null) {
    // El override por CLI manda: lo dejamos en el env para que el servicio
    // lo lea, sin tener que tocar el .env.
    putenv('BACKUP_PATH=' . $destino);
    $_ENV['BACKUP_PATH'] = $destino;
}

// ---------------------------------------------------------------- ejecutar
echo "=== Backup de bases ERP (modo: {$modo}) ===\n";

try {
    $servicio = new BackupService();

    $base = $servicio->destinoBase();
    echo "Destino: {$base}\n";
    echo "Bases:   " . implode(', ', array_keys($servicio->bases())) . "\n\n";

    $inicio = microtime(true);
    $res    = $servicio->respaldar($modo, $keep);
    $tiempo = round(microtime(true) - $inicio, 1);

    foreach ($res['archivos'] as $a) {
        printf(
            "  OK    %-22s %12s bytes  (%s)\n",
            $a['base'],
            number_format($a['bytes']),
            $a['tipo']
        );
    }

    echo "\n";
    printf(
        "Backup OK: %d base(s), %s MB, en %.1f s\n",
        $res['bases'],
        number_format($res['total'] / 1048576, 2),
        $tiempo
    );
    echo "Directorio: {$res['dir']}\n";

    if ($keep > 0) {
        echo "Rotacion: se conservan los ultimos {$keep} respaldo(s).\n";
    }

    echo "\nPara restaurar:\n";
    echo "  mysql -u root <base> < {$res['dir']}/<base>.sql\n";

    exit(0);
} catch (Throwable $e) {
    echo "\nFALLO: " . $e->getMessage() . "\n";
    exit(1);
}
