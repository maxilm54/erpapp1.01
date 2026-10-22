# ERP Cliente

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
| **Base de datos** | MariaDB 10.4 (InnoDB, utf8mb4) — compatible con MySQL 8 |
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
├── controllers/               # 35 controladores
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
│   ├── migrations/            # Migraciones SQL (003-021)
│   └── squemadb/              # Schema SQL para tenants nuevos
├── models/                    # 41 modelos
├── services/
│   ├── StockService.php       # Servicio de stock
│   ├── MailService.php        # Servicio de emails con templates
│   └── PdfService.php         # Generación de PDFs
└── views/                     # 38 directorios de vistas
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
    ├── devoluciones/          # Devoluciones de remitos
    ├── ordenes_compras/       # Órdenes de Compra
    ├── stock/                 # Consulta de stock
    ├── ajustes_stock/         # Ajustes manuales de stock
    ├── depositos/             # Depósitos (CRUD)
    ├── lotes/                 # Lotes y vencimientos
    ├── transferencias/        # Transferencias entre depósitos
    ├── cta_cte/               # Cuenta Corriente de clientes
    ├── cuenta_corriente_emp/  # Cuenta Corriente de la empresa
    ├── cobros/                # Cobros a clientes
    ├── perfil/                # "Mi Perfil" (datos del usuario logueado)
    ├── contabilidad/          # Contabilidad (asientos, plan de cuentas, etc.)
    ├── impuestos/             # Impuestos (IVA)
    ├── sdcomp/                # Comprobantes internos
    ├── ingresos/              # Ingresos de mercadería
    ├── gastos/                # Gastos
    ├── creditos/              # Créditos bancarios
    ├── empresa/               # Info y configuración de la empresa
    ├── numeradores/           # Numeradores de comprobantes
    ├── email/                 # Configuración y envío de emails
    ├── users/                 # Gestión de usuarios
    ├── categoriamaterial/     # Categorías de materias primas
    ├── unidadmedida/          # Unidades de medida
    ├── pdf/                   # Templates HTML para PDFs
    └── mails/                 # Templates HTML para emails
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
| `tareas` | Registro de tareas del panel de administración (PENDIENTE / HECHA) |
| `master_migrations` | Control de migraciones aplicadas al schema master |

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
| Superadmin | `SUPERADMIN` | Acceso total: gestión de tenants, usuarios, migraciones. **No accede a los módulos de tenant** (`Auth::requireTenant()` lo redirige al panel admin). |
| Administrador | `ADMIN` | Admin de la empresa (tenant): ABM, ventas, compras, stock, cobros, contabilidad. Puede administrar los usuarios de su empresa. |
| Operario | `USUARIO` | Acceso a módulos operativos: ABM, ventas, compras, stock, cobros. |
| Gerente Financiero | `GERENTE_FINANCIERO` | Acceso a módulo SDCOMP (comprobantes internos) + visualización. |
| Visor | `VISITOR` | Solo lectura (pendiente de implementación completa). |

> `SUPERADMIN` y `ADMIN` son roles **distintos**: el primero administra la instalación (tenants) y **no accede a los módulos de tenant**; el segundo administra una empresa. Definidos en `app/core/Role.php`.

### Permisos por Rol

| Módulo | SUPERADMIN | ADMIN | USUARIO | GERENTE_FINANCIERO |
|--------|-----------|-------|---------|-------------------|
| ABM (clientes, productos, etc.) | ❌ | ✅ | ✅ | ❌ |
| Ventas (NP, remitos) | ❌ | ✅ | ✅ | ❌ |
| Compras (OC, ingresos) | ❌ | ✅ | ✅ | ❌ |
| Stock | ❌ | ✅ | ✅ | ❌ |
| Producción | ❌ | ✅ | ✅ | ❌ |
| Cobros | ❌ | ✅ | ✅ | ❌ |
| Contabilidad | ❌ | ✅ | ✅ | ❌ |
| Cta. Corriente | ❌ | ✅ | ✅ | ❌ |
| SDCOMP (comprobantes internos) | ❌ | ✅ | ❌ | ✅ |
| Admin global (tenants, usuarios, migraciones) | ✅ | ❌ | ❌ | ❌ |
| Empresa (info, usuarios propios) | ❌ | ✅ | ✅ | ❌ |

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
- **Devoluciones**: Ver sección 2b.

