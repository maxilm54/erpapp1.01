<?php
class Controller{
    protected function view(string $view, array $data = []):void{
        extract($data);
        require BASE_PATH . "/app/views/layout/header.php";
        require BASE_PATH . "/app/views/{$view}.php";
        require BASE_PATH . "/app/views/layout/footer.php";
    }
}