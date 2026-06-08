<?php

class Role {
    const ADMIN = 'admin';
    const GERENTE = 'gerente';
    const OPERARIO = 'operario';
    
    public static function getAllRoles(): array {
        return [
            self::ADMIN => 'Administrador',
            self::GERENTE => 'Gerente',
            self::OPERARIO => 'Operario'
        ];
    }
    
    public static function getRoleName(string $role): string {
        return self::getAllRoles()[$role] ?? 'Desconocido';
    }
    
    public static function isValidRole(string $role): bool {
        return isset(self::getAllRoles()[$role]);
    }
}