### 2b. Flujo de Devoluciones

```
Remito de Salida → Devolución → Stock + CtaCte + Caja + Asiento → PDF → Email
                       ↓
              Reembolso: NC (CtaCte) / Efectivo / Transferencia
```

- **Referencia obligatoria**: Siempre vinculada a un remito de salida existente.
- **Validación de cantidades**: Se calcula `pendiente_devolver` por producto (cantidad remitida - ya devuelto en devoluciones anteriores). No se permite devolver más de lo pendiente.
- **Condiciones de producto**: NUEVO, BUEN_ESTADO, ESTADO_REGULAR, DANADO, INSERVIBLE.
- **Reingreso vs descarte**: `reingresa` = cantidad total devuelta. `descarta` ≤ `reingresa` (subconjunto que se descarta, no vuelve a stock).
- **Reembolso**:
  - *Nota de Crédito*: Siempre cubre el total de la devolución. Se registra como crédito en ctacte.
  - *Efectivo / Transferencia*: Solo por el exceso sobre la deuda del cliente. Requiere caja/banco con saldo suficiente.
  - La deuda del cliente se consulta en tiempo real y se muestra en el formulario.
- **Validaciones server-side** (no dependen del cliente):
  - Cantidad ≤ `pendiente_devolver` (verificado también con `FOR UPDATE` en transacción).
  - Precio forzado desde el remito (no se acepta el precio del POST).
  - Condición en lista blanca.
  - Saldo de caja/banco ≥ monto del reembolso (verificado con `FOR UPDATE` en transacción).
- **Distribución FIFO en ventas no cobradas**: Si una devolución excede el saldo de un remito, el exceso reduce el saldo de otros remitos del mismo cliente (más antiguo primero), evitando que el sobrante se pierda.
- **Impactos**: Stock (ENTRADA por reingreso, SALIDA por descarte), CtaCte (crédito), Caja/Banco (EGRESO si reembolso en efectivo/transferencia), Asiento contable automático.
- **Estados**: PENDIENTE → PROCESADA / ANULADA. La anulación revierte todos los impactos.

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
- **Orígenes de movimientos**: REMITO_SALIDA, NOTA_PEDIDO, ORDEN_COMPRA, INGRESO_MERCADERIA, AJUSTE, PRODUCCION, DEVOLUCION, TRANSFERENCIA, etc.
- **Depósitos (nuevo)**: Stock desglosado por depósito físico. Cada movimiento de stock ahora puede asociarse a un depósito (`deposito_id`) mediante las columnas agregadas en migración 016.
- **Lotes y Vencimientos (nuevo)**: Cada movimiento puede asociarse a un lote (`lote_id`) con código de lote, fecha de vencimiento y fecha de producción. Tabla `lotes` vinculada a `productos` o `materias_primas`.
- **Control de N° de Serie por unidad (nuevo)**: Flag `usa_serie` en productos y materias primas (migración 020). Cuando está activo:
  - El producto/MP se **recepciona y remite unidad a unidad**: la tabla `series` registra cada unidad con su `numero_serie` único, estado (`DISPONIBLE` → `REMITIDO` → `EN_DEVOLUCION`/`BAJA`), depósito, lote e ingreso/remito asociados.
  - **Ingreso**: los items con serie se expanden en N filas (una por unidad), cada una con su N° de serie obligatorio y validación de unicidad.
  - **Remito** (desde NP y manual): en lugar de cantidad se muestra un selector múltiple de series DISPONIBLES en el depósito (cargado vía AJAX a `remitossalida/seriesDisponibles`); al confirmar, cada serie se marca `REMITIDO` con su `remito_salida_id`.
  - **Detalle**: `productos/show` y `materiasprimas/show` muestran la tabla de unidades con serie, su estado, depósito, lote, ingreso y remito.
