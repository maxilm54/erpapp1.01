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
| MariaDB 10.4 (compatible MySQL 8) | InnoDB, utf8mb4 |
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
| Cheques | ChequesController | Cheque | cheques/ |
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

### Controladores (32)

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
| DevolucionesController | Ventas | Devoluciones de remitos (stock, ctacte, caja, asientos, PDF, email) |
| CobrosController | Finanzas | Cobros a clientes |
| CtacteController | Finanzas | Cuenta corriente de clientes |
| CuentacorrienteempresaController | Finanzas | Cuenta corriente de la empresa (ingresos/gastos + deuda por proveedor) |
| PerfilController | Perfil | "Mi Perfil": ver/editar nombre, email y contraseña del usuario logueado |
| OrdenescompraController | Compras | Órdenes de compra (aprobación con registro: `aprobada_at` + `aprobado_por`) |
| IngresosmercaderiaController | Compras | Ingresos de mercadería |
| FacturascompraController | Compras | Facturas de compra de proveedores (migración 026) |
| OrdenespagoController | Compras | Órdenes de pago de facturas (migración 027, numerador ORDEN_PAGO) |
| GastosController | Finanzas | Gestión de gastos |
| CreditosController | Finanzas | Créditos bancarios |
| StockController | Stock | Consulta de stock |
| AjustesstockController | Stock | Ajustes manuales de stock |
| DepositosController | Stock | CRUD de depósitos (gestión de ubicaciones físicas) |
| TransferenciasController | Stock | Transferencias de stock entre depósitos (salida→tránsito→recepción) |
| LotesController | Stock | Listado central de lotes vencidos y próximos a vencer |
| Producción | Producción | Órdenes de producción |
| OrdenproduccionController | Producción | Órdenes de producción |
| RecetasController | Producción | Recetas (BOM) de producción |
| ContabilidadController | Contabilidad | Contabilidad (asientos, plan de cuentas, etc.) |
| ImpuestosController | Impuestos | Impuestos (IVA) |
| SdcompController | SDCOMP | Comprobantes internos (movimientos sin comprobante fiscal) |
| EmailController | Email | Envío de emails |

### Modelos (41)

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
| Devolucion | devoluciones | Tenant |
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
| **Deposito** | **depositos** | **Tenant** |
| **Lote** | **lotes** | **Tenant** |
| **Transferencia** | **transferencias_depositos** | **Tenant** |
| **Serie** | **series** | **Tenant** |
| **Materiaprimaespecificacion** | **materias_primas_especificaciones** | **Tenant** |

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

### Devoluciones: Remito → Devolución → Reembolso

```
Remito de Salida → Devolución → Stock + CtaCte + Caja + Asiento → PDF → Email
                       ↓
              Reembolso: NC (CtaCte) / Efectivo / Transferencia
```

- El remito referenciado es obligatorio. Se valida `pendiente_devolver` por producto (ya devuelto en devoluciones anteriores se descuenta).
- Condiciones de producto: NUEVO, BUEN_ESTADO, ESTADO_REGULAR, DANADO, INSERVIBLE.
- `reingresa` = cantidad total devuelta (siempre igual a `cantidad_total`). `descarta` ≤ `reingresa` (subconjunto que se descarta).
- **Nota de Crédito siempre cubre el total de la devolución**. Efectivo/Transferencia solo por montos ya pagados (exceso sobre deuda).
- La deuda del cliente se muestra en el formulario para decidir el tipo de reembolso.
- **Validación server-side estricta**: cantidad vs `pendiente_devolver`, precio forzado desde el remito (no se confía en el POST), condición en lista blanca, saldo de caja/banco ≥ monto del reembolso, con `FOR UPDATE` dentro de la transacción.
- **Distribución FIFO de devoluciones en `ventasNoCobradas()`**: si una devolución excede el saldo de un remito, el exceso reduce el saldo de otros remitos del mismo cliente (más antiguo primero).
- Estados: PENDIENTE → PROCESADA / ANULADA.
- Orígenes de stock: DEVOLUCION (ENTRADA por reingreso), DEVOLUCION_DESCARTE (SALIDA por descarte).

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

