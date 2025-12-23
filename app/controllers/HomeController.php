<?php
require_once BASE_PATH . 'app/core/Controller.php';

class HomeController extends Controller{
    public function index():void{
        $this->view('home/index', ['title' => 'ERP app funcionando 🚀']);
    }
}