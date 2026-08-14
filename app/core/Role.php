<?php

class Role {
    const SUPERADMIN = 'SUPERADMIN';
    const ADMIN      = 'ADMIN';
    const USUARIO    = 'USUARIO';
    const VISITOR    = 'VISITOR';
    const GERENTE_FINANCIERO = 'GERENTE_FINANCIERO';

    public static function getAllRoles(): array {
        return [
            self::SUPERADMIN => 'Super Administrador',
            self::ADMIN      => 'Administrador',
            self::USUARIO    => 'Operario',
            self::VISITOR    => 'Visor',
            self::GERENTE_FINANCIERO => 'Gerente Financiero',
        ];
    }

    /**
     * Roles que un tenant admin puede asignar (no SUPERADMIN).
     */
    public static function getAssignableRoles(): array {
        return [
            self::ADMIN      => 'Administrador',
            self::USUARIO    => 'Operario',
            self::VISITOR    => 'Visor',
            self::GERENTE_FINANCIERO => 'Gerente Financiero',
        ];
    }

    public static function getRoleName(string $role): string {
        return self::getAllRoles()[strtoupper($role)] ?? 'Desconocido';
    }

    public static function isValidRole(string $role): bool {
        return isset(self::getAllRoles()[strtoupper($role)]);
    }

    /**
     * Verifica si un rol puede asignarse a nivel tenant.
     */
    public static function canAssignInTenant(string $role): bool {
        return isset(self::getAssignableRoles()[strtoupper($role)]);
    }

    /**
     * Roles que pueden gestionar usuarios dentro del tenant.
     */
    public static function canManageUsers(): array {
        return [self::ADMIN, self::USUARIO];
    }

    /**
     * Verifica si un rol puede gestionar usuarios.
     */
    public static function canManage(string $role): bool {
        return in_array(strtoupper($role), self::canManageUsers());
    }

    /**
     * Roles que pueden ver el panel de empresa.
     */
    public static function canSeeEmpresa(): array {
        return [self::ADMIN, self::USUARIO];
    }

    /**
     * Verifica si un rol puede ver el panel de empresa.
     */
    public static function canSeeEmpresaCheck(string $role): bool {
        return in_array(strtoupper($role), self::canSeeEmpresa());
    }

    /**
     * Roles que pueden acceder a comprobantes.
     */
    public static function canSeeSdcomp(): array {
        return [self::ADMIN, self::GERENTE_FINANCIERO];
    }

    /**
     * Verifica si un rol puede ver comprobantes.
     */
    public static function canSeeSdcompCheck(string $role): bool {
        return in_array(strtoupper($role), self::canSeeSdcomp());
    }
}
