<?php
class Router{
    public function run():void{
        $url=$_GET['url']??'home/index';
        $url=explode('/',trim($url,'/'));

        $controllerName=ucfirst($url[0]??'home').'Controller';
        $method=$url[1]??'index';
        $params=array_slice($url,2);
        $controllerFile=BASE_PATH . "/app/controllers/{$controllerName}.php";

        if(!file_exists($controllerFile)){
            http_response_code(404);
            echo "Controller not found.";
            exit;
        }

        require_once $controllerFile;
        $controller=new $controllerName();
        if(!method_exists($controller,$method)){
            http_response_code(404);
            echo "Method not found.";
            exit;
        }
        call_user_func_array([$controller,$method],$params);
    }
}