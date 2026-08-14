<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/EmailConfig.php';
require_once BASE_PATH . '/app/models/EmailTemplate.php';
require_once BASE_PATH . '/app/models/Maillog.php';

class EmailController extends Controller
{
    private function checkAccess(): void
    {
        Auth::requireLogin();
        Auth::requireTenant();
        if (!Auth::isEmpresaAdmin()) {
            $_SESSION['error'] = 'No tienes permisos para gestionar la configuración de email.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    // =====================================================
    // CONFIGURACIÓN SMTP
    // =====================================================

    /**
     * Mostrar configuración SMTP actual.
     */
    public function config(): void
    {
        $this->checkAccess();

        $emailConfigModel = new EmailConfig();
        $config = $emailConfigModel->getActive();
        $resolved = $emailConfigModel->getResolved();

        $this->view('email/config', [
            'config'   => $config,
            'resolved' => $resolved,
        ]);
    }

    /**
     * Guardar configuración SMTP.
     */
    public function saveConfig(): void
    {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/email/config');
            exit;
        }

        try {
            $data = [
                'smtp_host'       => trim($_POST['smtp_host'] ?? ''),
                'smtp_port'       => (int)($_POST['smtp_port'] ?? 465),
                'smtp_secure'     => $_POST['smtp_secure'] ?? 'ssl',
                'smtp_user'       => trim($_POST['smtp_user'] ?? ''),
                'smtp_pass'       => $_POST['smtp_pass'] ?? '',
                'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
                'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? ''),
                'bcc_email'       => trim($_POST['bcc_email'] ?? ''),
            ];

            if (empty($data['smtp_host']) || empty($data['smtp_user']) || empty($data['smtp_from_email'])) {
                throw new Exception('Los campos Host, Usuario y Email del remitente son obligatorios.');
            }

            $emailConfigModel = new EmailConfig();
            $emailConfigModel->save($data);

            $_SESSION['success'] = 'Configuración SMTP guardada correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/email/config');
        exit;
    }

    /**
     * Probar conexión SMTP.
     */
    public function testSmtp(): void
    {
        $this->checkAccess();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            return;
        }

        $data = [
            'smtp_host'       => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'       => (int)($_POST['smtp_port'] ?? 465),
            'smtp_secure'     => $_POST['smtp_secure'] ?? 'ssl',
            'smtp_user'       => trim($_POST['smtp_user'] ?? ''),
            'smtp_pass'       => $_POST['smtp_pass'] ?? '',
            'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
            'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? ''),
        ];

        $result = EmailConfig::testConnection($data);
        echo json_encode($result);
    }

    // =====================================================
    // TEMPLATES DE EMAIL
    // =====================================================

    /**
     * Listar todos los templates.
     */
    public function templates(): void
    {
        $this->checkAccess();

        $templateModel = new EmailTemplate();
        $templates = $templateModel->all();
        $tipos = EmailTemplate::getTipos();

        $this->view('email/templates', [
            'templates' => $templates,
            'tipos'     => $tipos,
        ]);
    }

    /**
     * Formulario para crear/editar template.
     */
    public function templateForm(): void
    {
        $this->checkAccess();

        $id = $_GET['id'] ?? null;
        $template = null;
        $tipos = EmailTemplate::getTipos();

        if ($id) {
            $templateModel = new EmailTemplate();
            $template = $templateModel->find((int)$id);
            if (!$template) {
                $_SESSION['error'] = 'Template no encontrado.';
                header('Location: ' . BASE_URL . '/email/templates');
                exit;
            }
        }

        $this->view('email/template_form', [
            'template' => $template,
            'tipos'    => $tipos,
        ]);
    }

    /**
     * Guardar template (crear o actualizar).
     */
    public function saveTemplate(): void
    {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/email/templates');
            exit;
        }

        try {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $data = [
                'tipo'       => $_POST['tipo'] ?? 'GENERIC',
                'asunto'     => trim($_POST['asunto'] ?? ''),
                'cuerpo_html'=> $_POST['cuerpo_html'] ?? '',
                'activo'     => 1,
            ];

            if (empty($data['asunto']) || empty($data['cuerpo_html'])) {
                throw new Exception('Los campos Asunto y Cuerpo son obligatorios.');
            }

            $templateModel = new EmailTemplate();

            if ($id) {
                $templateModel->update($id, $data);
                $_SESSION['success'] = 'Template actualizado correctamente.';
            } else {
                $templateModel->create($data);
                $_SESSION['success'] = 'Template creado correctamente.';
            }

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/email/templates');
        exit;
    }

    /**
     * Clonar template default para personalizar.
     */
    public function cloneTemplate(): void
    {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/email/templates');
            exit;
        }

        try {
            $defaultId = (int)($_POST['template_id'] ?? 0);
            if (!$defaultId) {
                throw new Exception('Template no especificado.');
            }

            $templateModel = new EmailTemplate();
            $newId = $templateModel->cloneDefault($defaultId);

            $_SESSION['success'] = 'Template clonado. Ahora puede personalizarlo.';
            header('Location: ' . BASE_URL . '/email/template-form?id=' . $newId);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/email/templates');
            exit;
        }
    }

    /**
     * Restaurar template a versión default.
     */
    public function resetTemplate(): void
    {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/email/templates');
            exit;
        }

        try {
            $tipo = $_POST['tipo'] ?? '';
            if (!$tipo) {
                throw new Exception('Tipo de template no especificado.');
            }

            $templateModel = new EmailTemplate();
            $templateModel->resetToDefault($tipo);

            $_SESSION['success'] = 'Template restaurado a la versión por defecto.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/email/templates');
        exit;
    }

    /**
     * Eliminar template (solo los no-default).
     */
    public function deleteTemplate(): void
    {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/email/templates');
            exit;
        }

        try {
            $id = (int)($_POST['template_id'] ?? 0);
            if (!$id) {
                throw new Exception('Template no especificado.');
            }

            $templateModel = new EmailTemplate();
            $templateModel->delete($id);

            $_SESSION['success'] = 'Template eliminado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/email/templates');
        exit;
    }

    // =====================================================
    // HISTORIAL DE ENVÍOS
    // =====================================================

    /**
     * Listar historial de emails enviados.
     */
    public function historial(): void
    {
        $this->checkAccess();

        $logModel = new Maillog();

        // Filtros
        $filtros = [
            'tipo'      => $_GET['tipo'] ?? null,
            'estado'    => $_GET['estado'] ?? null,
            'buscar'    => $_GET['buscar'] ?? null,
            'fecha_desde' => $_GET['fecha_desde'] ?? null,
            'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
        ];

        $emails = $logModel->all($filtros);
        $tipos = EmailTemplate::getTipos();

        $this->view('email/historial', [
            'emails'  => $emails,
            'filtros' => $filtros,
            'tipos'   => $tipos,
        ]);
    }
}