Orígenes: REMITO_SALIDA, NOTA_PEDIDO, ORDEN_COMPRA, INGRESO_MERCADERIA, AJUSTE, PRODUCCION, DEVOLUCION, TRANSFERENCIA, etc.

Desde migración 016, cada movimiento puede asociarse a un **depósito** (`deposito_id`) y a un **lote** (`lote_id`) con vencimiento, permitiendo stock desglosado por ubicación física y trazabilidad por lote.

### Transferencias entre Depósitos

Flujo en dos fases:
1. **Confirmar Salida**: stock sale del depósito origen, entra al depósito virtual "En Tránsito" → estado `EN_TRANSITO`.
2. **Confirmar Recepción**: stock sale de "En Tránsito", entra al depósito destino → estado `COMPLETADA`.

La anulación revierte según el estado: si está en tránsito devuelve al origen; si está completada retira del destino y devuelve al origen.

### Contabilidad (Partida Doble)

- Plan de Cuentas: Árbol jerárquico (ACTIVO, PASIVO, PATRIMONIO, INGRESO, EGRESO). 5 niveles.
- Asientos automáticos generados por: cobros, pagos, remitos, órdenes de compra.
- Estados contables: ACTIVO = PASIVO + PATRIMONIO
- Estado de Resultados: INGRESOS - EGRESOS

### Cuenta Corriente de la Empresa

- Movimientos de la empresa: `INGRESO` (entra dinero) / `GASTO` (sale dinero). Saldo = ingresos - gastos.
- Con `proveedor_id` cargado, el saldo por proveedor es la deuda que la empresa tiene con él.
- Categorías en `categorias_gastos_ingresos` (PROVEEDORES, SUELDOS, SERVICIOS, ALQUILER, IMPUESTOS, OTROS / VENTAS, COBRO CLIENTES, OTROS INGRESOS).
- Anulación por baja lógica (`activo = 0`).
- Ruta: `/cuentacorrienteempresa` + `/create`, `/edit/{id}`, `/delete/{id}`.

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

12. **Depósitos y Lotes**: `movimientos_stock` ahora tiene `deposito_id` y `lote_id`. La tabla `depositos` almacena ubicaciones físicas (`es_transito=1` para depósitos virtuales). La tabla `lotes` vincula códigos de lote y vencimiento a productos o materias primas.

13. **Transferencias en dos fases**: La mercadería sale del origen al depósito virtual "En Tránsito" al crear la transferencia, y se acredita al destino al confirmar la recepción. La anulación es inteligente según el estado.

14. **Control de N° de Serie por unidad**: `productos.usa_serie` y `materias_primas.usa_serie` (flag booleano, migración 020). Cuando está activo, el producto/MP se **recepciona y remite UNIDAD A UNIDAD**: la tabla `series` registra cada unidad individual con su `numero_serie` único (UNIQUE), estado (`DISPONIBLE` → `REMITIDO` → `EN_DEVOLUCION`/`BAJA`), depósito, lote e ingreso/remito asociados. En el ingreso de mercadería, los items con serie se expanden en N filas (una por unidad, cada una con su N° de serie obligatorio y validación de unicidad). En el remito (desde NP y manual), en vez de cantidad se muestra un selector múltiple de series DISPONIBLES en el depósito (cargado vía AJAX a `remitossalida/seriesDisponibles`); al confirmar, cada serie seleccionada se marca `REMITIDO` con el `remito_salida_id`. El detalle de producto/MP (`show`) muestra la tabla de unidades con serie y su estado.

15. **Cta. Cte. Empresa** (`/cuentacorrienteempresa`, migración 021): libro de ingresos (`INGRESO`) y gastos (`GASTO`) de la empresa. Con `proveedor_id` cargado, el saldo es la deuda con ese proveedor. Anulación por baja lógica (`activo = 0`), no se borra físicamente.

16. **"Mi Perfil"** (`/perfil`): el usuario logueado edita su nombre, email y contraseña. El `User` model escribe en la BD **master**; hay que llamar `syncToTenant()` para propagar el cambio a cada empresa del usuario.

