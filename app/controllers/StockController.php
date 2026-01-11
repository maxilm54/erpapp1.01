<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Stock.php';

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
        $stock = $this->model->stockProductos();

        $this->view('stock/productos', [
            'stock' => $stock
        ]);
    }

    public function materiasprimas()
    {
        $stock = $this->model->stockMateriasPrimas();

        $this->view('stock/materiasprimas', [
            'stock' => $stock
        ]);
    }
}