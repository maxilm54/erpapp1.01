<?php
/**
 * Genera el registro mensual de CtaCte de las empresas (panel superadmin).
 *
 * Es idempotente: si el registro de esa empresa y periodo ya existe, no lo
 * duplica (clave unica tenant_id + periodo). Pensado para cron diario, asi
 * el dia 1 de cada mes queda creado aunque nadie abra el panel. La pantalla
 * Admin -> CtaCte tambien genera el mes actual al abrirse.
 *
 * Uso:
 *   php app/helpers/ctacte_generar.php
 *   php app/helpers/ctacte_generar.php --periodo=2026-12
 *   php app/helpers/ctacte_generar.php --tenant=2
 *   php app/helpers/ctacte_generar.php --todos      (incluye empresas inactivas)
 *
 * Cron (Windows - Programador de tareas, todos los dias a las 3:00):
 *   php.exe C:\xampp\htdocs\app\app\helpers\ctacte_generar.php
 *
 * Cron (Linux, todos los dias a las 3:00):
 *   0 3 * * * /usr/bin/php /home/usuario/app/app/helpers/ctacte_generar.php
 */

$BASE_PATH = dirname(dirname(__DIR__));
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $BASE_PATH);
}

// config.php define la zona horaria (America/Argentina/Buenos_Aires).
require_once BASE_PATH . '/app/config/config.php';
require_once BASE_PATH . '/app/core/Database.php';
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Ctactetenant.php';

ini_set('display_errors', '1');

$periodo = date('Y-m');
$tenantId = null;
$soloActivos = true;

$uso = "Uso: php app/helpers/ctacte_generar.php [--periodo=AAAA-MM] [--tenant=ID] [--todos]\n";

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo $uso;
        exit(0);
    } elseif (str_starts_with($arg, '--periodo=')) {
        $periodo = substr($arg, 10);
    } elseif (str_starts_with($arg, '--tenant=')) {
        $tenantId = (int)substr($arg, 9);
    } elseif ($arg === '--todos') {
        $soloActivos = false;
    } else {
        fwrite(STDERR, "Opcion desconocida: {$arg}\n{$uso}");
        exit(1);
    }
}

try {
    $model = new Ctactetenant();
    $creados = $model->generarMes($periodo, $tenantId, $soloActivos);

    echo sprintf(
        "[%s] CtaCte periodo %s: %d registro(s) creado(s)%s.\n",
        date('Y-m-d H:i:s'),
        $periodo,
        $creados,
        $tenantId !== null ? " (tenant {$tenantId})" : ''
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[ctacte_generar] Error: ' . $e->getMessage() . "\n");
    exit(1);
}
