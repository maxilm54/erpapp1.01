<?php

class Middleware
{
    public static function auth(): void
    {
        if (!Auth::check()) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }

    public static function guest(): void
    {
        if (Auth::check()) {
            header('Location: ' . BASE_URL);
            exit;
        }
    }

    public static function role(string $role): void
    {
        self::auth();
        if (!Auth::hasRole($role)) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta sección.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    public static function anyRole(array $roles): void
    {
        self::auth();
        if (!Auth::hasAnyRole($roles)) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta sección.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }
}