- **Transferencias entre Depósitos (nuevo)**: Módulo para mover stock de un depósito a otro en dos fases:
  1. Confirmar salida → mercadería queda "En Tránsito" (depósito virtual).
  2. Confirmar recepción → se acredita en el depósito destino.
  La anulación devuelve el stock al origen si está en tránsito, o lo retira del destino si ya fue recibida.
- **Ajustes manuales**: Con justificación, por producto o materia prima. Ahora incluyen selección de depósito y lote.
- **Stock por depósito**: Las vistas de stock tienen filtro por depósito. Los remitos e ingresos de mercadería validan stock contra el depósito seleccionado.
- **Alertas**: Stock crítico, bajo mínimo, sobre stock.
- **Vistas**: Stock de productos, stock de materias primas, historial de movimientos (ahora muestra depósito, lote y vencimiento).

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
- **Cta. Corriente de la Empresa** (`/cuentacorrienteempresa`): libro de movimientos de la empresa.
  - `INGRESO` = entra dinero a la empresa; `GASTO` = sale dinero. Saldo = ingresos - gastos.
  - Cada movimiento tiene categoría (`categorias_gastos_ingresos`) y, opcionalmente, **proveedor**: con proveedor cargado, el saldo es la **deuda que la empresa tiene con ese proveedor**.
  - Filtros por tipo, categoría, proveedor, rango de fechas y texto; paginado de 50 por página.
  - Anulación por baja lógica (`activo = 0`): los movimientos contables no se borran físicamente.
  - Ruta: `/cuentacorrienteempresa`, `/create`, `/edit/{id}`, `/delete/{id}`.

### 6b. Perfil del Usuario

- **Mi Perfil** (`/perfil`): el usuario logueado ve y edita sus propios datos (nombre, email y contraseña).
- No permite cambiar el rol ni el estado: eso es exclusivo de un administrador de la empresa.
- El cambio se guarda en la BD master (`app_master.users`) y se propaga a la copia del usuario en cada empresa (`users` del tenant) para que no quede desincronizado.

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

### 10. Panel de Administración (SUPERADMIN)

`/admin` — solo el superadmin (el `Router` manda al panel admin a `AdminController` y `UsersController`; el superadmin **no** entra a los módulos de tenant).

- **Dashboard**: empresas, usuarios, tareas pendientes y accesos rápidos.
- **Empresas**: alta/edición de tenants (crea la BD desde `tenant_schema.sql` y los directorios de la empresa).
- **Usuarios**: gestión global y por empresa.
- **Migraciones**: estado por tenant + migraciones pendientes de la BD master.
- **Tareas** (`/admin/tareas`): registro de tareas de la instalación.

#### Registro de tareas

Reemplaza al viejo `tareas.json` de la raíz del repo (eliminado). Vive en la tabla `tareas` de la **BD master**.

- **Estados**: `PENDIENTE` → `HECHA`. Acciones: crear, **confirmar**, reabrir y eliminar.
- Cada tarea puede asociarse a una empresa (`tenant_id`) o ser general.
- Al confirmar se guardan `completada_at` y `completada_por`; al reabrir, se limpian.
- Rutas: `/admin/tareas`, `/admin/tareas-create`, `/admin/tareas-confirmar/{id}`, `/admin/tareas-reabrir/{id}`, `/admin/tareas-delete/{id}`.

---

## Base de Datos

### Estructura Multi-Tenant

- **Master DB** (`app_master`): 5 tablas (tenants, users, user_tenant, tareas, master_migrations).
- **Tenant DB** (ej: `app_triba`): 50+ tablas con datos de la empresa.

### Tablas Principales del Tenant

