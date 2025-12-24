<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Cliente.php';
class ClientesController extends Controller{
    private Cliente $cliente;
    public function __construct(){
        $this->cliente=new Cliente();
    }

    public function index():void{
        $this->view('clientes/index',['title'=>'Clientes','clientes'=>$this->cliente->all()]);
    }

    public function create():void{
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->cliente->create($_POST);
            header('Location: ' . BASE_URL . '/clientes');
            exit;
        }

        $this->view('clientes/form', [
            'title' => 'Nuevo Cliente'
        ]);
    }

    public function edit(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->cliente->update($id, $_POST);
            header('Location: ' . BASE_URL . '/clientes');
            exit;
        }

        $this->view('clientes/form', [
            'title' => 'Editar Cliente',
            'cliente' => $this->cliente->find($id)
        ]);
    }

    public function delete(int $id): void
    {
        $this->cliente->delete($id);
        header('Location: ' . BASE_URL . '/clientes');
    }


}