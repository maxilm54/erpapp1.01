<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/OrdenProduccion.php';
require_once BASE_PATH.'/app/models/Receta.php';
require_once BASE_PATH.'/app/models/Producto.php';
class OrdenproduccionController extends Controller
{
    private OrdenProduccion $model;

    public function __construct()
    {
        $this->model = new OrdenProduccion();
    }

    public function index() // muestra todas las OP (#-producto-cantidad-estado-ver)
    {
        $this->view('produccion/index', [
            'ordenes' => $this->model->all()
        ]);
    }
    //En la Vista Create, cuando generamos la orden, hacemos el pedido de MP, lo que hay de stock se reserva y lo que falta se pide.
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                error_log('se a intentado un registro rechazado por csrf. '.__FILE__.':'.__LINE__);
                $_SESSION['error'] = 'CSRF inválido. Intente nuevamente.';
                header('Location: '.BASE_URL.'/ordenproduccion/create');
                die('CSRF inválido');
            }
            try {
                $id = $this->model->crear([
                    'producto_id' => $_POST['producto_id'],
                    'receta_id'   => $_POST['receta_id'],
                    'cantidad'    => $_POST['cantidad'],
                    'f_entrega'   => $_POST['fecha_entrega'],
                    'observaciones' => $_POST['observaciones'],
                    'usuario_id'  => $_SESSION['user_id']
                ]);

                $_SESSION['success'] = 'Orden de producción creada';
                header('Location: '.BASE_URL.'/ordenproduccion/show/'.$id);
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                error_log('error al intentar crear orden de produccion'.$e->getMessage());
                header('Location: '.BASE_URL.'/ordenproduccion');
                exit;
            }
        }
        
        $this->view('produccion/create', [ //muestro la generacion de la orden el form y las recetas elijo receta, cantidad, fecha y observaciones. Visualiza la alerta de stock o no dependiendo si hay stock o no y al faltante da la opcion de generar una oc.
            'recetas' => (new Receta())->all(), //metodo que trae todas las recetas
            'productos' => (new Producto())->all() // metodo que trae los productos en la vista con ajax llama a check stock.
        ]);
    }
    public function addavance(int $id_avance){
        validarId($id_avance, BASE_URL . '/ordenproduccion');
        $registro=$this->model->confirmaravance($id_avance);
        $id_op=$this->model->numeroOP((int) $id_avance);
        if($registro===true){
            $_SESSION['SUCCESS']="Registro de avance confirmado!";
            header('Location: '.BASE_URL.'/ordenproduccion/avance/'.$id_op);
            exit;
        }else{
            error_log("Hubo un error al agregar la confirmacion del registro de avance $id_avance. ".__FILE__.':'.__LINE__);
            header('Location: '.BASE_URL.'/ordenproduccion');
            exit;
        }
    }
    public function avance(int $id) //controlador de eventos de produccion, vista avance/id,Modal del boton + iniciar produccion.
    {
        validarId($id, BASE_URL . '/ordenproduccion');
        $orden = $this->model->find((int)$id); // obtengo datos de cabecera OP
        $orden_det = $this->model->findopdetalle((int)$id); //Obtengo datos de detalle de OP (puede estar vacio o tener varios registros)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { // viene del modal de agregar produccion, se registra el avance y se actualiza el stock de producto y materia prima consumida, si es que hubo consumo. Se debe actualizar el estado de la OP a EN_PRODUCCION si no estaba en ese estado.
            //solo entro si hubo envio desde el modal.
            error_log('llega al post del controlador avance, fecha:'.FECHA_ACTUAL.' con data: '.print_r($_POST,true));
            //die();
            if (!Csrf::validate($_POST['csrf_token'])) {
                error_log('se a intentado un registro rechazado por csrf. '.__FILE__.':'.__LINE__);
                $_SESSION['error'] = 'CSRF inválido. Intente nuevamente.';
                header('Location: '.BASE_URL.'/ordenproduccion/avance/'.$id);//me vuelvo a la vista rechazando el inicio de produccion y alertando el csrf
                die('CSRF inválido');
            }
            //antes de llamar al modelo para registrar el avance,primero controlar stock de Materia Prima y luego el modelo se encarga de rechazar si se superan las cantidades.
            try {
                $stockCheck = $this->model->producirvsStockMP($_POST['receta_id'], $_POST['cantidad_producida']);
                error_log('Resultado del chequeo de stock para avance OP '.$id.': '.print_r($stockCheck,true));
                //error=sin stock, warnign stock insuficiente, niguno permite producir.
                //die();
                if ($stockCheck['estado'] === 'error') {
                    $_SESSION['error'] = 'No hay stock suficiente para producir la cantidad solicitada.';
                    header('Location: '.BASE_URL.'/ordenproduccion/avance/'.$id);
                    exit;
                }
                if ($stockCheck['estado'] === 'warning') {
                    $_SESSION['error'] = 'No hay stock suficiente para producir la cantidad solicitada.';
                    header('Location: '.BASE_URL.'/ordenproduccion/avance/'.$id);
                    exit;
                }
            } catch (Exception $e) {
                error_log('Error al chequear stock antes de registrar avance: '.$e->getMessage());
                $_SESSION['error'] = 'Error al verificar stock. Intente nuevamente.';
                header('Location: '.BASE_URL.'/ordenproduccion/avance/'.$id);
                exit;
            }
            //si el stock esta ok, en todas sus MP para la contidad a producir permite generar el registro a producir.
            try {
                $id = $this->model->avances([
                    'orden_id'   => $_POST['orden_id'],
                    'producto_id' => $_POST['producto_id'],
                    'cantidad_producida'    => $_POST['cantidad_producida'],
                    'f_registro'   => FECHA_ACTUAL,
                    'observaciones' => $_POST['observaciones'],
                    'usuario_id'  => $_SESSION['user_id']
                ]);
                //En el modelo:
                //si el model avance dio bien tengo que descontar la mercaderia, primero cambio el estado (consumido) luego las elimino para que el trigger las pase a historico
                //Se debe aumentar el stock del producto producido

            } catch (Exception $e) {
                error_log('sesion anterior'.print_r($_SESSION['error'],true));
                $_SESSION['error'] .= $e->getMessage();
                error_log('error al intentar crear registro de avance en OP '.$id.' - '.$e->getMessage());
                header('Location: '.BASE_URL.'/ordenproduccion/avance/'.$id);
                exit;
            }
            //$_SESSION['success'] = 'Registro de produccion creado';
            header('Location: '.BASE_URL.'/ordenproduccion/avance/'.$id);
            exit;
        }
        if (!$orden) {
            $_SESSION['error'] = 'Orden no encontrada';
            header('Location: '.BASE_URL.'/produccion');
            exit;
        }

        $this->view('produccion/avance', ['orden'=>$orden,'orden_det'=>$orden_det,'csrf' => Csrf::generate()]);
    }


    public function show($id) //ver una OP en datalle estado y requerimientos
    {
        validarId($id, BASE_URL . '/ordenproduccion');
        $orden = $this->model->find((int)$id);
        $reservas = $this->model->findreservas((int)$id);
        $orden_det = $this->model->findopdetalle((int)$id);
        if (!$orden) {
            $_SESSION['error'] = 'Orden no encontrada';
            header('Location: '.BASE_URL.'/produccion');
            exit;
        }

        $this->view('produccion/show', compact('orden', 'reservas','orden_det'));
    }

    public function checkStock() //este controlador se usa para el chequeo de stock via ajax y poder mostrar si hay stock para las distintas cantidads que se vayan eligiendo a producir
    {
        header('Content-Type: application/json');

        $recetaId  = (int)($_POST['receta_id'] ?? 0);
        $cantidad  = (float)($_POST['cantidad'] ?? 0);

        if ($recetaId <= 0 || $cantidad <= 0) {
            echo json_encode(['status' => 'none']);
            return;
        }

        $model = new OrdenProduccion();
        $resultado = $model->chequearStockReceta($recetaId, $cantidad); // desde el modelo consulta la receta y calcula el stock de materias primas para el producto

        echo json_encode($resultado);
    }
    // realizar la metodologia del consumo o devolucion de mp en reservas
    
    public function producir($id) // habilita el proceso de produccion de x cantidad solicitada en la OP
    {
        validarId($id, BASE_URL . '/ordenproduccion');
        $this->model->actualizarEstado((int)$id, 'EN_PRODUCCION');
        $_SESSION['success'] = 'Orden de producción en estado "En Producción"';
        header('Location: '.BASE_URL.'/ordenproduccion/show/'.$id);
        exit;
    }
    public function finalizarproduccion($id)
    {
        validarId($id, BASE_URL . '/ordenproduccion');
        $this->model->actualizarEstado((int)$id, 'FINALIZADA');
        $_SESSION['success'] = 'Orden de producción finalizada';
        header('Location: '.BASE_URL.'/ordenproduccion/show/'.$id);
        exit;
    }
    //funcion para cancelar la OP, se deve devolver a stock la materia prima que estaba reservada.
    public function cancelarproduccion($id)
    {
        validarId($id, BASE_URL . '/ordenproduccion');
        //$this->model->actualizarEstado((int)$id, 'CANCELADA');
        $this->model->cancelarproduccion($id);
        $_SESSION['success'] = 'Orden de producción cancelada';
        header('Location: '.BASE_URL.'/ordenproduccion/show/'.$id);
        exit;
    }
}