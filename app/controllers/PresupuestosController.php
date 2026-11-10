<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Presupuesto.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/models/Producto.php';
require_once BASE_PATH.'/app/models/Configuracion.php';
require_once BASE_PATH.'/app/models/Campopersonalizado.php';
require_once BASE_PATH.'/app/models/Adjunto.php';
require_once BASE_PATH.'/app/models/Notapedido.php';
require_once BASE_PATH.'/app/services/MailService.php';

class PresupuestosController extends Controller
{
    private Presupuesto $pr;

    public function __construct()
    {
        $this->pr = new Presupuesto();
    }

    public function index()
    {
        $this->view('presupuestos/index', [
            'presupuestos' => $this->pr->all()
        ]);
    }

    /**
     * Fecha de entrega comprometida (opcional) desde el form: solo acepta un
     * YYYY-MM-DD valido, cualquier otra cosa entra como NULL. La usa store/update.
     */
    private function fechaEntregaDesdePost(): ?string
    {
        $fecha = trim((string)($_POST['fecha_entrega'] ?? ''));
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }
        return $fecha;
    }

    public function create()
    {
        $this->view('presupuestos/form', [
            'clientes'  => (new Cliente())->allactive(),
            'productos' => (new Producto())->all(),
            'usarNomenclador' => (new Configuracion())->activa('usar_nomenclador', true),
            'usarAdjuntos' => (new Configuracion())->activa('usar_adjuntos', false),
            'cpCampos'  => (new Campopersonalizado())->porModulo('PRESUPUESTO'),
            'cpValores' => [],
        ]);
    }

    public function store()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token PresupuestosController::store'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/presupuestos/create');
                exit;
            }

            $items = $_POST['items'] ?? [];
            if (empty($items)) {
                $_SESSION['error'] = 'Debe agregar al menos un producto al presupuesto.';
                header('Location: ' . BASE_URL . '/presupuestos/create');
                exit;
            }

            try {
                $id = $this->pr->create([
                    'cliente_id'   => (int) $_POST['cliente_id'],
                    'usuario_id'   => $_SESSION['user_id'],
                    'items'        => $items,
                    'observaciones'=> $_POST['observaciones'] ?? '',
                    'fecha_entrega'=> $this->fechaEntregaDesdePost()
                ]);

                // Campos personalizados del formulario (config Empresa -> Configuraciones)
                try {
                    (new Campopersonalizado())->guardarDesdePost('PRESUPUESTO', (int)$id, $_POST['campos_cp'] ?? []);
                } catch (Exception $e) {
                    error_log('[Campos PR] ' . $e->getMessage());
                }

                // Adjuntos subidos en el form de creacion (config activa)
                if ((new Configuracion())->activa('usar_adjuntos', false)
                    && !empty($_FILES['archivos_adjuntos']['name'][0])) {
                    try {
                        $n = (new Adjunto())->guardarMultiples('PRESUPUESTO', (int)$id, $_FILES['archivos_adjuntos']);
                        if ($n > 0) {
                            $_SESSION['success'] = "Presupuesto creado con {$n} adjunto(s).";
                        }
                    } catch (Exception $e) {
                        error_log('[Adjuntos PR] ' . $e->getMessage());
                        $_SESSION['error'] = 'El presupuesto se creó pero falló un adjunto: ' . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/presupuestos/create');
                exit;
            }
            
            // Generar PDF inmediatamente
            try {
                $pdfPath = $this->pr->generarYGuardarPdf($id);
                error_log("[PDF Presupuesto] Generado: {$pdfPath}");
            } catch (Exception $e) {
                error_log("[PDF Presupuesto] Error al generar PDF: " . $e->getMessage());
            }
        }

        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    public function edit($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);

        if (!$pr || $pr['estado'] !== 'BORRADOR') {
            die('Presupuesto no editable');
        }

        $this->view('presupuestos/form', [
            'presupuesto' => $pr,
            'clientes'    => (new Cliente())->all(),
            'productos'   => (new Producto())->all(),
            'usarNomenclador' => (new Configuracion())->activa('usar_nomenclador', true),
            'usarAdjuntos' => (new Configuracion())->activa('usar_adjuntos', false),
            'cpCampos'    => (new Campopersonalizado())->porModulo('PRESUPUESTO'),
            'cpValores'   => (new Campopersonalizado())->valoresMapa('PRESUPUESTO', (int)$id),
        ]);
    }

    public function update($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token PresupuestosController::update'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/presupuestos/update/' . $id);
                exit;
            }

            $items = $_POST['items'] ?? [];
            if (empty($items)) {
                $_SESSION['error'] = 'Debe agregar al menos un producto al presupuesto.';
                header('Location: ' . BASE_URL . '/presupuestos/update/' . $id);
                exit;
            }

            try {
                $this->pr->update($id, [
                    'cliente_id'   => $_POST['cliente_id'],
                    'items'        => $items,
                    'observaciones'=> $_POST['observaciones'] ?? '',
                    'fecha_entrega'=> $this->fechaEntregaDesdePost()
                ]);

                // Campos personalizados del formulario (config Empresa -> Configuraciones)
                try {
                    (new Campopersonalizado())->guardarDesdePost('PRESUPUESTO', (int)$id, $_POST['campos_cp'] ?? []);
                } catch (Exception $e) {
                    error_log('[Campos PR] ' . $e->getMessage());
                }

                // Adjuntos nuevos subidos en el form de edicion (config activa)
                if ((new Configuracion())->activa('usar_adjuntos', false)
                    && !empty($_FILES['archivos_adjuntos']['name'][0])) {
                    try {
                        (new Adjunto())->guardarMultiples('PRESUPUESTO', (int)$id, $_FILES['archivos_adjuntos']);
                    } catch (Exception $e) {
                        error_log('[Adjuntos PR] ' . $e->getMessage());
                        $_SESSION['error'] = 'El presupuesto se guardó pero falló un adjunto: ' . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/presupuestos/update/' . $id);
                exit;
            }
        }

        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    public function show($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);
        if (!$pr) die('Presupuesto no encontrado');

        $this->view('presupuestos/show', [
            'presupuesto' => $pr
        ]);
    }

    public function aprobar($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $this->pr->aprobar($id);

        // La NP se genera SIEMPRE automatica al aprobar el presupuesto
        // (hereda items, observaciones, adjuntos y campos personalizados).
        $config = new Configuracion();
        $adjuntos = new Adjunto();

        try {
            $npId = (int)$this->pr->getNotaPedidoByPresupuesto((int)$id);
            if (!$npId) {
                $presupuesto = $this->pr->find((int)$id);
                $items = [];
                $idx = 0;
                foreach ($presupuesto['detalle'] as $d) {
                    if ($d['cantidad'] <= 0) continue;
                    if (!empty($d['producto_id'])) {
                        $items[$idx++] = [
                            'producto_id' => (int)$d['producto_id'],
                            'cantidad'    => $d['cantidad'],
                            'precio'      => $d['precio'],
                        ];
                    } elseif (!empty($d['descripcion'])) {
                        $items[$idx++] = [
                            'descripcion' => $d['descripcion'],
                            'cantidad'    => $d['cantidad'],
                            'precio'      => $d['precio'],
                        ];
                    }
                }

                $np = new NotaPedido();
                $npId = $np->create([
                    'cliente_id'     => (int)$presupuesto['cliente_id'],
                    'presupuesto_id' => (int)$id,
                    'usuario_id'     => $_SESSION['user_id'],
                    'items'          => $items,
                    'observaciones'  => $presupuesto['observaciones'] ?? '',
                    'fecha_entrega'  => $presupuesto['fecha_entrega'] ?? null,
                ]);

                // El presupuesto queda asignado a esta NP: no vuelve a aparecer en
                // el combo de presupuestos del form manual de NP (evita NP duplicadas).
                $this->pr->marcarAsignado((int)$id);

                // Adjuntos viajan si la config esta activa (no bloquean la generacion)
                $copiados = 0;
                if ($config->activa('usar_adjuntos', false)) {
                    $copiados = $adjuntos->copiarTodos('PRESUPUESTO', (int)$id, 'NOTA_PEDIDO', $npId);
                }

                // Campos personalizados del presupuesto viajan a la NP: si la NP tiene
                // un campo propio con el mismo nombre se llena con ese valor, y si no,
                // queda como fila heredada (se ve aunque la NP no tenga el campo configurado).
                $cpHeredados = (new Campopersonalizado())->copiarTodos('PRESUPUESTO', (int)$id, 'NOTA_PEDIDO', $npId);

                // Config Empresa -> Configuraciones: la NP automatica puede nacer aprobada
                $aprobadaAuto = false;
                if ($config->activa('np_automatica_aprobada', false)) {
                    $np->aprobar($npId);
                    $aprobadaAuto = true;
                }

                try {
                    $np->generarYGuardarPdf($npId);
                } catch (Exception $e) {
                    error_log('[NP Automatica] Error generando PDF: ' . $e->getMessage());
                }

                $_SESSION['success'] = 'Presupuesto aprobado. Se generó automáticamente la NP #' . $npId
                    . ($aprobadaAuto ? ' ya aprobada' : ' en borrador')
                    . ' con ' . $copiados . ' adjunto(s) y ' . $cpHeredados . ' campo(s) adicional(es) heredado(s).';
                header('Location: ' . BASE_URL . '/notaspedido/show/' . $npId);
                exit;
            }

            // El presupuesto ya tenia su NP generada: avisar y llevar a esa NP
            $_SESSION['success'] = 'El presupuesto ya tenía la NP #' . $npId . ' generada.';
            header('Location: ' . BASE_URL . '/notaspedido/show/' . $npId);
            exit;
        } catch (Exception $e) {
            error_log('[NP Automatica] Error al generar NP desde presupuesto ' . $id . ': ' . $e->getMessage());
            $_SESSION['error'] = 'El presupuesto quedó aprobado pero no se pudo generar la NP automática: '
                . $e->getMessage();
        }

        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    /**
     * Convierte una linea libre del presupuesto en un producto real de la base.
     */
    public function agregarProducto($id, $detalleId)
    {
        validarId($id, BASE_URL . '/presupuestos');
        validarId($detalleId, BASE_URL . '/presupuestos/show/' . $id);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
            exit;
        }
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
            header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
            exit;
        }

        try {
            $productoId = $this->pr->agregarProductoADetalle((int)$id, (int)$detalleId);
            try {
                $this->pr->generarYGuardarPdf((int)$id);
            } catch (Exception $e) {
                error_log('[Presupuesto] Error regenerando PDF tras agregar producto: ' . $e->getMessage());
            }
            $_SESSION['success'] = 'Producto #' . $productoId . ' agregado a la base. Ya puede editarlo.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
        exit;
    }

    public function porCliente($clienteId)
    {
        validarId($clienteId, BASE_URL . '/presupuestos');
        $model = new Presupuesto();
        echo json_encode($model->getByCliente($clienteId));
    }

    public function showAjax($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        header('Content-Type: application/json');

        $presupuesto = $this->pr->findWithDetalle((int)$id);

        if (!$presupuesto) {
            http_response_code(404);
            echo json_encode(['error' => 'Presupuesto no encontrado']);
            return;
        }

        // Campos personalizados cargados en el presupuesto (los muestra el form de la NP)
        $presupuesto['campos_personalizados'] = (new Campopersonalizado())->valoresVisibles('PRESUPUESTO', (int)$id);

        echo json_encode($presupuesto);
    }

    public function volvernp($presupuestoId)
    {
        validarId($presupuestoId, BASE_URL . '/presupuestos');
        $np = $this->pr->getNotaPedidoByPresupuesto($presupuestoId);
        if (!$np){
            $_SESSION['error'] = 'No existe una Nota de Pedido asociada a este Presupuesto';
            header('Location: ' . BASE_URL. '/presupuestos/show/' . $presupuestoId);
            exit;
        } 
        header('Location: ' . BASE_URL. '/notaspedido/show/' . $np);
        exit;
    }

    public function pdf($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);

        if (!$pr || empty($pr['pdf_path']) || !file_exists($pr['pdf_path'])) {
            $_SESSION['error'] = 'PDF no disponible. Regenerelo desde la vista del presupuesto.';
            header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($pr['pdf_path']) . '"');
        readfile($pr['pdf_path']);
        exit;
    }

    public function regenerarPdf($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        try {
            $this->pr->generarYGuardarPdf((int)$id);
            $_SESSION['success'] = 'PDF regenerado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al regenerar PDF: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
        exit;
    }

    public function reenviar($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);

        if (!$pr || empty($pr['pdf_path']) || !file_exists($pr['pdf_path'])) {
            $_SESSION['error'] = 'Presupuesto inválido o sin PDF. Regenerelo primero.';
            header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
            exit;
        }

        try {
            $mail = new MailService();
            $mail->enviarPresupuesto($pr, (int)$pr['cliente_id'], $_SESSION['user_id']);
            $_SESSION['success'] = 'Presupuesto enviado por email correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al enviar email: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
        exit;
    }
}