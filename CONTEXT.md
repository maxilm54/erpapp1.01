# CONTEXT - ERP Alimentos Triba S.R.L.

## Descripción Rápida

ERP multi-tenant en PHP 8+ (Custom MVC, sin frameworks). Gestión integral para empresa de alimentos: ventas, compras, producción, stock, contabilidad, cobros. Empresa: Alimentos Triba S.R.L., Leones, Córdoba, Argentina.

## Arquitectura

- **Custom MVC** (no Laravel/Symfony)
- **Multi-tenant**: Cada empresa tiene su propia BD. BD master comparte users/tenants.
- **Event-sourcing para stock**: Stock = SUM(movimientos). No se almacena valor estático.
- **Partida doble**: Asientos contables automáticos generados por cobros, pagos, remitos.

### Stack

| Capa | Tecnología |
|------|-----------|
| PHP 8+ | strict_types |
| MySQL 8 | InnoDB, utf8mb4 |
| Frontend | Bootstrap 5.3 (CDN), SweetAlert2, Chart.js |
| PDF | Dompdf 3.1 |
| Email | PHPMailer 7.0 (SMTP) |
| Seguridad | CSRF tokens, thecodingmachine/safe |
| Servidor | Apache (XAMPP desarrollo) |
| Timezone | America/Argentina/Buenos_Aires |

### Dependencias Composer

```json
{
    "require": {
        "phpmailer/phpmailer": "^7.0",
        "dompdf/dompdf": "^3.1",
        "phpoffice/phpspreadsheet": "^5.9"
    }
}
```

## Convenciones de Código

### Enrutamiento

URL: `?url=controller/method/param1/param2`

| URL | Controlador | Método |
|-----|------------|--------|
| `/productos` | ProductosController | `index()` |
| `/productos/create` | ProductosController | `create()` |
| `/productos/edit/5` | ProductosController | `edit(5)` |
| `/admin/migrations-run-all` | AdminController | `migrationsRunAll()` |

- Nombre controlador: `ucfirst(primeraParteURL) + 'Controller'`
- Ejemplo: `materiasprimas` → `MateriasprimasController`

### Naming de controladores y modelos

| Entidad | Controlador | Modelo | Vista Directorio |
|---------|------------|--------|-----------------|
| Productos | ProductosController | Producto | productos/ |
| Clientes | ClientesController | Cliente | clientes/ |
| Cobros | CobrosController | Cobro | cobros/ |
| Ordenes de Compra | OrdenescompraController | Ordencompra | ordenes_compras/ |
| Remitos Salida | RemitossalidaController | Remitosalida | remitos_salida/ |

### Convenciones de métodos

| Método | Propósito | HTTP |
|--------|----------|------|
| `index()` | Listar registros | GET |
| `create()` | Formulario (GET) o procesar (POST) | GET/POST |
| `store()` | Guardar nuevo (si se usa store separado) | POST |
| `show($id)` | Ver detalle | GET |
| `edit($id)` | Editar (GET) o procesar (POST) | GET/POST |
| `update($id)` | Procesar actualización | POST |
| `delete($id)` | Baja lógica (activo=0) | GET/POST |
| `search()` | Búsqueda AJAX (JSON) | GET |

### Dos patrones de Create/Update

**Patrón A - Combine create** (más simple, usado en ABM básico):
```php
public function create(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::validate($_POST['csrf_token']);
        // Procesar...
        header('Location: ' . BASE_URL . '/productos');
        exit;
    }
    $this->view('productos/form', [...]);
}
```

**Patrón B - Store separado** (para módulos complejos):
```php
public function create(): void { $this->view('cobros/create', [...]); }
public function store(): void { /* POST logic */ }
```

### Modelo base (extiende Model)

```php
class ModeloX extends Model {
    public function __construct() {
        parent::__construct(); // $this->db = Database::getInstance()
    }
}
```

Excepción: `User` y `Tenant` usan `Database::getMaster()` en su constructor.

### Métodos comunes de modelo

- `all()` → listar activos
- `find($id)` / `findById($id)` → buscar por ID
- `create(array $data)` → insertar (retorna lastInsertId)
- `update($id, array $data)` → actualizar
- `delete($id)` → baja lógica (`SET activo = 0`)
- `search($q)` → búsqueda por texto
- `getAll($filters)` → listar con filtros

### Formularios

```html
<!-- CSRF obligatorio en POST -->
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

<!-- Edit vs Create: misma vista, action diferente -->
<?php $action = $isEdit ? BASE_URL . "/gastos/update/{$gasto['id']}" : BASE_URL . '/gastos/store'; ?>
<form method="POST" action="<?= $action ?>">
    <input type="text" name="field" value="<?= htmlspecialchars($entity['field'] ?? '') ?>">
    <button type="submit">Guardar</button>
</form>
```