17. **Formularios de filtro con `method="get"`**: el router lee la ruta de `?url=`. Un form GET **sin `action`** borra el query string y manda al home. Todos los forms de filtro deben llevar `action="<?= BASE_URL ?>/<ruta>"`.

18. **Registro de tareas**: vive en la tabla `tareas` de la **BD master** (no en los tenants) y se administra desde el panel admin (`/admin/tareas`, solo superadmin). Reemplaza al viejo `tareas.json` de la raíz, que se eliminó. Acciones: crear, confirmar (HECHA), reabrir y eliminar. `MasterMigrationManager` mantiene el schema master al día (archivos en `app/helpers/squemadb/master_migrations/`), que es independiente del `MigrationManager` de tenants.

19. **Productos libres (migración 022)**: Presupuestos y Notas de Pedido aceptan líneas que no existen en la base. En el detalle, `producto_id` queda `NULL` y el nombre se guarda en `descripcion`. Reglas:
   - En toda consulta al detalle usar `LEFT JOIN productos` + `COALESCE(p.nombre, d.descripcion)` (nunca `JOIN` interno).
   - Al guardar, el checkbox "Agregar a Productos" manda `crear_producto=1` y el modelo crea el producto real con `Producto::crearDesdeLinea()` (SKU autogenerado `LIBRE-XXXXXXXX`, tipo `PRESENTACION`).
   - En la vista del documento, la línea libre muestra el botón "Agregar a Productos" (`/presupuestos/agregar-producto/{id}/{detalleId}` o `/notaspedido/agregar-producto/{id}/{detalleId}`); luego se edita desde Productos.
   - En el remito desde NP las líneas libres viajan en `items_manual[detalle_id]`, **no descuentan stock** y sí generan débito en cuenta corriente + asiento.

20. **Cheques de terceros (migraciones 023 y 024)**: cuando un cobro se registra con medio de pago Cheque, además del `pagos` se guarda una fila en `cheques` (un cheque por pago). Datos: tipo (`FISICO`/`ECHEQ`), número, banco, sucursal, titular, CUIT, fechas de emisión/vencimiento, monto, caja/banco destino, observaciones y estado (`EN_CARTERA`, `DEPOSITADO`, `COBRADO`, `ENDOSADO`, `RECHAZADO`, `ANULADO`).
   - Los campos se validan con `Cheque::datosDesdePost()`; `Cobro::registrar()` y `Pago::registrar()` insertan el cheque dentro de su transacción. Anular el cobro pasa el cheque a `ANULADO`.
   - La cartera se ve en `/cheques` (índice, detalle, edición y cambio de estado). Los avisos de vencimiento se envían con el CLI `php app/helpers/notificar_cheques.php [--dias=7]`; el script recorre tenants, usa el SMTP del tenant (`EmailConfig`) y marca `cheques.ultimo_aviso_at` para no repetir el mail el mismo día.
   - **Flujo financiero:** al recibir el cheque solo se acredita la ctacte del cliente y se debita `1350 Cheques en cartera` (activo). No toca caja/banco. `COBRADO` genera recién ahí el ingreso en la caja/banco elegida (`movimientos_caja` + asiento). `ENDOSADO` saca el cheque de cartera y cancela deuda con el proveedor vía `cuentas_corrientes_empresa` (`tipo = ENDOSO`), sin pasar por banco. `RECHAZADO` revierte la acreditación (si existía) y vuelve a debitar al cliente. Para anular el cheque hay que anular el cobro.

