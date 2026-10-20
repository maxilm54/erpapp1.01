<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Stock.php';
require_once BASE_PATH.'/app/models/Deposito.php';

class StockController extends Controller
{
    private Stock $model;

    public function __construct()
    {
        $this->model = new Stock();
    }

    public function index()
    {
        $this->view('stock/index');
    }

    public function productos()
    {
        $depositoId = !empty($_GET['deposito_id']) ? (int)$_GET['deposito_id'] : null;
        $stock = $this->model->stockProductos($depositoId);

        $this->view('stock/productos', [
            'stock' => $stock,
            'depositos' => (new Deposito())->all(),
            'deposito_id' => $depositoId
        ]);
    }

    public function materiasprimas()
    {
        $depositoId = !empty($_GET['deposito_id']) ? (int)$_GET['deposito_id'] : null;
        $stock = $this->model->stockMateriasPrimas($depositoId);

        $this->view('stock/materiasprimas', [
            'stock' => $stock,
            'depositos' => (new Deposito())->all(),
            'deposito_id' => $depositoId
        ]);
    }
}