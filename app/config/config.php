<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once BASE_PATH . '/app/config/env.php';
define('APP_NAME','app');
error_reporting(E_ALL);
ini_set('display_errors', (defined('APP_DEBUG') && APP_DEBUG) ? '1' : '0');
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PORT', env('SMTP_PORT'));
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', env('SMTP_PASS'));
define('SMTP_SECURE', env('SMTP_SECURE'));
define('SMTP_FROM', env('SMTP_FROM'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME'));
define('FECHA_ACTUAL', date("Y-m-d H:i:s"));

/**
 * Retorna configuración del sistema.
 * Para 'empresa', carga desde la BD del tenant actual.
 */
function config(string $key)
{
    static $config = [];
    static $empresaLoaded = false;
    static $empresaTenantId = null;

    if ($key === 'empresa') {
        $currentTenantId = (function_exists('Auth') && Auth::hasTenant()) ? Auth::getTenantId() : null;
        if (!$empresaLoaded || $empresaTenantId !== $currentTenantId) {
            $empresaLoaded = true;
            $empresaTenantId = $currentTenantId;
            $config['empresa'] = loadEmpresaConfig();
        }
        return $config['empresa'] ?? empresaDefaults();
    }

    if (!$config) {
        $config = [];
    }

    return $config[$key] ?? null;
}

/**
 * Carga la configuración de empresa desde la BD del tenant actual.
 */
function loadEmpresaConfig(): array
{
    // Si no hay tenant seleccionado, usar defaults
    if (!function_exists('Auth') || !Auth::hasTenant()) {
        return empresaDefaults();
    }

    try {
        $tenantId = Auth::getTenantId();
        require_once BASE_PATH . '/app/models/Tenant.php';
        $tenantModel = new Tenant();
        return $tenantModel->getEmpresaConfig($tenantId);
    } catch (Exception $e) {
        error_log("Error cargando config empresa: " . $e->getMessage());
        return empresaDefaults();
    }
}

/**
 * Valores por defecto de empresa (fallback).
 */
function empresaDefaults(): array
{
    return [
        'nombre'    => 'Empresa',
        'cuit'      => '',
        'email'     => '',
        'telefono'  => '',
        'direccion' => '',
        'logo'      => null,
        'logo_file' => null,
    ];
}

/**
 * Carga datos de empresa directamente desde la DB master.
 * No usa cache ni config(). Garantiza datos frescos.
 */
function loadEmpresaFromDb(): array
{
    $defaults = [
        'nombre' => '', 'cuit' => '', 'email' => '',
        'telefono' => '', 'direccion' => '', 'logo' => '', 'logo_file' => null,
    ];

    if (!function_exists('Auth') || !Auth::hasTenant()) {
        return $defaults;
    }

    try {
        $tenantId = Auth::getTenantId();
        $masterDb = Database::getMaster();

        // Verificar qué columnas existen en la tabla tenants
        $colCheck = $masterDb->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        $hasCuit     = in_array('cuit', $colCheck);
        $hasEmail    = in_array('email', $colCheck);
        $hasTelefono = in_array('telefono', $colCheck);
        $hasDireccion= in_array('direccion', $colCheck);
        $hasLogo     = in_array('logo', $colCheck);

        // Armar SELECT dinámico solo con columnas que existen
        $selects = ['nombre'];
        if ($hasCuit)      $selects[] = 'cuit';
        if ($hasEmail)     $selects[] = 'email';
        if ($hasTelefono)  $selects[] = 'telefono';
        if ($hasDireccion) $selects[] = 'direccion';
        if ($hasLogo)      $selects[] = 'logo';

        $sql = "SELECT " . implode(', ', $selects) . " FROM tenants WHERE id = ?";
        $stmt = $masterDb->prepare($sql);
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            error_log("[loadEmpresaFromDb] No se encontró tenant id={$tenantId}");
            return $defaults;
        }

        $empresa = [
            'nombre'    => $row['nombre'] ?? 'Empresa',
            'cuit'      => ($hasCuit ? ($row['cuit'] ?? '') : ''),
            'email'     => ($hasEmail ? ($row['email'] ?? '') : ''),
            'telefono'  => ($hasTelefono ? ($row['telefono'] ?? '') : ''),
            'direccion' => ($hasDireccion ? ($row['direccion'] ?? '') : ''),
            'logo'      => '',
            'logo_file' => ($hasLogo ? ($row['logo'] ?? null) : null),
        ];

        if ($hasLogo && !empty($row['logo'])) {
            $logoFile = BASE_PATH . "/public/uploads/img_config/empresa_{$tenantId}/{$row['logo']}";
            if (file_exists($logoFile)) {
                $empresa['logo'] = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile));
            }
        }

        return $empresa;
    } catch (Exception $e) {
        error_log("Error loadEmpresaFromDb: " . $e->getMessage());
        return $defaults;
    }
}

/**
 * Retorna la ruta del logo de la empresa actual (para PDFs, etc.).
 */
function empresaLogoPath(): ?string
{
    $empresa = config('empresa');
    $logoFile = $empresa['logo_file'] ?? null;

    if (empty($logoFile)) return null;

    $tenantId = Auth::getTenantId();
    if (!$tenantId) return null;

    $path = BASE_PATH . "/public/uploads/img_config/empresa_{$tenantId}/{$logoFile}";
    return file_exists($path) ? $path : null;
}

/**
 * Retorna la ruta del directorio de uploads para la empresa actual.
 */
function empresaUploadPath(string $tipo): string
{
    $tenantId = Auth::getTenantId();
    $base = BASE_PATH . "/public/uploads/{$tipo}/empresa_{$tenantId}";
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }
    return $base;
}

/**
 * Retorna la ruta de storage para la empresa actual.
 */
function empresaStoragePath(string $tipo): string
{
    $tenantId = Auth::getTenantId();
    $base = BASE_PATH . "/storage/empresa_{$tenantId}/{$tipo}";
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }
    return $base;
}

/**
 * Retorna la URL pública de uploads para la empresa actual.
 */
function empresaUploadUrl(string $tipo): string
{
    $tenantId = Auth::getTenantId();
    return BASE_URL . "/uploads/{$tipo}/empresa_{$tenantId}";
}

/**
 * Escribe un log diario para la empresa actual.
 */
function empresaLog(string $mensaje, string $nivel = 'INFO'): void
{
    if (!function_exists('Auth') || !Auth::hasTenant()) {
        error_log("[{$nivel}] {$mensaje}");
        return;
    }

    $tenantId = Auth::getTenantId();
    $logDir = BASE_PATH . "/storage/empresa_{$tenantId}/logs";
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $filename = $logDir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] [{$nivel}] {$mensaje}" . PHP_EOL;

    file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);
}
