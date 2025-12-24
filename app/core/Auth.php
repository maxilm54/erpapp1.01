<?php
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
}