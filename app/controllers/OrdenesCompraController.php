<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/OrdenCompra.php';
require_once BASE_PATH.'/app/models/Proveedor.php';
require_once BASE_PATH.'/app/models/MateriaPrima.php';

class OrdenesCompraController extends Controller
{
    private OrdenCompra $oc;

    public function __construct()
    {
        $this->oc = new OrdenCompra();
    }

    public function index()
    {
        $this->view('ordenes_compras/index', [
            'title'=>'Órdenes de Compra',
            'items'=>$this->oc->all()
        ]);
    }

    public function create()
    {
        if ($_POST) {
            $db = Database::getInstance();
            $db->beginTransaction();

            try {
                $ocId = $this->oc->create([
                    'proveedor_id'=>$_POST['proveedor_id'],
                    'usuario_id'=>$_SESSION['user_id']
                ]);

                foreach ($_POST['items'] as $item) {
                    if ($item['cantidad'] > 0) {
                        $this->oc->addDetalle(  // aca esta la respuesta! lo llama tantas veces existan lineas en detalle
                            $ocId,
                            $item['materia_prima_id'],
                            $item['cantidad']
                        );
                    }
                }

                $db->commit();
                header('Location: '.BASE_URL.'/ordenescompra');
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }

        $this->view('ordenes_compras/form', [
            'title'=>'Nueva Orden de Compra',
            'proveedores'=>(new Proveedor())->all(),
            'materias_primas'=>(new MateriaPrima())->all()
        ]);
    }

    public function aprobar($id)
    {
        $this->oc->aprobar($id);
        header('Location: '.BASE_URL.'/ordenescompra');
    }

    public function show($id)
    {
        $orden = $this->oc->findWithDetalle($id);

        $this->view('ordenes_compras/show', [
            'title'=>'Orden de Compra',
            'orden'=>$orden
        ]);
    }

    public function edit($id)
    {
        $orden = $this->oc->findWithDetalle($id);

        if (!$orden) {
            die('Orden no encontrada');
        }

        if ($orden['estado'] !== 'PENDIENTE') {
            die('No se puede editar una OC aprobada');
        }

        if ($_POST) {
            $this->oc->update($id, $_POST);
            header('Location: '.BASE_URL.'/ordenescompra');
            exit;
        }

        $this->view('ordenes_compras/form', [            
            'title'            => 'Editar Orden',
            'orden'            => $orden,
            'detalle'          => $orden['detalle'],
            'materias_primas'  => $this->oc->materiasPrimas(), // 👈 CLAVE
            'proveedores'      => $this->oc->proveedores()    
        ]);
    }
}