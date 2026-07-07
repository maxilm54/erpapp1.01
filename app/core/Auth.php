<?php
require_once BASE_PATH . '/app/core/Role.php';
require_once BASE_PATH . '/app/models/User.php';

class Auth{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Verifica que el usuario tenga un tenant seleccionado.
     */
    public static function hasTenant(): bool
    {
        return isset($_SESSION['tenant_id']) && isset($_SESSION['tenant_dbname']);
    }

    /**
     * Retorna el ID del tenant actual.
     */
    public static function getTenantId(): ?int
    {
        return $_SESSION['tenant_id'] ?? null;
    }

    /**
     * Retorna el nombre de la BD del tenant actual.
     */
    public static function getTenantDb(): ?string
    {
        return $_SESSION['tenant_dbname'] ?? null;
    }

    /**
     * Retorna el nombre del tenant actual.
     */
    public static function getTenantName(): ?string
    {
        return $_SESSION['tenant_nombre'] ?? null;
    }

    /**
     * Retorna el host del tenant actual.
     */
    public static function getTenantHost(): ?string
    {
        return $_SESSION['tenant_host'] ?? 'localhost';
    }

    /**
     * Establece el tenant en sesión y conecta la BD.
     */
    public static function setTenant(int $tenantId, string $dbname, string $nombre, string $host = 'localhost'): void
    {
        $_SESSION['tenant_id']       = $tenantId;
        $_SESSION['tenant_dbname']   = $dbname;
        $_SESSION['tenant_nombre']   = $nombre;
        $_SESSION['tenant_host']     = $host;
        $_SESSION['_tenant_connected'] = $dbname;

        Database::connectTenant($dbname, $host);

        // Sincronizar el usuario actual al tenant (para foreign keys)
        if (self::check()) {
            require_once BASE_PATH . '/app/models/User.php';
            $userModel = new User();
            $userModel->syncToTenant($_SESSION['user_id'], $dbname, $host);
        }
    }

    /**
     * Limpia el tenant de la sesión y desconecta la BD.
     */
    public static function clearTenant(): void
    {
        unset($_SESSION['tenant_id']);
        unset($_SESSION['tenant_dbname']);
        unset($_SESSION['tenant_nombre']);
        unset($_SESSION['tenant_host']);
        unset($_SESSION['_tenant_connected']);

        Database::disconnectTenant();
    }

    /**
     * Verifica si el usuario es superadmin (acceso a todos los tenants).
     */
    public static function isSuperAdmin(): bool
    {
        $user = self::getCurrentUser();
        return $user && strtoupper($user['rol']) === 'ADMIN';
    }

    /**
     * Verifica si el usuario puede gestionar su empresa (ADMIN o USUARIO).
     */
    public static function isEmpresaAdmin(): bool
    {
        $user = self::getCurrentUser();
        return $user && in_array(strtoupper($user['rol']), ['ADMIN', 'USUARIO']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }

    /**
     * Requiere que haya un tenant seleccionado.
     * Si no hay tenant pero el usuario es admin, lo redirige al selector.
     * Si no es admin ni tiene tenant, al login.
     */
    public static function requireTenant(): void
    {
        self::requireLogin();

        if (!self::hasTenant()) {
            if (self::isSuperAdmin()) {
                header('Location: ' . BASE_URL . '/admin/select-tenant');
            } else {
                $_SESSION['error'] = 'No tienes acceso a ningún módulo. Contacta al administrador.';
                header('Location: ' . BASE_URL . '/auth/login');
            }
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireTenant();
        $userModel = new User();
        $user = $userModel->getCurrentUser();

        if (!$user || strtoupper($user['rol']) !== strtoupper($role)) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta sección.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        self::requireTenant();
        $userModel = new User();
        $user = $userModel->getCurrentUser();

        if (!$user || !in_array(strtoupper($user['rol']), array_map('strtoupper', $roles))) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta sección.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::check()) {
            return null;
        }
        $userModel = new User();
        return $userModel->findById($_SESSION['user_id']);
    }

    public static function hasRole(string $role): bool
    {
        $user = self::getCurrentUser();
        return $user && strtoupper($user['rol']) === strtoupper($role);
    }

    public static function hasAnyRole(array $roles): bool
    {
        $user = self::getCurrentUser();
        return $user && in_array(strtoupper($user['rol']), array_map('strtoupper', $roles));
    }
}
