# AGENTS.md — ERP Alimentos Triba (PHP multi-tenant)

> Contexto largo del proyecto: `README.md` (arquitectura y módulos) y `CONTEXT.md` (convenciones, mapa de controladores/modelos, flujos).
> Este archivo registra lo que **no** se deduce leyendo el código. Los errores que se detectaron en `README.md`/`CONTEXT.md` ya están corregidos ahí; si los docs contradicen al código, **gana el código**.

## Arranque y entorno

- PHP 8.2 (CLI disponible), **MariaDB 10.4** (no MySQL 8: `EXPLAIN` no acepta DML, y el server es 10.4.32), Apache/XAMPP. Composer trae solo `phpmailer/phpmailer`, `dompdf/dompdf`, `phpoffice/phpspreadsheet`: sin dev-deps, sin scripts, **sin sección `autoload`**.
- **Ruta fija:** `app/bootstrap.php` define `BASE_URL = {esquema}://{host}/app/public` con `$develop = 1` hardcodeado. El repo tiene que quedar en `C:\xampp\htdocs\app` con webroot `C:\xampp\htdocs`; la app se abre en `http://localhost/app/public/`. Mover la carpeta rompe todos los links y redirects.
- **`.env` NO está en git** (`.gitignore:3`) y hay que crearlo a mano. Lo lee `app/config/env.php` con `parse_ini_file(..., INI_SCANNER_TYPED)`: claves `DB_MASTER_*`, `DB_*`, `SMTP_*`; se parsea como INI (las comillas importan, vacío = cadena vacía).
- **`public/.htaccess` NO está en git** (`.gitignore:9`). Sin él no hay rewrite y no funciona el front controller. Contenido:
  ```
  Options -Indexes
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
  ```
- `storage/` y `public/uploads/` están gitignored y se crean solos (`Tenant::crearDirectorios()`, `empresaStoragePath()` con `mkdir`).
- No hay cliente `mysql` en el PATH: para tocar la BD usar PHP/PDO o el panel web (Admin → Migraciones).
- Bases: `app/helpers/squemadb/create_master.sql` (master `app_master`) y `app/helpers/squemadb/tenant_schema.sql` (BD de cada tenant).

## Verificación (no hay tests)

- **No existe suite de tests, PHPUnit, linter, formatter ni CI.** Lo único verificable en CLI es la sintaxis:
  ```bash
  php -l app/controllers/FooController.php
  php -l app/models/Foo.php
  ```
  La verificación real es manual en el navegador contra el tenant.
