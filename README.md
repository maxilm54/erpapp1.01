# ERP Alimentos Triba S.R.L.

Sistema ERP multi-tenant para gestión integral de una empresa de alimentos (fabricación, ventas, compras, producción, contabilidad).

---

## Tabla de Contenidos

- [Visión General](#visión-general)
- [Arquitectura Técnica](#arquitectura-técnica)
- [Stack Tecnológico](#stack-tecnológico)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Sistema Multi-Tenant](#sistema-multi-tenant)
- [Autenticación y Permisos](#autenticación-y-permisos)
- [Módulos del Sistema](#módulos-del-sistema)
- [Base de Datos](#base-de-datos)
- [Migraciones](#migraciones)
- [Generación de PDFs](#generación-de-pdfs)
- [Envío de Emails](#envío-de-emails)
- [Instalación](#instalación)
- [Configuración](#configuración)

---

## Visión General

ERP desarrollado íntegramente en PHP custom MVC (sin frameworks externos) para la gestión operativa, financiera y contable de **Alimentos Triba S.R.L.**, una empresa de alimentos ubicada en Leones, Córdoba, Argentina.

El sistema cubre el ciclo completo del negocio:

```
Presupuesto → Nota de Pedido → Remito de Salida → Cobro
                                              ↓
                                        Cta. Corriente
                                              ↓
                                        Contabilidad

Orden de Compra → Ingreso de Mercadería → Stock
                                              ↓
                                        Gastos / Cta. Cte. Empresa
```

---

## Arquitectura Técnica

### Tipo: Custom MVC Framework

Framework propio en PHP 8+ con patrón Modelo-Vista-Controlador. No depende de Laravel, Symfony ni ningún framework externo.

### Componentes del Core

| Archivo | Función |
|---------|---------|
| `Router.php` | Enrutador URL → Controller::method(params). Conversión snake_case a camelCase. |
| `Controller.php` | Controlador base con `view()` que envuelve vistas en layout header/footer. |
| `Model.php` | Modelo base que inyecta conexión PDO via `Database::getInstance()`. |
| `Database.php` | Gestor de conexiones duales: DB master (usuarios/tenants) + DB tenant (dinámica). |
| `Auth.php` | Autenticación, manejo de sesión, control de tenant, verificación de roles. |
| `Role.php` | Constantes de roles y métodos de verificación de permisos. |
| `Middleware.php` | Middleware de requests: `auth()`, `guest()`, `role()`, `anyRole()`. |
| `Csrf.php` | Generación y validación de tokens CSRF. |
| `bootstrap.php` | Inicialización: sesión, constantes BASE_PATH/BASE_URL, carga de config. |

### Flujo de Request

```
public/index.php
  → bootstrap.php (session, constants, autoload)
  → Router::run()
    → Parse URL: ?url=controller/method/params
    → Verificar auth + tenant
    → Conectar BD del tenant
    → Instanciar Controller
    → Llamar Controller::method(params)
      → Controller usa Model (PDO)
      → Controller carga View (extract data → header → view → footer)
```

---

## Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| **Lenguaje** | PHP 8+ (strict types) |
| **Base de datos** | MySQL 8 (InnoDB, utf8mb4) |
| **Frontend** | Bootstrap 5.3 (CDN), Bootstrap Icons, SweetAlert2, Chart.js |
| **PDF** | Dompdf 3.1 (vía Composer) |
| **Email** | PHPMailer 7.0 (SMTP vía mail.dmtech.com.ar:465 SSL) |
| **Seguridad** | thecodingmachine/safe (envoltorio seguro de funciones PHP) |
| **Servidor** | Apache (XAMPP en desarrollo) |
| **Timezone** | America/Argentina/Buenos_Aires |

---

## Estructura del Proyecto

```
app/
├── bootstrap.php              # Inicialización de la aplicación
├── config/
│   ├── config.php             # Configuración general (timezone, SMTP, datos empresa)
│   ├── database.php           # Conexiones a BD (master + tenant)
│   ├── Cache.php              # Cache en archivos
│   └── env.php                # Parser de .env
├── controllers/               # 25 controladores
├── core/
│   ├── Router.php             # Enrutador URL
│   ├── Controller.php         # Controlador base
│   ├── Model.php              # Modelo base
│   ├── Database.php           # Gestor de conexiones
│   ├── Auth.php               # Autenticación
│   ├── Role.php               # Roles y permisos
│   ├── Middleware.php          # Middleware
│   └── Csrf.php               # Tokens CSRF
├── helpers/
│   ├── validationHelper.php   # Validación de inputs
│   ├── StockHelper.php        # Estados de stock
│   ├── AsientoAutomatico.php  # Asientos contables automáticos
│   ├── MailHelper.php         # Wrapper PHPMailer
│   ├── MigrationManager.php   # Gestor de migraciones
│   ├── migrations/            # Migraciones SQL (003-015)
│   └── squemadb/              # Schema SQL para tenants nuevos
├── models/                    # 30 modelos
├── services/
│   ├── StockService.php       # Servicio de stock
│   ├── MailService.php        # Servicio de emails con templates
│   └── PdfService.php         # Generación de PDFs
└── views/                     # 22 directorios de vistas
    ├── layout/                # header.php, footer.php, alerts.php
    ├── auth/                  # Login, registro, selección de tenant
    ├── home/                  # Dashboard principal
    ├── admin/                 # Gestión de tenants, usuarios, migraciones
    ├── clientes/              # ABM Clientes
    ├── proveedores/           # ABM Proveedores
    ├── productos/             # ABM Productos + códigos de barras
    ├── materias_primas/       # ABM Materias Primas + códigos de barras
    ├── recetas/               # Recetas (BOM) de producción
    ├── produccion/            # Órdenes de producción
    ├── notas_pedido/          # Notas de Pedido (ventas)
    ├── presupuestos/          # Presupuestos / Cotizaciones
    ├── remitos_salida/        # Remitos de Salida (manuales y desde NP)
    ├── ordenes_compras/       # Órdenes de Compra
    ├── stock/                 # Consulta de stock
    ├── ajustes_stock/         # Ajustes manuales de stock
    ├── cta_cte/               # Cuenta Corriente de clientes
    ├── cobros/                # Cobros a clientes
    ├── contabilidad/          # Contabilidad (asientos, plan de cuentas, etc.)
    ├── impuestos/             # Impuestos (IVA)
    ├── pdf/                   # Templates HTML para PDFs
    └── mails/                 # Templates HTML para emails

public/                        # Raíz web
├── index.php                  # Front controller
├── .htaccess                  # Reescritura Apache
├── assets/css/app.css         # Estilos globales
├── js/confirmations.js        # SweetAlert2 confirmaciones
└── uploads/                   # Archivos subidos
    ├── img_config/            # Logos de empresa para PDFs
    ├── productos/             # Imágenes de productos
    └── materiasprimas/        # Imágenes de materias primas

storage/                       # PDFs generados
├── pagos/                     # Recibos de pago
└── remitos/                   # Remitos por año/mes
```

---

## Sistema Multi-Tenant

### Diseño

```
┌─────────────────────────────────────────┐
│           Base de Datos Master           │
│  ┌─────────┐  ┌────────┐  ┌──────────┐ │
│  │ tenants │  │ users  │  │user_tenant│ │
│  └─────────┘  └────────┘  └──────────┘ │
└─────────────────────────────────────────┘
         │                    │
    ┌────┴────┐          ┌───┴───┐
    ▼         ▼          ▼       ▼
┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐
│Tenant A│ │Tenant B│ │Tenant C│ │Tenant D│
│app_    │ │app_    │ │app_    │ │app_    │
│triba   │ │empresa2│ │empresa3│ │empresa4│
└────────┘ └────────┘ └────────┘ └────────┘
 Cada tenant tiene su propia base de datos
 con el mismo schema pero datos independientes.
```

### Tablas Master

| Tabla | Descripción |
|-------|------------|
| `tenants` | Empresas registradas (nombre, dbname, host, activo, schema_version) |
| `users` | Usuarios globales (nombre, email, password_hash, rol) |
| `user_tenant` | Relación many-to-many usuario-tenant |

### Flujo de Conexión

1. Usuario hace login → se autentica contra `app_master.users`
2. Selecciona tenant → `Auth::setTenant()` conecta a la BD del tenant
3. `User::syncToTenant()` sincroniza el usuario a la BD del tenant (para FK)
4. Todas las queries del módulo usan la BD del tenant

---

## Autenticación y Permisos

### Roles

| Rol | Constante | Descripción |
|-----|-----------|------------|
| Superadmin | `ADMIN` | Acceso total: gestión de tenants, usuarios, migraciones. Puede cambiar de empresa. |
| Operario | `USUARIO` | Acceso a módulos operativos: ABM, ventas, compras, stock, cobros. |
| Gerente Financiero | `GERENTE_FINANCIERO` | Acceso a módulo SDCOMP (comprobantes internos) + visualización. |
| Visor | `VISITOR` | Solo lectura (pendiente de implementación completa). |

### Permisos por Rol

| Módulo | ADMIN | USUARIO | GERENTE_FINANCIERO |
|--------|-------|---------|-------------------|
| ABM (clientes, productos, etc.) | ✅ | ✅ | ❌ |
| Ventas (NP, remitos) | ✅ | ✅ | ❌ |
| Compras (OC, ingresos) | ✅ | ✅ | ❌ |
| Stock | ✅ | ✅ | ❌ |
| Producción | ✅ | ✅ | ❌ |
| Cobros | ✅ | ✅ | ❌ |
| Contabilidad | ✅ | ✅ | ❌ |
| Cta. Corriente | ✅ | ✅ | ❌ |
| SDCOMP (comprobantes internos) | ✅ | ❌ | ✅ |
| Admin (tenants, usuarios) | ✅ | ❌ | ❌ |
| Empresa (info, usuarios propios) | ✅ | ✅ | ❌ |

---

## Módulos del Sistema

### 1. ABM (Altas, Bajas, Modificaciones)

- **Clientes**: Razón social, CUIT, email, teléfono, dirección, localidad, distribuidor, observaciones financieras. Soporte para cliente ocasional (id=9999).
- **Proveedores**: Razón social, CUIT, email, teléfono, dirección.
- **Productos**: Nombre, SKU, precio de venta, stock mínimo/máximo/crítico, tipo (BASE/PRESENTACIÓN), imágenes. Soporte para códigos de barras (EAN, CODE128, CODE39, UPC, INTERNO).
- **Materias Primas**: Nombre, SKU, precio actual, stock mínimo/máximo/crítico, categoría, unidad de medida. Soporte para códigos de barras.

### 2. Flujo de Ventas

```
Presupuesto → Nota de Pedido → Remito de Salida → PDF → Email
    ↓              ↓                  ↓
 (Opcional)    Aprobación         Impacto Stock
                                   Cta. Corriente
                                   Asiento Contable
```

- **Presupuestos**: Cotizaciones con validez, productos, cantidades, precios. Estados: BORRADOR → APROBADO.
- **Notas de Pedido**: Pedidos de venta vinculados a presupuesto. Estados: BORRADOR → APROBADA → SinRemitir/Parcial/Completo → ANULADA.
- **Remitos de Salida**: Dos vías:
  - *Desde NP*: Se generan automáticamente desde una nota de pedido aprobada.
  - *Manual*: Se crean directamente con búsqueda de productos por AJAX.
  - Genera PDF con diseño corporativo, legal "R" y texto RG AFIP 1415.
  - Envía PDF por email al cliente.
  - Impacta stock (SALIDA) y genera débito en ctacte.

### 3. Flujo de Compras

```
Orden de Compra → Ingreso de Mercadería → Stock
      ↓                    ↓                ↓
  Aprobación         Recepción Física    ENTRADA
  Cta. Cte. Empresa  Verificación        Asiento
```

- **Órdenes de Compra**: Solicitudes a proveedores. Soportan MP o productos, moneda (ARS/USD/EUR). Estados: PENDIENTE → APROBADA → RECIBIDA/PARCIAL → ANULADA.
- **Ingresos de Mercadería**: Recepción física contra OC. Verificación de cantidades. Impacto automático de stock.

### 4. Producción

```
Receta (BOM) → Orden de Producción → Avances → Confirmación
     ↓               ↓                  ↓            ↓
  Materias       Reserva MP         Producción    Stock
  Primas         (disponible)       Parcial       Final
```

- **Recetas**: Listas de materiales (BOM). Cada receta tiene detalle de materias primas con cantidades.
- **Órdenes de Producción**: Vinculadas a receta. Reservan MP automáticamente. Permiten avances parciales (producción incrementales). Estados: PENDIENTE → EN_PRODUCCION → FINALIZADA/CANCELADA.
- El sistema verifica disponibilidad de MP antes de iniciar y al confirmar producción.

### 5. Stock

- **Motor de stock basado en movimientos**: Todo stock se calcula por event-sourcing desde `movimientos_stock`:
  ```sql
  Stock = SUM(CASE WHEN tipo IN ('ENTRADA','AJUSTE') THEN cantidad
                   WHEN tipo = 'SALIDA' THEN -cantidad END)
  ```
- **Orígenes de movimientos**: REMITO_SALIDA, NOTA_PEDIDO, ORDEN_COMPRA, INGRESO_MERCADERIA, AJUSTE, PRODUCCION, etc.
- **Ajustes manuales**: Conjustificación, por producto o materia prima.
- **Alertas**: Stock crítico, bajo mínimo, sobre stock.
- **Vistas**: Stock de productos, stock de materias primas, historial de movimientos.

### 6. Cuenta Corriente y Cobros

- **Cta. Corriente Clientes**: Registra débitos (remisos) y créditos (pagos). Saldo corriente por cliente. Soporte para clientes ocasionales (id=9999) con nombre.
- **Cobros**: 
  - Lista de ventas no cobradas (remitos con saldo pendiente).
  - Registro de pago con medio de pago (efectivo, transferencia, cheque, tarjeta).
  - Asignación a caja/banco.
  - Genera recibo PDF y envía por email.
  - Registra crédito en ctacte.
  - Genera asiento contable automático.
- **Pagos a Proveedores**: Similar a cobros pero para egresos.

### 7. Contabilidad (Partida Doble)

- **Plan de Cuentas**: Árbol jerárquico (ACTIVO, PASIVO, PATRIMONIO, INGRESO, EGRESO). 5 niveles.
- **Asientos Contables**: Partida doble (DEBE/HABER). Asientos manuales y automáticos.
- **Asientos Automáticos**: Generados por cobros, pagos, remitos, órdenes de compra.
- **Cajas y Bancos**: Gestión de cuentas de efectivo y bancarias. Saldos y movimientos.
- **Conciliación Bancaria**: Conciliación de extracto bancario con movimientos internos.
- **Balance General**: Estados contables (ACTIVO = PASIVO + PATRIMONIO).
- **Estado de Resultados**: INGRESOS - EGRESOS = Resultado del período.
- **IVA**: Cálculo de débito fiscal (ventas) y crédito fiscal (compras). Saldo a favor/en contra.

### 8. Gastos

- Categorías: PROVEEDORES, SUELDOS, SERVICIOS, ALQUILER, IMPUESTOS, OTROS.
- Vinculación a órdenes de compra.
- Desglose de IVA.
- Asignación a caja/banco.
- Asiento contable automático.

### 9. SDCOMP (Comprobantes Internos)

Módulo para registrar movimientos de stock que no generan comprobante fiscal ni asiento contable. Diseñado con nomenclatura genérica para uso interno.

- **Tipos**: Salida (equivalente a venta) / Entrada (equivalente a compra).
- **Estados**: PENDIENTE → PARCIAL → COBRADO/PAGADO → ANULADO.
- **Clientes ocasionales**: Soporte para registros sin cliente registrado.
- **Stock**: Impacta movimientos con origen `AJUSTE_SDCOMP`.
- **Dashboard**: KPIs, deudores, proveedores, resumen por tipo/estado.
- **Acceso**: Solo roles ADMIN y GERENTE_FINANCIERO.

---

## Base de Datos

### Estructura Multi-Tenant

- **Master DB** (`app_master`): 3 tablas (tenants, users, user_tenant).
- **Tenant DB** (ej: `app_triba`): 50+ tablas con datos de la empresa.

### Tablas Principales del Tenant

| Categoría | Tablas |
|-----------|--------|
| **ABM** | `clientes`, `proveedores`, `productos`, `materias_primas`, `categorias_mp_id`, `unidad_medida`, `monedas` |
| **Códigos** | `producto_codigos`, `materiaprima_codigos`, `conversiones` |
| **Ventas** | `presupuestos`, `presupuestos_detalle`, `notas_pedido`, `notas_pedido_detalle`, `remitos_salida`, `remitos_salida_detalle` |
| **Compras** | `ordenes_compra`, `ordenes_compra_detalle`, `ingresos_mercaderia`, `ingresos_mercaderia_detalle`, `compras`, `compras_detalle` |
| **Producción** | `recetas`, `recetas_detalle`, `ordenes_produccion`, `orden_produccion_detalle`, `reservas_materia_prima` |
| **Stock** | `movimientos_stock`, `vststock_movstock_producto`, `vststock_movstock_materiaprima` |
| **Finanzas** | `cuentas_corriente_clientes`, `pagos`, `gastos`, `cajas_bancos`, `movimientos_caja` |
| **Contabilidad** | `asientos_contables`, `asientos_detalle`, `cuentas_contables`, `conciliaciones_bancarias`, `conciliaciones_detalle` |
| **Impuestos** | `impuestos` |
| **Cta. Empresa** | `cuentas_corrientes_empresa`, `categorias_gastos_ingresos` |
| **SDCOMP** | `movimientos_no_declarados`, `movimientos_no_declarados_detalle`, `movimientos_no_declarados_pagos` |
| **Sistema** | `act_bd`, `numeradores`, `mails_log`, `users` (stub), `historial_cambio_precios`, `stock_mp_transito`, `tbl_historico_reserva_mp` |

### Vistas SQL

| Vista | Descripción |
|-------|------------|
| `vststock_movstock_producto` | Stock agregado de productos desde movimientos |
| `vststock_movstock_materiaprima` | Stock agregado de materias primas desde movimientos |

### Trigger

- `historico_reserva_mp_trig`: Al eliminar de `reservas_materia_prima`, archiva en `tbl_historico_reserva_mp`.

---

## Migraciones

### Sistema de Migraciones

El `MigrationManager.php` controla la versión del schema por tenant. Cada tenant tiene un `schema_version` en la tabla `tenants`. Al ejecutar migraciones, se comparan las versiones y se aplican solo las pendientes.

### Migraciones SQL

| # | Archivo | Descripción |
|---|---------|-------------|
| 003 | `003_create_contabilidad_tables.sql` | Tablas de contabilidad (asientos, plan de cuentas, cajas, conciliación) |
| 004 | `004_create_impuestos_table.sql` | Tabla de impuestos (IVA) |
| 005 | `005_add_caja_banco_to_gastos.sql` | Agregar caja_banco_id a gastos |
| 006 | `006_add_remitos_manuales.sql` | Remitos manuales (columnas de cliente) |
| 007 | `007_add_precio_unitario_to_remitos_detalle.sql` | Precio unitario en detalle de remitos |
| 008 | `008_add_caja_banco_anulado_to_pagos.sql` | Caja/banco y flag anulado en pagos |
| 009 | `009_add_cliente_ocasional.sql` | Cliente genérico OCASIONAL (id=9999) |
| 010 | `010_add_cliente_nombre_to_ctacte.sql` | Nombre de cliente en cuenta corriente |
| 011 | `011_add_cliente_nombre_to_pagos.sql` | Nombre de cliente en pagos |
| 012 | `012_add_numero_transaccion_to_conciliacion.sql` | Número de transacción en conciliación |
| 013 | `013_add_remito_id_to_pagos.sql` | ID de remito en pagos |
| 014 | `014_create_movimientos_no_declarados.sql` | Tablas de comprobantes internos (SDCOMP) |
| 015 | `015_fix_detalle_no_declarados.sql` | Fix: agregar materia_prima_id al detalle |

---

## Generación de PDFs

### Servicio: `PdfService.php`

Utiliza **Dompdf** para generar PDFs a partir de templates HTML.

### PDFs Generados

| Tipo | Template | Descripción |
|------|----------|------------|
| Remito de Salida | `views/pdf/remito_salida.php` | Diseño corporativo con logo, "R" legal, texto RG AFIP 1415, tabla de productos,totales, footer fijo. |
| Recibo de Pago | `views/mails/pago.php` | Comprobante de cobro con datos del cliente, monto, medio de pago. |

### Almacenamiento

```
storage/
├── pagos/pago_{id}.pdf
└── remitos/{YYYY}/{MM}/remito_{id}.pdf
```

---

## Envío de Emails

### Servicio: `MailService.php`

Utiliza **PHPMailer** con SMTP (mail.dmtech.com.ar:465 SSL).

### Emails Enviados

| Tipo | Template | Adjunto |
|------|----------|---------|
| Remito de Salida | `views/mails/remito.php` | PDF del remito |
| Pago/Cobro | `views/mails/pago.php` | PDF del recibo |

### Funcionalidades

- BCC automático a dirección de sistema.
- Log de envíos en tabla `mails_log`.
- Soporte para múltiples destinatarios.

---

## Instalación

### Requisitos

- PHP 8.0+
- MySQL 8.0+
- Apache (con mod_rewrite habilitado)
- Composer

### Pasos

1. **Clonar el repositorio**:
   ```bash
   git clone <repo-url> C:\xampp\htdocs\app
   ```

2. **Instalar dependencias**:
   ```bash
   cd C:\xampp\htdocs\app
   composer install
   ```

3. **Crear bases de datos**:
   - Crear la BD master (`app_master`) usando `app/helpers/squemadb/create_master.sql`
   - Crear la primera BD tenant usando `app/helpers/squemadb/tenant_schema.sql`

4. **Configurar credenciales**:
   - Editar `app/config/database.php` con credenciales de MySQL
   - Crear archivo `.env` con configuración SMTP

5. **Crear usuario admin**:
   - Registrar el primer usuario vía `AuthController::register()`
   - O insertar directamente en `app_master.users`

6. **Configurar Apache**:
   - Habilitar `mod_rewrite`
   - Asegurar que `.htaccess` funcione (AllowOverride All)

7. **Crear primer tenant**:
   - Login como superadmin
   - Admin → Nueva Empresa
   - Seleccionar empresa para trabajar

---

## Configuración

### Archivo `.env`

```env
APP_ENV=development
APP_DEBUG=true
SMTP_HOST=mail.dmtech.com.ar
SMTP_PORT=465
SMTP_USERNAME=...
SMTP_PASSWORD=...
```

### Archivo `app/config/config.php`

```php
define('APP_NAME', 'Alimentos Triba S.R.L.');
define('TIMEZONE', 'America/Argentina/Buenos_Aires');

// Datos de la empresa (para PDFs)
$config['empresa'] = [
    'nombre'   => 'Alimentos Triba S.R.L.',
    'cuit'     => '30-31778732-5',
    'domicilio'=> 'Leones, Córdoba',
    'telefono' => '...',
    'email'    => '...',
    'logo'     => 'triba_log.png',
];
```

### Archivo `app/config/database.php`

```php
// Master DB
define('DB_MASTER_HOST', 'localhost');
define('DB_MASTER_NAME', 'app_master');
define('DB_MASTER_USER', 'root');
define('DB_MASTER_PASS', '');

// Tenant default
define('DB_TENANT_HOST', 'localhost');
define('DB_TENANT_NAME', 'app');
define('DB_TENANT_USER', 'root');
define('DB_TENANT_PASS', '');
```

---

## Comandos Útiles

| Comando | Descripción |
|---------|------------|
| `php app/migrations/add_role_to_users.php` | Agregar campo rol a users |
| `php app/migrations/create_cuentas_corrientes_empresa_table.php` | Crear tabla ctacte empresa |
| Admin → Migraciones | Panel web para ejecutar migraciones SQL pendientes |

---

## Notas de Desarrollo

- El sistema usa **event-sourcing para stock**: el stock actual se calcula sumando movimientos, no se almacena como valor estático.
- Los **asientos contables** se generan automáticamente para cobros, pagos y remitos.
- El **cliente ocasional** (id=9999) permite registrar ventas sin cliente registrado en el ABM.
- Los **remitos manuales** buscan productos por AJAX con autocompletado.
- El módulo **SDCOMP** usa nomenclatura genérica ("AJUSTE") para no revelar la naturaleza de los movimientos.
- Los **PDFs** incluyen diseño corporativo con logo de la empresa y texto legal argentino (RG AFIP 1415).