| Categoría | Tablas |
|-----------|--------|
| **ABM** | `clientes`, `proveedores`, `productos`, `materias_primas`, `categorias_mp_id`, `unidad_medida`, `monedas` |
| **Códigos** | `producto_codigos`, `materiaprima_codigos`, `conversiones` |
| **Ventas** | `presupuestos`, `presupuestos_detalle`, `notas_pedido`, `notas_pedido_detalle`, `remitos_salida`, `remitos_salida_detalle`, `devoluciones`, `devoluciones_detalle`, `devoluciones_reembolsos` |
| **Compras** | `ordenes_compra`, `ordenes_compra_detalle`, `ingresos_mercaderia`, `ingresos_mercaderia_detalle`, `compras`, `compras_detalle` |
| **Producción** | `recetas`, `recetas_detalle`, `ordenes_produccion`, `orden_produccion_detalle`, `reservas_materia_prima` |
| **Stock** | `movimientos_stock`, `vststock_movstock_producto`, `vststock_movstock_materiaprima`, `depositos`, `lotes`, `series` |
| **Transferencias** | `transferencias_depositos`, `transferencias_detalle` |
| **Finanzas** | `cuentas_corriente_clientes`, `pagos`, `gastos`, `cajas_bancos`, `movimientos_caja` |
| **Contabilidad** | `asientos_contables`, `asientos_detalle`, `cuentas_contables`, `conciliaciones_bancarias`, `conciliaciones_detalle` |
| **Impuestos** | `impuestos` |
| **Cta. Empresa** | `cuentas_corrientes_empresa`, `categorias_gastos_ingresos` |
| **SDCOMP** | `movimientos_no_declarados`, `movimientos_no_declarados_detalle`, `movimientos_no_declarados_pagos` |
| **Sistema** | `act_bd`, `numeradores`, `mails_log`, `users` (stub), `historial_cambio_precios`, `stock_mp_transito`, `tbl_historico_reserva_mp` |
| **Cta. Empresa** | `cuentas_corrientes_empresa`, `categorias_gastos_ingresos` |

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
|---|-------|--------------|
| 003 | `003_create_contabilidad_tables.sql` | Tablas de contabilidad (asientos, plan de cuentas, cajas, conciliación) |
| 004 | `004_create_impuestos_table.sql` | Tabla de impuestos (IVA) |
| 005 | `005_add_remitos_manuales.sql` | Remitos manuales (columnas de cliente) |
| 006 | `006_add_remito_id_to_pagos.sql` | ID de remito en pagos |
| 007 | `007_create_movimientos_no_declarados.sql` | Tablas de comprobantes internos (SDCOMP) |
| 008 | `008_creditos_bancarios.sql` | Tabla de créditos bancarios |
| 009 | `009_add_pdf_columns_sdcomp.sql` | Columnas PDF en SDCOMP |
| 010 | `010_email_config_templates.sql` | Configuración y templates de email |
| 011 | `011_email_default_templates.sql` | Templates de email por defecto |
| 012 | `012_add_activo_to_unidad_medida_and_categorias.sql` | Baja lógica en UM y categorías |
| 013 | `013_add_pdf_path_to_presupuestos.sql` | PDF path en presupuestos |
| 014 | `014_add_pdf_path_to_notas_pedido.sql` | PDF path en notas de pedido |
| 015 | `015_create_devoluciones.sql` | Tablas de devoluciones + cuentas contables 4400/5110 + numerador DEV- |
| 016 | `016_depositos_lotes.sql` | Tablas `depositos` y `lotes`, columnas `deposito_id`/`lote_id` en `movimientos_stock` |
| 017 | `017_transferencias_depositos.sql` | Tablas `transferencias_depositos` y `transferencias_detalle` + numerador TRANSFERENCIA |
| 018 | `018_transferencias_transito.sql` | Columna `es_transito` en `depositos`, depósito virtual "En Tránsito", estado `EN_TRANSITO` + `recibida_at`/`recibida_por` |
| 019 | `019_lotes_serie.sql` | Columna `numero_serie` en `lotes` |
| 020 | `020_serie_unidades.sql` | Columna `usa_serie` en `productos` y `materias_primas`, tabla `series` (unidades individuales con control de N° de serie) |
| 021 | `021_cuenta_corriente_empresa.sql` | Tablas `categorias_gastos_ingresos` y `cuentas_corrientes_empresa` (ctacte de la empresa, con deuda por proveedor) |