### Mensajes flash (sesión)

```php
$_SESSION['success'] = "Registro creado.";
$_SESSION['error'] = "Ocurrió un error.";
header('Location: ' . BASE_URL . '/module');
exit;
```

Se renderizan en `layout/alerts.php` via SweetAlert2.

### Respuestas AJAX (JSON)

```php
public function search(): void {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); return; }
    $results = $this->model->search($q);
    echo json_encode($results);
}
```

### Validación de IDs

```php
validarId($id, BASE_URL . '/module'); // helper global
```

### Baja lógica (soft delete)

```php
public function delete(int $id): bool {
    return $this->db->prepare("UPDATE tabla SET activo = 0 WHERE id = :id")->execute(['id' => $id]);
}
```

## Mapa de Módulos

### Controladores (31)

| Controlador | Módulo | Descripción |
|------------|--------|------------|
| AuthController | Auth | Login, registro, selección de tenant |
| HomeController | Home | Dashboard principal del tenant |
| AdminController | Admin | Gestión global de tenants, usuarios, migraciones |
| UsersController | Admin | CRUD de usuarios |
| EmpresaController | Empresa | Info y configuración de la empresa (tenant) |
| ClientesController | ABM | ABM de clientes |
| ProveedoresController | ABM | ABM de proveedores |
| ProductosController | ABM | ABM de productos + códigos de barras |
| MateriasprimasController | ABM | ABM de materias primas + códigos de barras |
| CategoriamaterialController | ABM | Categorías de materias primas |
| UnidadmedidaController | ABM | Unidades de medida |
| NumeradoresController | Admin | Numeradores (número de remitos, etc.) |
| PresupuestosController | Ventas | Presupuestos/cotizaciones |
| NotaspedidoController | Ventas | Notas de pedido (ventas) |
| RemitossalidaController | Ventas | Remitos de salida (manuales y desde NP) |
| CobrosController | Finanzas | Cobros a clientes |
| CtacteController | Finanzas | Cuenta corriente de clientes |
| CuentacorrienteempresaController | Finanzas | Cuenta corriente de la empresa |
| OrdenescompraController | Compras | Órdenes de compra |
| IngresosmercaderiaController | Compras | Ingresos de mercadería |
| ComprasController | Compras | Compras (facturación) |
| GastosController | Finanzas | Gestión de gastos |
| CreditosController | Finanzas | Créditos bancarios |
| StockController | Stock | Consulta de stock |
| AjustesstockController | Stock | Ajustes manuales de stock |
| Producción | Producción | Órdenes de producción |
| OrdenproduccionController | Producción | Órdenes de producción |
| RecetasController | Producción | Recetas (BOM) de producción |
| ContabilidadController | Contabilidad | Contabilidad (asientos, plan de cuentas, etc.) |
| ImpuestosController | Impuestos | Impuestos (IVA) |
| SdcompController | SDCOMP | Comprobantes internos (movimientos sin comprobante fiscal) |
| EmailController | Email | Envío de emails |

### Modelos (36)

| Modelo | Tabla | Conexión |
|--------|-------|----------|
| User | users | Master DB |
| Tenant | tenants | Master DB |
| Cliente | clientes | Tenant |
| Proveedor | proveedores | Tenant |
| Producto | productos | Tenant |
| Productocodigo | producto_codigos | Tenant |
| Productocostos | productocostos | Tenant |
| Materiaprima | materias_primas | Tenant |
| Receta | recetas | Tenant |
| Ordenproduccion | ordenes_produccion | Tenant |
| Reservamateriaprima | reservas_materia_prima | Tenant |
| Presupuesto | presupuestos | Tenant |
| Notapedido | notas_pedido | Tenant |
| Remitosalida | remitos_salida | Tenant |
| Ordencompra | ordenes_compra | Tenant |
| Ingresomercaderia | ingresos_mercaderia | Tenant |
| Stock | movimientos_stock | Tenant |
| Ajustestock | ajustes_stock | Tenant |
| Cobro | cobros | Tenant |
| Cuentacorrientecliente | cuentas_corriente_clientes | Tenant |
| Cuentacorrienteempresa | cuentas_corrientes_empresa | Tenant |
| Pago | pagos | Tenant |
| Gasto | gastos | Tenant |
| AsientoContable | asientos_contables | Tenant |
| CuentaContable | cuentas_contables | Tenant |
| CajaBanco | cajas_bancos | Tenant |
| ConciliacionBancaria | conciliaciones_bancarias | Tenant |
| Impuesto | impuestos | Tenant |
| Sdcomp | movimientos_no_declarados | Tenant |
| CreditoBancario | creditos_bancarios | Tenant |
| Numerador | numeradores | Tenant |
| Categoriamaterial | categorias_mp | Tenant |
| Unidadmedida | unidad_medida | Tenant |
| EmailTemplate | email_templates | Tenant |
| EmailConfig | email_config | Tenant |
| Maillog | mails_log | Tenant |