21. **Circuito de compras (migraciones 025, 026 y 027)**: OC → aprobación → ingreso de mercadería → factura → orden de pago.
    - **Aprobación de OC (025):** `ordenes_compra` tiene `aprobada_at` + `aprobado_por`. `OrdenCompra::aprobar($id, $usuarioId)` es transaction-aware, valida con `FOR UPDATE` que la OC esté `PENDIENTE` y registra quién y cuándo aprobó. El botón de aprobar es POST con CSRF (ya no un GET).
    - **Facturas de compra (026, `/facturascompra`):** `FacturaCompra::registrar()` valida proveedor activo, OC en `APROBADA/PARCIAL/RECIBIDA` del mismo proveedor, unicidad de número por proveedor (entre `REGISTRADA`) y que neto + IVA = total. Genera el asiento `Debe 5100 + 2200 (IVA) / Haber 2100` y no toca caja. La deuda de la factura = total − órdenes de pago en `APROBADA/PAGADA`. `anular()` bloquea si hay órdenes de pago activas y revierte el asiento (idempotente).
    - **Órdenes de pago (027, `/ordenespago`):** `OrdenPago::crear()` exige factura con saldo, usa el numerador `ORDEN_PAGO` (prefijo `OP-`) dentro de transacción y queda `BORRADOR`. Transiciones: `BORRADOR → APROBADA → PAGADA` o `ANULADA`; `PAGADA → ANULADA` revierte asiento y caja. Al pagar se revalida el saldo **excluyendo la orden propia** (`saldoFacturaExcluyendo()`), porque su monto ya está comprometido. El egreso de caja es `referencia_modulo = 'ORDENES_PAGO'`.
    - **Integración con Gastos:** `Gasto::getOCPendientes()` excluye OCs con factura `REGISTRADA` (esas se pagan por orden de pago) y solo trae `APROBADA/PARCIAL/RECIBIDA` (ya no `PENDIENTE`).
    - Los ids de la tabla `monedas` varían por tenant (en `tenant_pistachos` el peso es id 4, no 1): `FacturaCompra::monedaPorDefecto()` resuelve por `simbolo = '$'`, nunca hardcodear el id.

22. **Tipo de gestión y ficha técnica de MP (migración 035)**: `materias_primas.tipo_gestion` (`varchar(30)`, NOT NULL, default `UNIDAD`) define cómo se mide/consume la MP: `UNIDAD`, `LINEAL` (metros/barras/rollos), `SUPERFICIE` (m²) o `GRANEL` (kg/L). La ficha técnica vive en `materias_primas_especificaciones` (relación **1:1 opcional** con la MP: `materia_prima_id` UNIQUE + nullable, `parametro_texto_1`, `parametro_numerico_1`, `parametro_numerico_2`, `presentacion`, `obs`). Los parámetros son genéricos; su significado lo sugiere el `tipo_gestion` en el formulario. Se guarda con `Materiaprimaespecificacion::guardar()` dentro de la transacción de `MateriaPrima::create/update`; si todos los campos están vacíos se borra la fila. `MateriaPrima::find()` hace LEFT JOIN y devuelve la ficha aplanada. Validar siempre `tipo_gestion` con `Materiaprimaespecificacion::normalizarTipoGestion()`.

23. **Compra en unidad distinta al stock (migración 036)**: `materias_primas.id_unidadcompra` (nullable, FK `unidad_medida`) y `factor_conversion` (`decimal(18,6)`, default 1) permiten comprar en kg/barra/rollo/bolsa aunque el stock y las recetas sigan en la unidad de medida. `factor_conversion` es canónico: **unidades de stock por 1 unidad de compra**; el formulario de MP permite cargarlo en las dos direcciones y el servidor lo recalcula (`factor_modo` + `factor_valor`). Reglas:
    - `id_unidadcompra` NULL o igual a `id_unidadmedida` → sin conversión (factor 1).
    - OC e ingreso guardan `cantidad` y `precio_unitario` en **unidad de compra**. La OC y el ingreso muestran esa unidad.
    - Al registrar el ingreso, el movimiento de stock usa `MateriaPrima::cantidadStockDesdeCompra($mp, $cantidad)`.
    - Para costos/recetas, `MateriaPrima::getUltimosPreciosCompra()` devuelve el precio convertido a unidad de stock con `precioStockDesdeCompra()`.
    - Para generar OC desde faltantes de producción, `MateriaPrima::cantidadCompraDesdeStock()` convierte el faltante a unidad de compra.
    - `OrdenCompra::calcularStockProyectado()` convierte `en_compra` a unidad de stock antes de comparar.