- `APP_DEBUG` **nunca se define** → `display_errors` está siempre off. Los errores PHP van al `error_log` de Apache (archivo `error_log` en la raíz, sin trackear). Log por tenant: `storage/empresa_{tenantId}/logs/YYYY-MM-DD.log` vía `empresaLog($msg, $nivel)`.
- **Para validar SQL contra la BD real sin tocar datos**: abrir un PDO con `PDO::ATTR_EMULATE_PREPARES => false` y llamar `$pdo->prepare($sql)` **sin ejecutar**. El server parsea la sentencia (COM_STMT_PREPARE) y devuelve error si la tabla o columna no existe, sin modificar nada. Es la única forma de chequear referencias a columnas en este repo (no hay tests). Ojo: el SQL armado por interpolación (`$placeholders`, `{$depositoFilter}`, `" . $var`) no se puede validar así.
- La BD de desarrollo está viva: master `app_master` + tenants `tenant_pistachos` (#2) y `tenant_dmtech` (#8), ambos en `schema_version = 20`.

## Arquitectura: lo no obvio

- **No hay autoloader.** `composer.json` no define `autoload`; todo se carga con `require_once` explícito. `app/bootstrap.php` ya carga `core/{Database,Auth,Controller,Model,Router,Csrf}.php` + `helpers/validationHelper.php`, así que el `require_once` por archivo es convención (y algunos modelos lo omiten sin romper nada) — pero **un modelo o servicio nuevo sí debe requirear sus dependencias**, porque no hay autoload que las resuelva. Sin namespaces: clases globales.
- **Código muerto eliminado**: `ComprasController` (duplicaba el flujo OC → Ingreso, sin `index()`, vista inexistente, usaba la columna legacy `materias_primas.stock_actual`) y el `CuentacorrienteempresaController` roto (namespaces inexistentes, `$this->redirect()` inexistente, vistas inexistentes, tablas inexistentes). Este último se **revivió** y ahora funciona: ver "Cta. Cte. Empresa" más abajo. Las tablas `compras` / `compras_detalle` quedaron sin uso en el schema (no las borres sin migración).
- Flujo: `public/index.php` → `app/bootstrap.php` (sesión, constantes, config) → `Router::run()`.
- **El Router conecta la BD del tenant ANTES de instanciar el controlador** (`app/core/Router.php`), porque los constructores crean modelos que llaman `Database::getInstance()`. Si instanciás un modelo en el constructor, la conexión ya está lista.
- `Database::getInstance()` **cae silenciosamente a la BD default de `.env`** (`DB_NAME`) si no hay tenant conectado: un modelo usado fuera del ciclo de request (CLI, script suelto) escribe en la base equivocada sin error. `User` y `Tenant` son los únicos que usan `Database::getMaster()`. **Un modelo nuevo que use `Database::getMaster()` va a la master** (instalación: tenants, usuarios, tareas); todo lo demás va a la BD del tenant.
- **Dos schemas, dos mecanismos de migración**:
  - **Tenant** (datos de la empresa): `app/helpers/migrations/NNN_*.sql`, los aplica `MigrationManager` por tenant desde Admin → Migraciones, con el estado en `tenants.schema_version` (master).
  - **Master** (instalación): `app/helpers/squemadb/master_migrations/NNN_*.sql`, los aplica `MasterMigrationManager` (control en la tabla `master_migrations` de la master), desde Admin → Migraciones → "Migraciones de la BD Master".
- Roles reales (`app/core/Role.php`): `SUPERADMIN`, `ADMIN`, `USUARIO`, `VISITOR`, `GERENTE_FINANCIERO`. `SUPERADMIN` (panel global) y `ADMIN` (admin de empresa) son roles distintos: el README ya está corregido, no volver a copiar la tabla vieja.
- `Auth::requireTenant()` expulsa al `SUPERADMIN` de los módulos de tenant; `Auth::requireAdminPanel()` hace lo inverso y limpia el tenant de la sesión. `AdminController` y `UsersController` son rutas de panel admin (solo superadmin); el resto exige tenant.

## Routing y naming

- URL: `?url=controller/método/param1/param2`. El controlador es `ucfirst(primer segmento) . 'Controller'` y su archivo debe estar en `app/controllers/` con ese nombre exacto (`materiasprimas` → `MateriasprimasController`).
- Los guiones en la parte del método pasan a camelCase: `admin/migrations-run-all` → `AdminController::migrationsRunAll()`. Los parámetros llegan como string y se castean en la firma (`edit(int $id)`).
- CRUD: `index/create/store/show/edit/update/delete/search`. `search()` responde JSON. `delete()` es baja lógica (`activo = 0`).
- Nombres de clase "planos" en singular: `Remitossalida`, `Cuentacorrientecliente`, `Materiaprima`. El directorio de vistas sí usa guiones bajos: `remitos_salida`, `materias_primas`, `cta_cte`.

## Convenciones obligatorias

- **CSRF en todo POST**: `Csrf::validate($_POST['csrf_token'])` + `<input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">`. Token único por sesión.
- Flash + redirect: `$_SESSION['success']` / `$_SESSION['error']` → `header('Location: ' . BASE_URL . '/ruta'); exit;`. Los renderiza `app/views/layout/alerts.php` (SweetAlert2).
- `validarId($id, BASE_URL . '/modulo')` antes de usar un ID de la URL (`app/helpers/validationHelper.php`).
- Vistas: `$this->view('dir/vista', $data)` mete `layout/header.php` + `layout/footer.php`; `adminView()` usa `admin_header/admin_footer`; `modal()` renderiza sin layout. Los datos llegan por `extract()` y las vistas son PHP plano con `<?= BASE_URL ?>/...` en todos los links.
- Baja lógica: `activo = 0`; los listados filtran `activo = 1`.
- Todo módulo nuevo necesita su link en `app/views/layout/header.php` (nav de tenant) o `app/views/layout/admin_header.php` (panel).
- **El backlog NO es un archivo**: las tareas viven en la tabla `tareas` de la **BD master** y se administran desde el panel admin (`/admin/tareas`, solo superadmin). Acciones: crear, confirmar, reabrir, eliminar. El viejo `tareas.json` de la raíz se eliminó.

## Stock: event-sourcing (no romper esto)

- El stock **nunca** se guarda como valor, se calcula desde `movimientos_stock`:
  ```sql
  SUM(CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad WHEN tipo = 'SALIDA' THEN -cantidad END)
  ```
  Desde la 016 el cálculo es **por depósito y por lote** (`deposito_id`, `lote_id`).
- Para escribir un movimiento usar `StockService::registerMovement([...])`. `updateStockProducto()` / `updateStockMateriaPrima()` son atajos con `origen = 'MANUAL'`.
- `origen` esperados: `REMITO_SALIDA, NOTA_PEDIDO, ORDEN_COMPRA, INGRESO_MERCADERIA, AJUSTE, PRODUCCION, DEVOLUCION, TRANSFERENCIA, AJUSTE_SDCOMP, MANUAL`.
- Transferencias entre depósitos en dos fases: origen → depósito virtual "En tránsito" (`es_transito = 1`) → destino. La anulación revierte según el estado.
- Con `usa_serie = 1` (migración 020) el producto/MP se maneja **unidad por unidad** en la tabla `series` (`DISPONIBLE` → `REMITIDO` → `EN_DEVOLUCION`/`BAJA`); en los remitos se eligen series, no cantidades.
- Cliente ocasional = `id 9999`: contemplarlo en toda lógica de ventas y ctacte.

## Cta. Cte. Empresa y "Mi Perfil" (módulos propios)

- **Cta. Cte. Empresa** (`/cuentacorrienteempresa`, migración 021): `INGRESO` = entra dinero, `GASTO` = sale dinero; saldo = ingresos - gastos. Con `proveedor_id` cargado, el saldo es la **deuda con ese proveedor** (`getSaldosPorProveedor()`). Anulación por **baja lógica** (`activo = 0`), no se borra físicamente. Tablas: `cuentas_corrientes_empresa` + `categorias_gastos_ingresos`.
- **"Mi Perfil"** (`/perfil`): el usuario logueado edita nombre, email y contraseña. **El `User` model escribe en la BD master**, así que después de actualizar hay que llamar `User::syncToTenant()` por cada empresa del usuario para no dejar la copia del tenant desincronizada. El rol NO se edita desde acá.
- **Forms de filtro con `method="get"`**: el router lee la ruta de `?url=`. Un form GET **sin `action`** borra el query string y manda al home. Todos deben llevar `action="<?= BASE_URL ?>/<ruta>"` (o un hidden `url`).

## Dinero y contabilidad

- `cuentas_corriente_clientes` es la ctacte (débitos por remitos, créditos por pagos). No recalcular saldos a mano: usar los métodos de `Cuentacorrientecliente`.
- **Toda** operación financiera debe generar su asiento con `app/helpers/AsientoAutomatico.php`: `ventaDebito`, `ventaCredito`, `ventaDevolucion`, `ventaReembolso`, `gastoPagar`, `gastoAnularPago`.
- Numeración de comprobantes: `Numerador::siguiente($tipo)` / `siguienteFormateado($tipo)`. Usa `FOR UPDATE` → llamarlo siempre dentro de una transacción, y el **tipo tiene que existir** en la tabla `numeradores` (se siembra con `INSERT INTO numeradores (...)` en la migración que crea el módulo, ej. `TRANSFERENCIA` en la 017).
- En operaciones sensibles (stock, saldo de caja, crédito): `FOR UPDATE` dentro de transacción y **forzar el precio desde el origen** (el remito) en vez de confiar en el POST.

## Migraciones

- **La carpeta correcta es `app/helpers/migrations/`, NO `app/helpers/squemadb/`**. En `squemadb/` vive el schema base (`create_master.sql`, `tenant_schema.sql`); `app/helpers/migdiscontnuadas/` (typo incluido) es el archivo de migraciones retiradas.
- `MigrationManager` tiene el path hardcodeado y solo toma archivos que matchean `^\d+_.+\.sql$`. Próximo número libre: **022**.
- Los `.sql` se ejecutan con `PDO::exec` partido por `;` (respeta comillas). **No usar `DELIMITER`** ni rutinas. Si un statement falla, el loop corta y `schema_version` queda en la última migración aplicada.
- Se aplican **por tenant** desde Admin → Migraciones (`/admin/migrations`, `/admin/migrations-run/{tenantId}`, `/admin/migrations-run-all`); el estado vive en `tenants.schema_version` (master). **Al agregar una migración hay que correrla en todos los tenants**: el archivo no basta.
- Un tenant nuevo arranca con `schema_version = 0` y corre 003..N. Si el schema base de `tenant_schema.sql` ya incluye una tabla, no volver a crearla sin `IF NOT EXISTS`.
- Los scripts PHP sueltos de `app/migrations/` (carpeta gitignored) usan `BASE_PATH`, que solo se define en `app/bootstrap.php`: **no corren con `php app/migrations/x.php`** aunque el README lo diga.

## Archivos, PDFs y emails

- Rutas por tenant (helpers en `app/config/config.php`): `empresaStoragePath('pagos')` → `storage/empresa_{id}/pagos`; `empresaUploadPath('productos')` → `public/uploads/productos/empresa_{id}`; `empresaUploadUrl('productos')`; `empresaLog($msg, $nivel)`; `config('empresa')` / `loadEmpresaFromDb()`.
- **Los PDFs NO van a `storage/pdfs/`**: cada uno usa `empresaStoragePath('<tipo>')`, que resuelve `storage/empresa_{tenantId}/<tipo>/`. Usar siempre el helper, nunca una ruta hardcodeada.
- Templates: `app/views/pdf/*.php` (p. ej. `devolucion.php`) y `app/views/mails/*.php` (recibo de pago, remito, presupuesto, NP). Ojo: `PdfService::renderTemplate()` carga de `views/mails/`, no de `views/pdf/`.
- Mail: `MailService::enviar($tipo, $referenciaId, $email, $usuarioId, $data, $options)` despacha por tipo; helpers `enviarRemito/enviarPresupuesto/enviarNotaPedido/enviarPago`. Los envíos se registran en `mails_log`. SMTP sale de `.env` (`mail.dmtech.com.ar:465`, SSL).

## Git

- Rama de trabajo: **`desarrollo`** (por defecto en `origin`). Commits en español, una línea descriptiva y al final la lista de archivos tocados con este formato:
  ```
  Descripción del cambio
  modified:   app/controllers/FooController.php
  new file:   app/views/foo/index.php
  ```
- No hay CI ni flujo de PR documentado: se sube directo a `desarrollo`.
