<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\StockService;
use App\Models\CuentaCorrienteEmpresa;
use App\Models\CategoriaGastoIngreso;
use App\Auth;

class CuentaCorrienteEmpresaController extends Controller
{
    public function index()
    {
        $cuentaModel = new CuentaCorrienteEmpresa();
        $categorias = (new CategoriaGastoIngreso())->all();
        $movements = $cuentaModel->findAll(['tipo' => 'ingreso']);
        $movementsG = $cuentaModel->findAll(['tipo' => 'gasto']);
        $balance = $cuentaModel->getBalance();

        $this->view('cuenta_corriente_emp/index', [
            'title' => 'Movimientos Empresa',
            'movements' => $movements,
            'movementsG' => $movementsG,
            'balance' => $balance,
            'categorias' => $categorias
        ]);
    }

    public function register(array $data)
    {
        // Expected form fields: tipo, categoria_id, descripcion, monto, fecha, referencia_id, referencia_tipo
        $cuentaModel = new CuentaCorrienteEmpresa();
        $cuentaModel->create($data);
        $this->redirect(BASE_URL . '/cuentas/corrientes');
    }

    public function delete(int $id)
    {
        $cuentaModel = new CuentaCorrienteEmpresa();
        $cuentaModel->delete($id);
        $this->redirect(BASE_URL . '/cuentas/corrientes');
    }

    public function edit(int $id)
    {
        $cuentaModel = new CuentaCorrienteEmpresa();
        $movement = $cuentaModel->findById($id);
        $categorias = (new CategoriaGastoIngreso())->all();

        $this->view('cuenta_corriente_emp/edit', [
            'title' => 'Editar Movimiento',
            'movement' => $movement,
            'categorias' => $categorias
        ]);
    }

    public function update(int $id, array $data)
    {
        $cuentaModel = new CuentaCorrienteEmpresa();
        $cuentaModel->update($id, $data);
        $this->redirect(BASE_URL . '/cuentas/corrientes');
    }
}