## Flujo de Datos Clave

### Ventas: Presupuesto → NP → Remito → Cobro

```
Presupuesto → Nota de Pedido → Remito de Salida → PDF → Email
    ↓              ↓                  ↓             ↓
 (Opcional)    Aprobación         Impacto Stock   Cta. Corriente
                                    Asiento Contable
```

- Presupuestos: BORRADOR → APROBADO
- Notas de Pedido: BORRADOR → APROBADA → SinRemitir/Parcial/Completo → ANULADA
- Remitos: Generados desde NP o manualmente. Generan PDF, envían email, impactan stock, ctacte, asiento contable.

### Compras: OC → Ingreso → Stock

```
Orden de Compra → Ingreso de Mercadería → Stock
      ↓                    ↓                ↓
  Aprobación         Recepción Física    ENTRADA
  Cta. Cte. Empresa  Verificación        Asiento
```

- OC: PENDIENTE → APROBADA → RECIBIDA/PARCIAL → ANULADA
- Ingresos de mercadería: Recepción física contra OC. Impacto automático de stock.

### Producción: Receta → Orden → Avances → Confirmación

```
Receta (BOM) → Orden de Producción → Avances → Confirmación
     ↓               ↓                  ↓            ↓
  Materias       Reserva MP         Producción    Stock
  Primas         (disponible)       Parcial       Final
```

- Órdenes: PENDIENTE → EN_PRODUCCION → FINALIZADA/CANCELADA
- Reserva MP automáticamente. Avances parciales permitidos.

### Stock (Event-sourcing)

```sql
Stock = SUM(CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                 WHEN tipo = 'SALIDA' THEN -cantidad END)
```

Orígenes: REMITO_SALIDA, NOTA_PEDIDO, ORDEN_COMPRA, INGRESO_MERCADERIA, AJUSTE, PRODUCCION, etc.

### Contabilidad (Partida Doble)

- Plan de Cuentas: Árbol jerárquico (ACTIVO, PASIVO, PATRIMONIO, INGRESO, EGRESO). 5 niveles.
- Asientos automáticos generados por: cobros, pagos, remitos, órdenes de compra.
- Estados contables: ACTIVO = PASIVO + PATRIMONIO
- Estado de Resultados: INGRESOS - EGRESOS

## Puntos Críticos

1. **Cliente ocasional (id=9999)**: Permite ventas sin cliente registrado en ABM. Verificar este ID en lógica de ventas.

2. **Stock por event-sourcing**: Nunca hardcodear stock. Siempre consultar `movimientos_stock` o usar `StockService`.

3. **Asientos automáticos**: Al crear cobros, pagos, remitos → se genera asiento contable automático. Usar `AsientoAutomatico.php`.

4. **SDCOMP**: Módulo de comprobantes internos con nomenclatura genérica. Solo accesible por ADMIN y GERENTE_FINANCIERO.

5. **Multi-tenant**: Conexión DB se establece en Router antes de instanciar controlador. Los modelos usan `Database::getInstance()` (tenant). Solo `User` y `Tenant` usan `Database::getMaster()`.

6. **Sin autoloader manual**: Todos los archivos se cargan con `require_once`. No hay PSR-4.

7. **Validación CSRF**: Obligatoria en todo POST. `Csrf::generate()` y `Csrf::validate()`.

8. **Rutas admin**: Solo SUPERADMIN. `AdminController` y `UsersController` requieren `requireAdminPanel()`.

9. **Tenant selection**: Login → si 1 tenant → auto-connect; si múltiples → select-tenant; si 0 → error.

10. **File uploads**: Organizados por tenant: `public/uploads/{tipo}/empresa_{id}/`.

## Cómo Agregar un Módulo Nuevo

### 1. Modelo (`app/models/NuevoModelo.php`)

```php
<?php
require_once BASE_PATH . '/app/core/Model.php';

class NuevoModelo extends Model {
    public function all(): array {
        return $this->db->query(
            "SELECT * FROM nuevo_modulo WHERE activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM nuevo_modulo WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO nuevo_modulo (...) VALUES (...)");
        $stmt->execute([...]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        return $this->db->prepare(
            "UPDATE nuevo_modulo SET ... WHERE id = :id"
        )->execute(array_merge($data, ['id' => $id]));
    }

    public function delete(int $id): bool {
        return $this->db->prepare(
            "UPDATE nuevo_modulo SET activo = 0 WHERE id = :id"
        )->execute(['id' => $id]);
    }
}
```

