<?php
class Csrf{
    public static function generate():string{
        if(empty($_SESSION['csrf'])){
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function validate(string $token):bool{
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}