<?php // app/core/Controller.php
class Controller{
    protected function view(string $view, array $data = []):void{
        extract($data);
        require BASE_PATH . "/app/views/layout/header.php";
        require BASE_PATH . "/app/views/{$view}.php";
        require BASE_PATH . "/app/views/layout/footer.php";
    }

    protected function login2(string $view, array $data = []):void{
        extract($data);
        require BASE_PATH . "/app/views/layout/headerlogin.php";
        require BASE_PATH . "/app/views/{$view}.php";
        require BASE_PATH . "/app/views/layout/footer.php";
    }

    protected function modal(string $view, array $data = []):void{
         extract($data);
        require BASE_PATH . "/app/views/{$view}.php";
    }
}