### 2. Controlador (`app/controllers/NuevoController.php`)

```php
<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/NuevoModelo.php';

class NuevoController extends Controller {
    private NuevoModelo $modelo;

    public function __construct() {
        $this->modelo = new NuevoModelo();
    }

    public function index(): void {
        $this->view('nuevo/index', [
            'title' => 'Nuevo Módulo',
            'registros' => $this->modelo->all()
        ]);
    }

    public function create(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido.';
                header('Location: ' . BASE_URL . '/nuevo/create');
                exit;
            }
            $this->modelo->create([...]);
            $_SESSION['success'] = 'Creado correctamente.';
            header('Location: ' . BASE_URL . '/nuevo');
            exit;
        }
        $this->view('nuevo/form', [
            'title' => 'Nuevo Registro',
            'registro' => null
        ]);
    }

    public function edit(int $id): void {
        validarId($id, BASE_URL . '/nuevo');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // ... update logic
        }
        $this->view('nuevo/form', [
            'title' => 'Editar Registro',
            'registro' => $this->modelo->find($id)
        ]);
    }

    public function delete(int $id): void {
        validarId($id, BASE_URL . '/nuevo');
        $this->modelo->delete($id);
        $_SESSION['success'] = 'Inactivado.';
        header('Location: ' . BASE_URL . '/nuevo');
        exit;
    }
}
```

### 3. Vistas (`app/views/nuevo/`)

```
nuevo/
├── index.php      # Lista
├── form.php       # Crear/Editar
└── show.php       # Detalle (opcional)
```

### 4. Navegación

Editar `app/views/layout/header.php` para agregar enlace al menú.

### 5. Migración (si se necesita)

Crear archivo SQL en `app/helpers/squemadb/` y ejecutar desde Admin → Migraciones.

## Errores Comunes

| Problema | Solución |
|----------|---------|
| "El controlador no existe" | Verificar que el nombre del archivo es `{Nombre}Controller.php` con primera letra mayúscula |
| "El menu no funciona" | Verificar que el método existe en el controlador |
| "Token CSRF inválido" | Agregar `<input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">` en el form |
| "No hay conexión al tenant" | Verificar `Auth::setTenant()` o que el Router está conectando el tenant |
| "500 error" | Verificar `app/logs/` o `public/error_log` |
| "Tabla no existe" | Verificar migración aplicada en Admin → Migraciones |
| Soft delete no funciona | Verificar que `activo = 0` y no `activo = 1` en queries de listado |
| Stock negativo | Verificar que `movimientos_stock` tiene los tipos correctos (ENTRADA, SALIDA, AJUSTE) |

## Archivos Clave (Referencia Rápida)

| Archivo | Función |
|---------|---------|
| `public/index.php` | Front controller |
| `app/bootstrap.php` | Session, constantes, autoloading |
| `app/core/Router.php` | URL → Controlador::método |
| `app/core/Controller.php` | Controlador base (view, adminView, modal) |
| `app/core/Model.php` | Modelo base (PDO $this->db) |
| `app/core/Database.php` | Conexiones PDO (master + tenant) |
| `app/core/Auth.php` | Autenticación, sesión, tenant |
| `app/core/Role.php` | Constantes de roles y permisos |
| `app/core/Csrf.php` | Tokens CSRF |
| `app/core/Middleware.php` | Middleware de autenticación |
| `app/config/config.php` | Config general (timezone, empresa, SMTP) |
| `app/config/database.php` | Config de BD |
| `app/config/env.php` | Parser de .env |
| `app/helpers/validationHelper.php` | Validación de inputs |
| `app/helpers/AsientoAutomatico.php` | Asientos contables automáticos |
| `app/helpers/StockHelper.php` | Estados de stock |
| `app/helpers/MailHelper.php` | Wrapper PHPMailer |
| `app/helpers/MigrationManager.php` | Gestor de migraciones SQL |
| `app/services/StockService.php` | Servicio de stock |
| `app/services/MailService.php` | Servicio de emails |
| `app/services/PdfService.php` | Generación de PDFs |
| `app/views/layout/header.php` | Layout tenant (navbar) |
| `app/views/layout/footer.php` | Footer tenant |
| `app/views/layout/alerts.php` | Flash messages (SweetAlert2) |
| `app/views/layout/admin_header.php` | Layout admin (sidebar) |
| `app/views/layout/admin_footer.php` | Footer admin |
| `public/js/confirmations.js` | SweetAlert2 confirmaciones |
| `public/assets/css/app.css` | Estilos globales |
| `.env` | Variables de entorno |
| `tareas.json` | Tareas pendientes/realizadas |