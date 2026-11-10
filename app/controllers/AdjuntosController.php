<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Adjunto.php';
require_once BASE_PATH . '/app/models/Configuracion.php';

/**
 * Subida, descarga y baja de adjuntos de documentos (PR, NP, Remito, Produccion).
 * Rutas: /adjuntos/subir/{modulo}/{documentoId}, /adjuntos/descargar/{id},
 * /adjuntos/eliminar/{id}. La descarga pasa por PHP: storage nunca es publico.
 */
class AdjuntosController extends Controller
{
    private Adjunto $adjuntos;

    public function __construct()
    {
        $this->adjuntos = new Adjunto();
    }

    /**
     * Ruta de retorno segun el modulo (para volver al documento).
     */
    private function urlDocumento(string $modulo, int $documentoId): string
    {
        return match (strtoupper($modulo)) {
            'PRESUPUESTO'   => BASE_URL . '/presupuestos/show/' . $documentoId,
            'NOTA_PEDIDO'   => BASE_URL . '/notaspedido/show/' . $documentoId,
            'REMITO_SALIDA' => BASE_URL . '/remitossalida/show/' . $documentoId,
            'PRODUCCION'    => BASE_URL . '/ordenproduccion/show/' . $documentoId,
            'ORDEN_COMPRA'  => BASE_URL . '/ordenescompra/show/' . $documentoId,
            default         => BASE_URL . '/home',
        };
    }

    /**
     * Sube uno o varios archivos para un documento.
     */
    public function subir(string $modulo, int $documentoId): void
    {
        $modulo = strtoupper($modulo);
        $urlDoc = $this->urlDocumento($modulo, $documentoId);

        Auth::requireLogin();
        Auth::requireTenant();
        validarId($documentoId, $urlDoc);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $urlDoc);
            exit;
        }
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
            header('Location: ' . $urlDoc);
            exit;
        }
        if (!(new Configuracion())->activa('usar_adjuntos', false)) {
            $_SESSION['error'] = 'La opcion de adjuntos esta desactivada en Configuraciones.';
            header('Location: ' . $urlDoc);
            exit;
        }
        if (!Adjunto::moduloValido($modulo)) {
            $_SESSION['error'] = 'Modulo de adjunto invalido.';
            header('Location: ' . $urlDoc);
            exit;
        }

        try {
            $guardados = $this->adjuntos->guardarMultiples($modulo, $documentoId, $_FILES['archivos'] ?? []);
            $_SESSION['success'] = $guardados > 0
                ? 'Se ' . ($guardados > 1 ? 'adjuntaron' : 'adjuntó') . ' ' . $guardados . ' archivo(s).'
                : 'No se selecciono ningun archivo.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al adjuntar: ' . $e->getMessage();
        }

        header('Location: ' . $urlDoc);
        exit;
    }

    /**
     * Descarga/visualiza un adjunto (por PHP, nunca link directo a storage).
     */
    public function descargar(int $id): void
    {
        Auth::requireLogin();
        Auth::requireTenant();
        validarId($id, BASE_URL . '/home');

        $adj = $this->adjuntos->find($id);
        $ruta = $adj ? $this->adjuntos->rutaFisica($adj) : '';

        if (!$adj || !file_exists($ruta)) {
            $_SESSION['error'] = 'El archivo no existe o fue eliminado.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $ext = strtolower(pathinfo($adj['nombre_original'], PATHINFO_EXTENSION));
        $inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'], true);
        $mime = $adj['mime'] ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . rawurlencode($adj['nombre_original']) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($ruta);
        exit;
    }

    /**
     * Elimina un adjunto (archivo + fila).
     */
    public function eliminar(int $id): void
    {
        Auth::requireLogin();
        Auth::requireTenant();
        validarId($id, BASE_URL . '/home');

        $adj = $this->adjuntos->find($id);
        $urlDoc = $adj ? $this->urlDocumento($adj['modulo'], (int)$adj['documento_id']) : BASE_URL . '/home';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $urlDoc);
            exit;
        }
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
            header('Location: ' . $urlDoc);
            exit;
        }

        if ($this->adjuntos->eliminar($id)) {
            $_SESSION['success'] = 'Adjunto eliminado.';
        } else {
            $_SESSION['error'] = 'No se pudo eliminar el adjunto.';
        }

        header('Location: ' . $urlDoc);
        exit;
    }
}
