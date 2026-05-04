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
    public function anular($id)
    {
        $this->oc->anular($id);
        header('Location: '.BASE_URL.'/ordenescompra');
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token OrdenesCompraController::create'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/ordenescompra/create');
                exit;
            }
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
                            $item['cantidad'],
                            $item['precio_unitario'],
                            $item['moneda'] ?? '$'
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token OrdenesCompraController::edit'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/ordenescompra/edit/' . $id);
                exit;
            }

            $this->oc->update($id, $_POST);
            header('Location: '.BASE_URL.'/ordenescompra');
            exit;
        }
        $orden = $this->oc->findWithDetalle($id);

        if (!$orden) {
            $_SESSION['error'] = 'La Orden No existe.';
            error_log('Error al buscar la OC OrdenesCompraController::edit'.__FILE__.':'.__LINE__);
            header('Location: ' . BASE_URL . '/ordenescompra/edit/' . $id);
            die('Orden no encontrada');
        }

        if ($orden['estado'] !== 'PENDIENTE') {
            $_SESSION['error'] = 'Esta Orden Esta Aprobado, no se puede esditar.';
            error_log('Intento de editar OC aprobada OrdenesCompraController::edit'.__FILE__.':'.__LINE__);
            header('Location: ' . BASE_URL . '/ordenescompra/edit/' . $id);
            die('No se puede editar una OC aprobada');
        }

        $this->view('ordenes_compras/form', [
            'title'            => 'Editar Orden',
            'orden'            => $orden,
            'detalle'          => $orden['detalle'],
            'materias_primas'  => $this->oc->materiasPrimas(), // 👈 CLAVE
            'proveedores'      => $this->oc->proveedores()
        ]);
    }

    public function generardesdefaltantes() // funcion que genera una orden que viene desde la OP con faltantes
    {
        $input = json_decode(file_get_contents("php://input"), true);
        /**Aca se muestra el array quye viene desde la vista de producion:
         * [faltantes] =>
        *([0] =>(
        *   [materia_prima_id] => 11
        *   [materia_prima] => Tapas Frasco 450
        *   [necesario] => 11
        *   [disponible] => 10
        *   [faltante] => 1
        *   )
        *)
        */

        error_log('inputs de faltantes en vista produccion '.print_r($input,true)); //traenmos data desde la vista prodccion

        if (empty($input['faltantes'])) {
            echo json_encode(['success' => false]);
            return;
        }
        try {
            
            foreach ($input['faltantes'] as $item) { // recorre las materias primas que vienen desde la vista de la produccion-> faltantes
                $ordenCompraId = null;
                error_log('datos de cada item faltante->'.print_r($item,true));
                $materiaId = (int)$item['materia_prima_id'];
                $cantidadNecesaria=(int)$item['necesario'];
                $cantidadFaltante=(int)$item['faltante'];
                error_log('maeriaprima: '.$materiaId.' - Cantidad Necesaria: '.$cantidadNecesaria.' - Cant Faltante: '.$cantidadFaltante);
                if($item['materia_prima_id'] <= 0){ //el id de mp debe ser mayor que sero
                    error_log("if id mp=0");
                    continue;
                }
                // 🔹 1️⃣ Calcular stock proyectado real
                $stockData = $this->oc->calcularStockProyectado($materiaId);
                error_log('Stockdata:'.print_r($stockData,true));
                if(!$stockData){ continue;}
                $stockProyectado = $stockData['stock_actual']+ $stockData['en_compra'];
                // este es nuestro stock disponible real + virtual porque lo que esta en oc no llego todavia
                if (($stockProyectado-$cantidadNecesaria) > $cantidadFaltante ) { //revisar aca porque siempre entra
                    error_log('Entro al if de stock proyectado cubierto, necesario: '.$cantidadNecesaria.' proyectado: '.$stockProyectado.'. Stockactual: '.$stockData['stock_actual'].', stockreserva: '.$stockData['reservado'].', StockCompra: '.$stockData['en_compra']);
                    throw new Exception("El stock proyectado es mayor al solicitado.");
                }
                // CORREGIR ACA LA EVALUCION DEL STOCK Y VALIDACION DE CANTIDADES A COMPRAR
                $cantidadAComprar = abs($cantidadFaltante);
                // 🔹 2️⃣ Buscar OC editable (NO APROBADA), aca no tengo que buscar cualquier oc, tengo que buscar una que ya tenga esa materia prima, ya que si elijo cualier oc , puede que me cree un pedido de compra en un proveedor que no vende esa materia prima.
                //Cambio de planes! siempre que se haga una OP la OC va a ser una nueva OC punto! NUEVO!
                //por cada una de las materias primas se crea una nueva oc
                if (!$ordenCompraId) {
                    error_log("Se va a crear una nueva OC y se le van agregar los detalles");
                    $ordenCompraId = $this->oc->crearOc(); // devuelvo el id de una oc que no existia y se creo nuev
                    $this->oc->addMpOc($ordenCompraId,$materiaId,$cantidadAComprar);
                }
            }
            echo json_encode([
                'success' => true,
                'id' => $ordenCompraId
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            echo json_encode([
                'success' => false
            ]);
        }
    }
}