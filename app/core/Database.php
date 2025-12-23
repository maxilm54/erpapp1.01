<?php
class Database{
    private static ?PDO $instance=null;
    public static function getInstance():PDO{
        if(self::$instance===null){
            $config=require BASE_PATH . '/app/config/database.php';
            $dsn="mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            self::$instance=new PDO($dsn,$config['user'],$config['pass'],[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
        }
        return self::$instance;
    }
}