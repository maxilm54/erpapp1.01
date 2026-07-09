<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Remitosalida.php';
require_once BASE_PATH.'/app/models/Notapedido.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/services/MailService.php';
class RemitosSalidaController extends Controller
{
    private RemitoSalida $model;
    private NotaPedido $np;
    private Cliente $cli;

    public function __construct()
    {
        $this->model = new RemitoSalida();
        $this->np = new NotaPedido();
        $this->cli = new Cliente();
    }
    public function index()
    {
        $remitos = $this->model->all();

        $this->view('remitos_salida/index', [
            'remitos' => $remitos
        ]);
    }
        public function pdf($id) // funcion para descargar los pdf remitados
    {
        validarId($id, BASE_URL . '/remitossalida');
        $remito = $this->model->find($id);

        if (!$remito || !$remito['pdf_path']) {
            die('PDF no disponible');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($remito['pdf_path']) . '"');
        readfile($remito['pdf_path']);
        exit;
    }

        public function create($notaPedidoId) // llamo al form para remitar una NP
    {
        validarId($notaPedidoId, BASE_URL . '/remitossalida');
        $np = $this->np->findWithPendientes((int)$notaPedidoId); // obtengo la np con los pendientes de remito desde el modelo notas pedido
        $cli_act = $this->cli->cliactive((int)$np['id_cliente']);
        if($cli_act['activo']===0){
            $_SESSION['error'] = 'Cliente Inactivo, No se puede remitar';
            error_log('Cliente Inactivo, No se puede remitar');
            header("Location: " . BASE_URL . "/notaspedido");
            exit;

        }
        error_log(print_r($np, true).'-'.__FILE__.'-'.__LINE__); // guardo un registro de la infoque me devuelve el modelo
        if (!$np ) { // si no existe o hay alguna error en la variable devuelvo el error, dejo un log y redirijo
            $_SESSION['error'] = 'Nota de Pedido, inexistente';
            error_log('Nota de Pedido, inexistente');
            header("Location: " . BASE_URL . "/notaspedido");
            exit;
        }
        if (!$np || $np['estado'] !== 'APROBADA') { //si np es nulo o no esta aprobada alerto redirijo y dejo el log
            $_SESSION['error'] = 'Nota de Pedido # '.$notaPedidoId .' No Aprobada o Anulada';
            error_log('Nota de Pedido, no aprobada: ID '.$notaPedidoId.' Estado: '.$np['estado'].'-'.__FILE__.'-'.__LINE__);
            header("Location: " . BASE_URL . "/notaspedido");
            exit;
        }
        //hasta aqui si no hay errores sigo con el form

        if ($_SERVER['REQUEST_METHOD'] === 'POST') { //cuando desde el form envio el post inicio el proceso de generacion del remito
            error_log('POST recibido para crear Remito de Salida: ' . print_r($_POST, true) . ' - ' . __FILE__ . ':' . __LINE__);
            try {
                if (empty($_POST['items'])) { //si items esta vacio no puedo generar el remito y genero la excepcion, esto no deberia pasar pero por control se deja el bloqueo
                    throw new Exception('No hay productos para remitar');
                }
                error_log('Creando Remito de Salida '.print_r($_POST['items'], true).'-'.__FILE__.'-'.__LINE__); //se ascienta un log de los items a remitir
                $id = $this->model->create(
                    (int)$notaPedidoId,
                    (int)$_SESSION['user_id'],
                    $_POST['items'],
                    $_POST['observaciones'] ?? null
                );
                error_log('Remito de Salida creado con ID: ' . $id . ' - ' . __FILE__ . ':' . __LINE__);
                // GENERAR Y GUARDA PDF AUTOMÁTICO (antes descargaba auto ahora no por seguridad)
                //$this->model->generarPdf($id); -> obsoleto, ahora lo hago y lo guardo en la db
                $this->model->generarYGuardarPdf($id);
                $_SESSION['success'] = 'Remito de Salida creado correctamente.';
                $this->reenviar($id); //llamo a la funcion de reenviar mail automaticamente al crear el remito
                header("Location: " . BASE_URL . "/remitossalida/show/$id");
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                error_log('Error al crear Remito de Salida: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            }
        }

        $this->view('remitos_salida/form', [ //muestro el form con la info de la np para remitar
            'np' => $np
        ]);
    }

    public function show($id)
    {
        validarId($id, BASE_URL . '/remitossalida');
        $remito = $this->model->find($id);
        if (!$remito){
            $_SESSION['error'] = 'El remito no existe.';
            error_log('Remito no encontrado - ' . __FILE__ . ':' . __LINE__);
            die('Remito no encontrado');
        } 

        $this->view('remitos_salida/show', [
            'remito' => $remito
        ]);
    }

    public function reenviar($id) //numero de remito a reenviar
    {
        validarId($id, BASE_URL . '/remitossalida');
        $remito = $this->model->findCompleto((int)$id); //traigo el array de datos del remito completo

        if (!$remito || empty($remito['pdf_path'])) {
            $_SESSION['error'] = 'Remito inválido o sin PDF.';
            header("Location: " . BASE_URL . "/remitossalida/show/$id");
            exit;
        }

        try {
            $mail = new MailService(); //instancio el servicio de mail
            $mail->enviarRemito(    //llamo al metodo para enviar el remito por mail
                $remito['cliente'],
                $remito,
                $_SESSION['user_id']
            );

            $_SESSION['success'] = 'Remito reenviado correctamente.';

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al reenviar remito.' . $e->getMessage();
            error_log('Error al reenviar remito: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
        }

        header("Location: " . BASE_URL . "/remitossalida/show/$id");
        exit;
    }


}
