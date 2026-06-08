<?php
require_once BASE_PATH . '/app/core/Role.php';
require_once BASE_PATH . '/app/models/User.php';

class Auth{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        $userModel = new User();
        $user = $userModel->getCurrentUser();
        
        if (!$user || $user['role'] !== $role) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta sección.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        self::requireLogin();
        $userModel = new User();
        $user = $userModel->getCurrentUser();
        
        if (!$user || !in_array($user['role'], $roles)) {
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
        return $user && $user['role'] === $role;
    }

    public static function hasAnyRole(array $roles): bool
    {
        $user = self::getCurrentUser();
        return $user && in_array($user['role'], $roles);
    }
}