24. **MP sobrante y depósito de origen en Producción (migración 037)**: `depositos.es_sobrante` identifica el depósito "MP sobrante" (se crea en la migración). Los restos de barras/rollos se guardan ahí; en ese depósito no se calcula equivalencia de presentación. `presentacion` sigue siendo solo una referencia (`9 × 6 M equiv.`), nunca unidades físicas contadas.
    - `reservas_materia_prima.deposito_id` guarda de qué depósito se tomó cada MP. Al crear una OP, el formulario muestra por cada MP el stock por depósito y el operario reparte la cantidad entre una o varias fuentes (`deposito_items[mpId][depositoId]`). La suma debe coincidir con lo necesario; si el stock total alcanza, ninguna fuente puede superar el stock de su depósito. Si no alcanza, se permite negativo para planificar la compra.
    - Se inserta una reserva y un movimiento `SALIDA` con `deposito_id` por cada fuente. La cancelación y el consumo (`CONSUMIDO`) se reparten proporcionalmente entre las fuentes reservadas, así el stock vuelve/sale de cada depósito. `findreservas` muestra una fila por fuente.
    - **Movimientos históricos con `deposito_id NULL`**: se imputan al depósito principal en las consultas por depósito (`Deposito::principalId()`). El stock total no cambia.
    - `Deposito::allSeleccionables(false)` excluye el sobrante en ingresos de mercadería y remitos; en transferencias y ajustes sí está disponible.
    - **Sobrante automático**: al finalizar o cancelar una OP, `OrdenProduccion::registrarSobrantesAutomaticos()` calcula el resto de la última presentación por cada MP con `presentacion` y cada fuente no sobrante, y lo mueve con `SALIDA` desde el origen + `ENTRADA` a "MP sobrante" (`origen = SOBRANTE_MP`). No cambia el stock total. Es idempotente/ajustable: recalcula el objetivo y mueve solo la diferencia, corrigiendo finalizaciones prematuras. El consumo desde el propio depósito sobrante no genera un nuevo sobrante.
    - **Solo avances confirmados**: `estadoChange`, `cantidadProducidaOP` y los totales de las vistas de Producción deben sumar filas con `confirma_produccion IS NOT NULL`. Si se suman avances cargados sin confirmar, la OP se finaliza antes de tiempo y el sobrante automático se calcula mal.
    - **Tope de producción**: no se puede cargar ni confirmar más de lo pedido. Al cargar un avance (`avances()`) se valida contra confirmados + pendientes; al confirmar (`confirmaravance()`) se valida contra los confirmados (sin contar el avance actual) y se rechaza con un mensaje claro. Reconfirmar un avance ya confirmado no duplica consumo ni stock. El formulario de avance limita el máximo a lo que queda por cargar.
    - La columna **Equiv. stock total** de la vista de stock es solo el desglose del stock total (`665 × 6 M + 5 M`), **no** el sobrante de una OP. El sobrante real se ve en la columna **Sobrante** y en el depósito "MP sobrante".

25. **Producción por procesos (migración 038)**: cada receta tiene al menos un proceso (`recetas_procesos`: nombre, `horas_por_unidad`, secuencia, activo). El ABM de receta exige mínimo uno; el modelo crea "Proceso 1" si no viene ninguno y la migración lo hizo para las recetas existentes.
    - Al crear una OP se copian los procesos a `ordenes_produccion_procesos` (snapshot de nombre/horas/secuencia). La primera fila arranca con `cantidad_en_proceso = cantidad pedida` y el resto en 0.
    - Pantalla `/ordenproduccion/procesos/{id}` (métodos `procesos`, `moverProceso`, `finalizarProceso`): permite mover cantidades parciales al siguiente proceso y finalizar piezas desde el último. Las OPs con procesos redirigen desde `/avance` al tablero; las OPs viejas sin filas siguen con el flujo legacy de avances.
    - Cada movimiento queda en `ordenes_produccion_procesos_movimientos` (`MOVER` o `FINALIZAR`).
    - Al finalizar piezas se inserta una fila confirmada en `orden_produccion_detalle` y se llama a `OrdenProduccion::aplicarAvanceConfirmado()`: consumo de MP, ingreso de producto terminado y `estadoChange()`. La OP queda abierta hasta que el total finalizado alcance la cantidad pedida.
    - El consumo de MP se registra al finalizar piezas (no al mover entre procesos); la reserva de MP ya se descuenta al crear la OP. El sobrante automático se calcula al finalizar/cancelar la OP.
    - El tablero muestra piezas por proceso, horas estimadas en curso y el historial. `totalFinalizado` sale de los avances confirmados.

