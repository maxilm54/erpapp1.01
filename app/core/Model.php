<?php
class Model{
    protected PDO $db;
    public function __construct(){
        $this->db = Database::getInstance();
        //error_log(print_r($this->db, true));
        //error_log(print_r($_SESSION, true));
    }
}