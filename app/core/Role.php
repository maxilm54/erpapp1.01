<?php

class Role {
    const ADMIN    = 'ADMIN';
    const USUARIO  = 'USUARIO';
    const VISITOR  = 'VISITOR';

    public static function getAllRoles(): array {
        return [
            self::ADMIN   => 'Administrador',
            self::USUARIO => 'Operario',
            self::VISITOR => 'Visor',
        ];
    }

    public static function getRoleName(string $role): string {
        return self::getAllRoles()[strtoupper($role)] ?? 'Desconocido';
    }

    public static function isValidRole(string $role): bool {
        return isset(self::getAllRoles()[strtoupper($role)]);
    }

    /**
     * Roles que pueden gestionar usuarios (todos, incluido operario).
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
}
