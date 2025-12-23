<?php
require_once BASE_PATH . '/app/core/Database.php';
class Model{
    protected PDO $db;
    public function __construct(){
        $this->db = Database::getInstance();
    }
}