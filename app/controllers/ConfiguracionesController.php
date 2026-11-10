<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Configuracion.php';

/**
 * Menu de Configuraciones del tenant (Empresa -> Configuraciones).
 * Claves/valores por empresa; el nomenclador por cliente es la primera
 * configuracion, luego se suman las demas (emails, facturacion, etc).
 */
class ConfiguracionesController extends Controller
{
    private function requireEmpresaAdmin(): void
    {
        Auth::requireLogin();
        Auth::requireTenant();
        if (!Auth::isEmpresaAdmin()) {
            $_SESSION['error'] = 'No tenes permiso para administrar la configuracion.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    public function index(): void
    {
        $this->requireEmpresaAdmin();

        $config = new Configuracion();
        $this->view('configuraciones/index', [
            'title'     => 'Configuraciones',
            'config'    => $config->all(),
            'nomenclador' => $config->activa('usar_nomenclador', true),
            'usarAdjuntos' => $config->activa('usar_adjuntos', false),
            'usarAdjuntosCompra' => $config->activa('usar_adjuntos_compra', false),
        ]);
    }

    /**
     * Guarda las configuraciones del form (solo las claves conocidas).
     */
    public function guardar(): void
    {
        $this->requireEmpresaAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/configuraciones');
                exit;
            }

            $config = new Configuracion();

            // Cada form del panel envia un marker: solo actualizamos esas claves,
            // asi un switch no pisa el valor del otro.
            switch ($_POST['guardar'] ?? '') {
                case 'usar_nomenclador':
                    $config->set('usar_nomenclador', !empty($_POST['usar_nomenclador']) ? '1' : '0');
                    break;
                case 'usar_adjuntos':
                    $config->set('usar_adjuntos', !empty($_POST['usar_adjuntos']) ? '1' : '0');
                    break;
                case 'usar_adjuntos_compra':
                    $config->set('usar_adjuntos_compra', !empty($_POST['usar_adjuntos_compra']) ? '1' : '0');
                    break;
            }

            $_SESSION['success'] = 'Configuraciones guardadas correctamente.';
            header('Location: ' . BASE_URL . '/configuraciones');
            exit;
        }

        header('Location: ' . BASE_URL . '/configuraciones');
        exit;
    }
}