26. **Baja de productos (inactivar) y buscadores del ABM**: `Producto::inactivar($id, $confirmarStock)` es la única vía de baja y sigue siendo **lógica** (`activo = 0`). La ruta `/productos/delete/{id}` es **POST con CSRF**; la vista manda `forzar_stock=1` cuando el usuario confirma en el SweetAlert.
    - **Bloqueos** (`Producto::motivosBloqueoInactivacion()`): solo mientras el compromiso siga abierto — presupuesto `APROBADO` cuya NP no está `RemitidoCompleto` (o que no tiene NP), NP `APROBADA` con `remitido` <> `RemitidoCompleto`, remito con `entregado = 0` (entrega pendiente de confirmar) y OC `APROBADA`/`PARCIAL` (hay que anular la OC). El mensaje lista los comprobantes concretos. Un presupuesto/NP ya entregado completo **no** bloquea.
    - **Entrega del remito (migración 039)**: `remitos_salida.entregado` (`entregado_por`, `entregado_at`) nace en 0 = entrega pendiente. Se confirma con `Remitosalida::confirmarEntrega()` desde el listado o el detalle (`/remitossalida/confirmarEntrega/{id}`, POST + CSRF) y se puede **revertir** con `revertirEntrega()` (`/remitossalida/revertirEntrega/{id}`, POST + CSRF): vuelve a quedar pendiente y el producto se vuelve a bloquear. La migración marcó como entregados los remitos existentes. El campo `firmado`/`firmado_at` es legacy y no se usa.
    - **Stock al inactivar**: si hay stock y no se confirmó, devuelve `requiere_confirmacion` y **no toca nada**. Al confirmar, por cada `(deposito_id, lote_id)` con saldo distinto de 0 inserta un movimiento que lo lleva a 0 (`SALIDA` si el saldo es positivo, `ENTRADA` si es negativo) con `origen = AJUSTE_STOCK` y `motivo` obligatorio, y pasa las `series` en estado `DISPONIBLE` a `BAJA`. Los movimientos con `deposito_id NULL` se liquidan imputándolos al depósito principal, así el total queda en 0.
    - **Buscadores del ABM** (`Producto::listadoAbm()` / `MateriaPrima::listadoAbm()` con `?q=` y form GET con `action` explícito): buscan por nombre, SKU, descripción (en MP: categoría u observaciones de la ficha técnica), precio (`CAST(... AS CHAR) LIKE`, tolera `1.500,50`) y códigos de barra. `all()` sigue devolviendo solo activos y la usan los formularios de presupuestos/NP/remitos/OC, así que un producto inactivo no se puede elegir en documentos nuevos.
    - **Listado de inactivos y reactivación**: el botón "Inactivos" (`?estado=inactivos`, que se conserva al buscar con un hidden `estado`) lista las bajas con `listadoAbm($q, true)` y muestra el botón Reactivar (`/productos/reactivar/{id}`, `/materiasprimas/reactivar/{id}`, POST + CSRF). `reactivar()` solo vuelve `activo = 1`: **no repone stock** (el ajuste a 0 ya se registró) ni reactiva las series pasadas a `BAJA`.

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

Crear archivo SQL en `app/helpers/migrations/` con el nombre `NNN_descripcion.sql` (prefijo numérico de 3 dígitos, correlativo) y ejecutarlo desde Admin → Migraciones, **en cada tenant** (el estado va en `tenants.schema_version` de la BD master).

> `app/helpers/squemadb/` NO es la carpeta de migraciones: ahí vive el schema base (`create_master.sql` para la BD master y `tenant_schema.sql` para crear la BD de un tenant nuevo). `app/helpers/migdiscontnuadas/` guarda las migraciones retiradas.

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
| Stock no se filtra por depósito | Verificar que migración 016 está aplicada y que `deposito_id` se envía en el POST |

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
| `app/helpers/MasterMigrationManager.php` | Migraciones del schema de la BD master |
| `app/models/Tarea.php` | Tareas del panel admin (tabla `tareas` en la BD master) |