### Migraciones de la BD Master

El schema de la **base master** (instalación) tiene su propio mecanismo, independiente del de los tenants:

- Archivos en `app/helpers/squemadb/master_migrations/NNN_descripcion.sql`.
- `MasterMigrationManager.php` los aplica y lleva el control en la tabla `master_migrations` de la master.
- Se ejecutan desde **Admin → Migraciones → "Migraciones de la BD Master"**.

| # | Archivo | Descripción |
|---|---------|--------------|
| 001 | `001_create_tareas.sql` | Tabla `tareas` (registro de tareas del panel de administración) |

---

## Generación de PDFs

### Servicio: `PdfService.php`

Utiliza **Dompdf** para generar PDFs a partir de templates HTML.

### PDFs Generados

| Tipo | Template | Descripción |
|------|----------|------------|
| Remito de Salida | `views/pdf/remito_salida.php` | Diseño corporativo con logo, "R" legal, texto RG AFIP 1415, tabla de productos, totales, footer fijo. |
| Recibo de Pago | `views/mails/pago.php` | Comprobante de cobro con datos del cliente, monto, medio de pago. |
| Devolución | `views/pdf/devolucion.php` | Comprobante de devolución con items, condiciones, reembolso, color suave (#B03A2E). |

### Almacenamiento

Los archivos son **por tenant**: `empresaStoragePath($tipo)` (helper en `app/config/config.php`) resuelve `storage/empresa_{tenantId}/{$tipo}` y crea el directorio si no existe.

```
storage/empresa_{tenantId}/
├── pagos/pago_{id}.pdf
├── remitos/{YYYY}/{MM}/remito_{id}.pdf
├── devoluciones/devolucion_{numero}.pdf
├── notas_pedido/nota_pedido_{id}.pdf
├── presupuestos/presupuesto_{id}.pdf
├── sdcomp/{YYYY}/{MM}/
└── logs/{YYYY-MM-DD}.log
```

La ruta absoluta se persiste en la columna `pdf_path` de la tabla correspondiente (`remitos_salida`, `notas_pedido`, `presupuestos`).

---

## Envío de Emails

### Servicio: `MailService.php`

Utiliza **PHPMailer** con SMTP (mail.dmtech.com.ar:465 SSL).

### Emails Enviados

| Tipo | Template | Adjunto |
|------|----------|---------|
| Remito de Salida | `views/mails/remito.php` | PDF del remito |
| Pago/Cobro | `views/mails/pago.php` | PDF del recibo |
| Devolución | `views/mails/devolucion.php` | PDF de la devolución |

### Funcionalidades

- BCC automático a dirección de sistema.
- Log de envíos en tabla `mails_log`.
- Soporte para múltiples destinatarios.

---

## Instalación

### Requisitos

- PHP 8.0+
- MariaDB 10.4 (o MySQL 8.0+)
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

## Actualización en el servidor (instalación existente)

Al desplegar código nuevo en un servidor que **ya tiene** la base master y los tenants, el orden es:

1. **Copiar el código** y correr `composer install` si cambiaron las dependencias.
2. **Migraciones de los tenants** (datos de cada empresa): entrar como superadmin →
   **Admin → Migraciones** → "Ejecutar Todas las Migraciones", o "Ejecutar" por tenant.
   Cada tenant lleva su propio `schema_version` (en la master), así que hay que correrlas en **todos**.
3. **Migraciones de la BD master** (instalación: tareas, etc.): en la **misma pantalla** de
   Admin → Migraciones, está la sección **"Migraciones de la BD Master"**. Si hay pendientes
   aparece el botón **"Aplicar N pendiente(s)"** → lo apretás y listo.

No hace falta buscar el `.sql` ni abrir un gestor web: los dos tipos de migración se ejecutan
desde el panel. La de la master además es **auto-aplicable**: al abrir por primera vez
`/admin/tareas` el sistema aplica solo lo pendiente.

---

## Instalación desde cero

Todos los SQL inicial **están en el repo**, no hace falta ningún archivo externo:

| Archivo | Para qué |
|---------|----------|
| `app/helpers/squemadb/create_master.sql` | Crea la BD master `app_master` (empresas, usuarios, tareas) + el usuario superadmin inicial |
| `app/helpers/squemadb/tenant_schema.sql` | Crea la BD de un tenant (40 tablas base) |
| `app/helpers/migrations/003..021_*.sql` | Migraciones incrementales (se aplican después del schema) |
| `app/helpers/squemadb/master_migrations/001_*.sql` | Migraciones del schema master |

### Orden

1. **Master**: correr `create_master.sql` (crea `app_master` con `tenants`, `users`, `user_tenant`, `tareas`, `master_migrations` y el superadmin). La password del superadmin inicial está en el comentario del propio archivo.
2. **Tenant**: crearlo desde el panel (**Admin → Nueva Empresa**): el sistema crea la BD clonando `tenant_schema.sql` y deja el tenant en `schema_version = 0`.
3. **Migraciones del tenant**: **Admin → Migraciones → "Ejecutar"** en ese tenant. Aplica 003..021 y deja `schema_version = 21`.
4. **Migraciones master**: **Admin → Migraciones → "Migraciones de la BD Master" → "Aplicar"**. Aplica la 001 (la tabla `tareas`).

> Instalación nueva: si corriste `create_master.sql` actualizado, la tabla `tareas` ya existe y la migración master 001 queda como no-op (`CREATE TABLE IF NOT EXISTS`).
>
> El tenant nuevo queda **idéntico** a uno migrado: 66 tablas. Verificado.

---

## Backup de las bases

Hay dos formas, con la misma lógica de volcado (`app/services/BackupService.php`):

### 1. Desde el panel (a mano)

Entrar como superadmin → **Admin → Backups**. Tres botones:

| Botón | Qué vuelca |
|-------|------------|
| **Master** | Solo `app_master` (empresas, usuarios, tareas) |
| **Tenants** | Un `.sql` por cada empresa activa, sin la master |
| **Full (todo)** | Master + todos los tenants en una sola carpeta |

Cada respaldo queda en una carpeta propia `erp_YYYYMMDD_HHMM/` (con sufijo `_2`, `_3`… si dos
corren en el mismo segundo). Desde la misma pantalla se descarga cualquier `.sql` o se borra
un respaldo entero.

### 2. Desde la consola (para el cron)

```bash
php app/helpers/backup.php                  # full
php app/helpers/backup.php --master         # solo la master
php app/helpers/backup.php --tenants        # solo los tenants
php app/helpers/backup.php --daily          # full rotativo: deja la ultima copia
php app/helpers/backup.php --daily --keep=7 # full rotativo: deja las 7 ultimas
php app/helpers/backup.php --destino=D:/respaldos   # destino alternativo
```

### Respaldo diario automatico (cron)

- **Windows** (Programador de tareas), una vez al dia:
  ```
  php.exe C:\xampp\htdocs\app\app\helpers\backup.php --daily --keep=14
  ```
- **Linux** (`crontab -e`), a las 2:00:
  ```
  0 2 * * * php /var/www/app/app/helpers/backup.php --daily --keep=14
  ```

**`--keep` es la cantidad de carpetas, no de dias.** Si corrés una vez por dia, `--keep=7`
da una semana. Si corrés dos (p. ej. 06:00 y 21:00), con `--keep=7` solo tenés 3 días y
medio, así que usá `--keep=14` para cubrir la semana.

| Corridas/dia | `--keep` | Historial real |
|---|---|---|
| 1 (21:00) | `--keep=7` | 7 dias |
| 2 (06:00 y 21:00) | `--keep=7` | 3 dias y medio |
| 2 (06:00 y 21:00) | `--keep=14` | 7 dias |

> Con `--keep=1` (default de `--daily`) solo queda la última copia: si la BD se corrompe
> antes de que corra el cron, se respalda la corrupción y se tira la última copia buena.
>
> Y una aclaración de alcance: el cron protege contra **corrupción de datos**, no contra
> **pérdida de la máquina**. Si se rompe el disco se van los dos, así que conviene copiar la
> carpeta de respaldos a otro lado (nube o disco) con algo tan simple como un `robocopy`.

### Cuando cambie el servidor

En producción casi seguro no va a ser la misma ruta. Las dos claves del `.env` que resuelven eso:

| Clave | Para qué | Ejemplo |
|---|---|---|
| `BACKUP_PATH` | Dónde se guardan los respaldos | `BACKUP_PATH=/var/backups/erp` |
| `MYSQLDUMP_PATH` | Ruta absoluta de mysqldump | `MYSQLDUMP_PATH=/usr/bin/mysqldump` |

Si no se definen, se busca `mysqldump` en el `PATH` y la carpeta de respaldos se resuelve sola:
se prueban varios destinos y queda el primero que sea **escribible y esté fuera del webroot**.
Hacen falta varios porque la altura de la raíz del proyecto cambía según el servidor:

| Servidor | Proyecto | Candidato que funciona |
|---|---|---|
| XAMPP | `C:/xampp/htdocs/app` | dos arriba → `C:/xampp/_erp_backups` |
| Hosting | `/home/usuario/app` | **uno arriba** → `/home/usuario/_erp_backups` |

Con un único destino fijo, en uno de los dos servidores falla. **Admin → Backups** chequea el
entorno antes de dejar generar uno y avisa con un mensaje claro si algo falta. Tres problemas
típicos en hosting:

1. **`exec()` deshabilitada** — muy común en hosting compartido. Sin ella no se puede invocar
   a mysqldump; hay que pedir que la habiliten o correr los respaldos por consola.
2. **`mysqldump` no disponible** — resolver con `MYSQLDUMP_PATH`. Ojo: si la clave está mal
   escrita **no se ignora en silencio**, el panel la marca y hay que corregirla.
3. **Destino sin permisos de escritura** — resolver con `BACKUP_PATH` apuntando a una carpeta
   escribible **y fuera del webroot**.

> `BACKUP_PATH` y `MYSQLDUMP_PATH` se pueden poner en el `.env` **o** como variable de entorno
> (la variable manda). Así el cron puede apuntar a otro destino sin tocar el archivo.

### Dónde quedan los archivos

Por defecto, en el primer destino escribible y externo al webroot de la lista de arriba. En XAMPP
eso es `C:\xampp\_erp_backups\`. Es a propósito: si los `.sql` vivieran bajo `C:\xampp\htdocs`,
Apache los serviría como archivos estáticos y quedarían expuestos los hashes de password y los
datos de las empresas. Para comprobarlo, el chequeo usa `DOCUMENT_ROOT` cuando hay una petición
web (es el webroot real, sin adivinar) y el patrón de la app cuando corre por consola.

### Importante

- Los `.sql` reconstruyen todo, incluido el `schema_version` de cada tenant.
- `storage/` (PDFs) y `public/uploads/` (imágenes) **no están en MySQL**: respaldarlos aparte.
- Ojo con el shell: en **PowerShell 5.1** el `>` escribe **UTF-16** y deja el dump ilegible.
  Por eso se usa `--result-file` de mysqldump. Si generás dumps a mano, usá siempre
  `mysqldump --result-file=dump.sql` y no `> dump.sql`.

### Restaurar

```bash
mysql -u root -e "CREATE DATABASE app_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root app_master < app_master.sql
mysql -u root tenant_xxx  < tenant_xxx.sql
```

> Instalación nueva: `create_master.sql` ya incluye las tablas de la master (incluida `tareas`),
> y la migración master 001 queda como no-op (`CREATE TABLE IF NOT EXISTS`).

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

# Opcional. Carpeta donde se guardan los respaldos (Admin -> Backups y el cron).
# Si no esta, se prueba una lista de destinos y queda el primero escribible y
# FUERA del webroot: en XAMPP C:\xampp\_erp_backups, en hosting algo tipo
# /home/usuario/_erp_backups. Conviene ponerlo explicito en produccion.
# Tambien puede ir como variable de entorno (manda sobre el .env).
# BACKUP_PATH=/home/usuario/_erp_backups

# Opcional. Ruta absoluta de mysqldump si no esta en el PATH (necesario en
# hosting de Linux). En Windows con XAMPP se detecta solo. Si la clave esta mal
# escrita, Admin -> Backups la marca en vez de ignorarla en silencio.
# MYSQLDUMP_PATH=/usr/bin/mysqldump
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

| Tarea | Cómo se hace |
|---------|------------|
| Aplicar migraciones SQL pendientes | Panel web: **Admin → Migraciones** (`/admin/migrations`), por tenant (`/admin/migrations-run/{id}`) o para todos (`/admin/migrations-run-all`) |
| Agregar un cambio de schema | Crear `app/helpers/migrations/NNN_descripcion.sql` y correrlo desde el panel en **cada** tenant |
| Cambios rápidos de DB | Editar `app/config/database.php` (lee `.env`) o usar PHP/PDO. No hay cliente `mysql` en el PATH |
| Ver logs | `storage/empresa_{tenantId}/logs/YYYY-MM-DD.log` (app) y `error_log` en la raíz (PHP/Apache) |

> Los scripts PHP sueltos de `app/migrations/` (carpeta gitignored) son **legacy**: dependen de la constante `BASE_PATH`, que solo define `app/bootstrap.php`, así que no se pueden ejecutar con `php app/migrations/x.php` de forma directa. Usar el panel de migraciones.

---

## Notas de Desarrollo

- El sistema usa **event-sourcing para stock**: el stock actual se calcula sumando movimientos, no se almacena como valor estático.
- Los **asientos contables** se generan automáticamente para cobros, pagos, remitos y devoluciones.
- El **cliente ocasional** (id=9999) permite registrar ventas sin cliente registrado en el ABM.
- Los **remitos manuales** buscan productos por AJAX con autocompletado.
- El módulo **SDCOMP** usa nomenclatura genérica ("AJUSTE") para no revelar la naturaleza de los movimientos.
- Los **PDFs** incluyen diseño corporativo con logo de la empresa y texto legal argentino (RG AFIP 1415).
- **Devoluciones**: Toda validación de cantidad, precio y saldo de caja es server-side con `FOR UPDATE` en transacción. El precio se fuerza desde el remito (no se acepta del cliente). La distribución de devoluciones en ventas no cobradas es FIFO para no perder excesos.
- **Depósitos**: Stock desglosado por depósito físico. Cada movimiento de stock se asocia a `deposito_id`. Los depósitos con `es_transito=1` son virtuales (Ej: "En Tránsito" para transferencias).
- **Lotes y Vencimientos**: La tabla `lotes` registra códigos de lote con fecha de vencimiento y producción, vinculados a productos o materias primas. El stock se consulta por lote mediante filtro en `movimientos_stock`.
- **Transferencias entre Depósitos**: Flujo en dos fases: 1) salida de origen + entrada a "En Tránsito" (estado `EN_TRANSITO`), 2) confirmación de recepción + entrada a destino (estado `COMPLETADA`). Anulación revierte según estado.
