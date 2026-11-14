<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Configuracion.php';
require_once BASE_PATH . '/app/models/Campopersonalizado.php';

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
        $camposModel = new Campopersonalizado();

        $camposPorModulo = [];
        foreach (Campopersonalizado::MODULOS as $modulo) {
            $camposPorModulo[$modulo] = $camposModel->porModulo($modulo);
        }

        $this->view('configuraciones/index', [
            'title'     => 'Configuraciones',
            'config'    => $config->all(),
            'nomenclador' => $config->activa('usar_nomenclador', true),
            'usarAdjuntos' => $config->activa('usar_adjuntos', false),
            'usarAdjuntosCompra' => $config->activa('usar_adjuntos_compra', false),
            'npAprobadaAuto' => $config->activa('np_automatica_aprobada', false),
            'usarProductosRecetas' => $config->activa('usar_productos_recetas', false),
            'camposPorModulo' => $camposPorModulo,
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
                case 'np_automatica_aprobada':
                    $config->set('np_automatica_aprobada', !empty($_POST['np_automatica_aprobada']) ? '1' : '0');
                    break;
                case 'usar_productos_recetas':
                    $config->set('usar_productos_recetas', !empty($_POST['usar_productos_recetas']) ? '1' : '0');
                    break;
            }

            $_SESSION['success'] = 'Configuraciones guardadas correctamente.';
            header('Location: ' . BASE_URL . '/configuraciones');
            exit;
        }

        header('Location: ' . BASE_URL . '/configuraciones');
        exit;
    }

    // =====================================================
    // Campos personalizados por modulo
    // =====================================================

    /**
     * Alta de un campo personalizado (modulo + nombre + tipo).
     */
    public function campoAgregar(): void
    {
        $this->requireEmpresaAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/configuraciones');
                exit;
            }

            try {
                $nombre = (string)($_POST['cp_nombre'] ?? '');
                (new Campopersonalizado())->crear(
                    (string)($_POST['cp_modulo'] ?? ''),
                    $nombre,
                    (string)($_POST['cp_tipo'] ?? 'texto')
                );
                $_SESSION['success'] = "Campo '{$nombre}' agregado. Ya aparece en el formulario del módulo.";
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }

        header('Location: ' . BASE_URL . '/configuraciones');
        exit;
    }

    /**
     * Renombra / cambia el tipo de un campo personalizado.
     */
    public function campoEditar($id): void
    {
        $this->requireEmpresaAdmin();
        validarId($id, BASE_URL . '/configuraciones');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/configuraciones');
                exit;
            }

            try {
                $nombre = (string)($_POST['cp_nombre'] ?? '');
                $tipo   = (string)($_POST['cp_tipo'] ?? 'texto');
                $campos = new Campopersonalizado();

                // Unicidad de nombre dentro del modulo (sin contar este campo)
                $campo = $campos->find((int)$id);
                if ($campo) {
                    foreach ($campos->porModulo($campo['modulo']) as $otro) {
                        if ((int)$otro['id'] !== (int)$id && mb_strtolower(trim($otro['nombre'])) === mb_strtolower(trim($nombre))) {
                            throw new Exception("Ya existe otro campo '{$nombre}' en ese módulo.");
                        }
                    }
                }

                $campos->actualizar((int)$id, $nombre, $tipo);
                $_SESSION['success'] = "Campo actualizado a '{$nombre}'. Los valores ya guardados no se pierden.";
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }

        header('Location: ' . BASE_URL . '/configuraciones');
        exit;
    }

    /**
     * Baja logica del campo: deja de aparecer en los formularios. Los valores ya
     * guardados siguen visibles en los documentos (snapshot del nombre).
     */
    public function campoEliminar($id): void
    {
        $this->requireEmpresaAdmin();
        validarId($id, BASE_URL . '/configuraciones');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/configuraciones');
                exit;
            }

            (new Campopersonalizado())->eliminar((int)$id);
            $_SESSION['success'] = 'Campo eliminado de los formularios. Los valores ya guardados siguen visibles en los documentos.';
        }

        header('Location: ' . BASE_URL . '/configuraciones');
        exit;
    }
}
