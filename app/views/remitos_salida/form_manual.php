<?php
$clientes = $clientes ?? [];
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/remitossalida" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Remitos
    </a>
</div>

<h3><i class="bi bi-truck"></i> Nuevo Remito Manual (sin NP)</h3>

<form method="POST" action="<?= BASE_URL ?>/remitossalida/store-manual" id="formRemitoManual" class="mt-3">

    <!-- Sección: Datos del Cliente -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person"></i> Datos del Cliente</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Tipo de Cliente</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipo_cliente" id="tipoExistente" value="existente" checked>
                        <label class="form-check-label" for="tipoExistente">Cliente existente</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipo_cliente" id="tipoOcasional" value="ocasional">
                        <label class="form-check-label" for="tipoOcasional">Cliente ocasional (sin cargar)</label>
                    </div>
                </div>
            </div>

            <!-- Cliente existente -->
            <div id="clienteExistente">
                <div class="row">
                    <div class="col-md-12">
                        <label for="cliente_id" class="form-label">Seleccionar Cliente *</label>
                        <select id="cliente_id" name="cliente_id" class="form-select">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?> <?= !empty($c['cuit']) ? '(CUIT: ' . $c['cuit'] . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Cliente ocasional (campos manuales) -->
            <div id="clienteOcasional" style="display:none;">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="cliente_nombre" class="form-label">Nombre / Razón Social *</label>
                        <input type="text" id="cliente_nombre" name="cliente_nombre" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="cliente_cuit" class="form-label">CUIT</label>
                        <input type="text" id="cliente_cuit" name="cliente_cuit" class="form-control" maxlength="20">
                        <div class="form-text">Opcional - Consumidor Final</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="cliente_localidad" class="form-label">Localidad</label>
                        <input type="text" id="cliente_localidad" name="cliente_localidad" class="form-control" maxlength="100">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="cliente_direccion" class="form-label">Dirección</label>
                        <input type="text" id="cliente_direccion" name="cliente_direccion" class="form-control" maxlength="255">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cliente_email" class="form-label">Email</label>
                        <input type="email" id="cliente_email" name="cliente_email" class="form-control" maxlength="150">
                        <div class="form-text">Si se deja vacío, se enviará a contacto@alimentostriba.com.ar</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cliente_telefono" class="form-label">Teléfono</label>
                        <input type="text" id="cliente_telefono" name="cliente_telefono" class="form-control" maxlength="50">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección: Productos -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-box"></i> Productos a Remitar</h5>
            <div>
                <button type="button" class="btn btn-sm btn-primary me-1" onclick="abrirModalProductos()">
                    <i class="bi bi-search"></i> Buscar Producto
                </button>
                <button type="button" class="btn btn-sm btn-success" onclick="agregarItemManual()">
                    <i class="bi bi-plus-lg"></i> Agregar Servicio
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="sinProductos" class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                <p class="mt-2">No hay items seleccionados. Haga clic en "Buscar Producto" o "Agregar Servicio".</p>
            </div>
            <div class="table-responsive" id="contenedorTabla" style="display:none;">
                <table class="table table-striped" id="tablaProductos">
                    <thead class="table-dark">
                        <tr>
                            <th>Concepto</th>
                            <th class="text-end">Tipo</th>
                            <th class="text-end">Precio U.</th>
                            <th>Stock</th>
                            <th width="120">Cantidad</th>
                            <th class="text-end">Subtotal</th>
                            <th width="60"></th>
                        </tr>
                    </thead>
                    <tbody id="productosBody">
                    </tbody>
                    <tfoot>
                        <tr class="table-success fw-bold">
                            <td colspan="5" class="text-end">TOTAL:</td>
                            <td class="text-end" id="totalGeneral">$ 0,00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal para agregar item manual (servicio) -->
    <div class="modal fade" id="modalItemManual" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar Servicio/Concepto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" id="descripcionManual" class="form-control" placeholder="Ej: Servicio de instalación, Envío, etc.">
                    </div>
                    <div class="row">
                        <div class="col">
                            <label class="form-label">Cantidad</label>
                            <input type="number" id="cantidadManual" class="form-control" step="0.01" min="0.01" value="1">
                        </div>
                        <div class="col">
                            <label class="form-label">Precio Unitario ($)</label>
                            <input type="number" id="precioManual" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarItemManual()">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Observaciones -->
    <div class="mb-3">
        <label for="observaciones" class="form-label">Observaciones</label>
        <textarea id="observaciones" name="observaciones" class="form-control" rows="3"
                  placeholder="Notas adicionales del remito..."></textarea>
    </div>

    <!-- Productos seleccionados (hidden inputs) -->
    <div id="productosHidden"></div>

    <!-- Botones -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success" id="btnSubmit">
            <i class="bi bi-check-lg"></i> Crear Remito
        </button>
        <a href="<?= BASE_URL ?>/remitossalida" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<!-- Modal de búsqueda de productos -->
<div class="modal fade" id="modalProductos" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-search"></i> Buscar Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-3">
            <input type="text" id="buscadorProducto" class="form-control form-control-lg"
                   placeholder="Buscar por nombre, SKU o codigo de barra..." autofocus>
        </div>
        <div id="resultadosBusqueda" class="list-group" style="max-height: 400px; overflow-y: auto;">
            <div class="text-center text-muted py-3">Escriba al menos 2 caracteres para buscar</div>
        </div>
    </div>
</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formRemitoManual');
    const tipoExistente = document.getElementById('tipoExistente');
    const tipoOcasional = document.getElementById('tipoOcasional');
    const divExistente = document.getElementById('clienteExistente');
    const divOcasional = document.getElementById('clienteOcasional');
    const selectCliente = document.getElementById('cliente_id');
    const tbody = document.getElementById('productosBody');
    const sinProductos = document.getElementById('sinProductos');
    const contenedorTabla = document.getElementById('contenedorTabla');
    const productosHidden = document.getElementById('productosHidden');
    const buscador = document.getElementById('buscadorProducto');
    const resultados = document.getElementById('resultadosBusqueda');
    const totalGeneralEl = document.getElementById('totalGeneral');

    // Productos seleccionados: {id: {id, nombre, sku, precio, stock, cantidad}}
    let productosSeleccionados = {};
    // Items manuales (servicios sin producto): array de {descripcion, cantidad, precio}
    let itemsManuales = [];

    function formatMoney(val) {
        return '$ ' + val.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Toggle tipo cliente
    function toggleTipoCliente() {
        if (tipoExistente.checked) {
            divExistente.style.display = 'block';
            divOcasional.style.display = 'none';
            selectCliente.required = true;
            document.getElementById('cliente_nombre').required = false;
        } else {
            divExistente.style.display = 'none';
            divOcasional.style.display = 'block';
            selectCliente.required = false;
            selectCliente.value = '';
            document.getElementById('cliente_nombre').required = true;
        }
    }
    tipoExistente.addEventListener('change', toggleTipoCliente);
    tipoOcasional.addEventListener('change', toggleTipoCliente);

    // Buscar productos via AJAX
    let busquedaTimeout;
    buscador.addEventListener('input', function() {
        clearTimeout(busquedaTimeout);
        const q = this.value.trim();
        if (q.length < 2) {
            resultados.innerHTML = '<div class="text-center text-muted py-3">Escriba al menos 2 caracteres para buscar</div>';
            return;
        }
        busquedaTimeout = setTimeout(function() {
            fetch('<?= BASE_URL ?>/remitossalida/search-products?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (data.length === 0) {
                        resultados.innerHTML = '<div class="text-center text-muted py-3">No se encontraron productos</div>';
                        return;
                    }
                    let html = '';
                    data.forEach(function(p) {
                        const precio = parseFloat(p.precio_venta) || 0;
                        const stock = parseFloat(p.stock) || 0;
                        const sinStock = stock <= 0;
                        const yaSeleccionado = productosSeleccionados[p.id];
                        const codigosBarra = p.codigos_barra ? ' <small class="text-muted">(CB: ' + escHtml(p.codigos_barra) + ')</small>' : '';

                        let itemClass = 'list-group-item list-group-item-action';
                        let badgeHtml = '';
                        let stockBadge = '';

                        if (sinStock) {
                            itemClass += ' border-danger';
                            badgeHtml = '<span class="badge bg-danger ms-2">Sin Stock</span>';
                            stockBadge = '<span class="badge bg-danger">Stock: 0</span>';
                        } else {
                            if (yaSeleccionado) {
                                itemClass += ' list-group-item-secondary';
                                badgeHtml = '<span class="badge bg-warning ms-2">Ya agregado</span>';
                            }
                            stockBadge = '<span class="badge bg-success">Stock: ' + stock.toFixed(2) + '</span>';
                        }

                        const nameStyle = sinStock ? ' style="text-decoration: line-through; color: #dc3545;"' : '';
                        const precioStyle = sinStock ? ' style="color: #dc3545;"' : ' style="color: #198754;"';

                        html += '<a href="#" class="' + itemClass + '" '
                            + 'data-id="' + p.id + '" data-nombre="' + escHtml(p.nombre) + '" '
                            + 'data-sku="' + escHtml(p.sku || '') + '" data-stock="' + stock + '" '
                            + 'data-precio="' + precio + '"'
                            + (sinStock ? ' style="pointer-events: none; opacity: 0.7;"' : '') + '>'
                            + '<div class="d-flex justify-content-between align-items-center">'
                            + '<div><strong' + nameStyle + '>' + escHtml(p.nombre) + '</strong>'
                            + (p.sku ? ' <small class="text-muted">(SKU: ' + escHtml(p.sku) + ')</small>' : '')
                            + codigosBarra
                            + ' <span class="fw-bold"' + precioStyle + '>' + formatMoney(precio) + '</span>'
                            + badgeHtml + '</div>'
                            + stockBadge
                            + '</div></a>';
                    });
                    resultados.innerHTML = html;

                    // Click en resultado
                    resultados.querySelectorAll('.list-group-item-action').forEach(function(el) {
                        el.addEventListener('click', function(e) {
                            e.preventDefault();
                            const id = this.dataset.id;
                            if (productosSeleccionados[id]) return;
                            agregarProducto(id, this.dataset.nombre, this.dataset.sku,
                                parseFloat(this.dataset.stock), parseFloat(this.dataset.precio));
                            bootstrap.Modal.getInstance(document.getElementById('modalProductos')).hide();
                            buscador.value = '';
                            resultados.innerHTML = '';
                        });
                    });
                });
        }, 300);
    });

    // Agregar producto a la tabla
    function agregarProducto(id, nombre, sku, stock, precio) {
        if (productosSeleccionados[id]) return;
        productosSeleccionados[id] = {
            id: id,
            nombre: nombre,
            sku: sku,
            stock: stock,
            precio: precio,
            cantidad: 1
        };
        renderTabla();
    }

    // Quitar producto de la tabla
    window.quitarProducto = function(id) {
        delete productosSeleccionados[id];
        renderTabla();
    };

    // Actualizar cantidad (sin reconstruir tabla)
    window.actualizarCantidad = function(id, valor) {
        const cant = parseFloat(valor) || 0;
        if (productosSeleccionados[id]) {
            productosSeleccionados[id].cantidad = cant;
            actualizarFilaYTotal(id);
        }
    };

    // Actualizar precio unitario (sin reconstruir tabla)
    window.actualizarPrecio = function(id, valor) {
        const precio = parseFloat(valor) || 0;
        if (productosSeleccionados[id]) {
            productosSeleccionados[id].precio = precio;
            actualizarFilaYTotal(id);
        }
    };

    // Actualiza solo la celda subtotal de una fila y el total general
    function actualizarFilaYTotal(id) {
        const p = productosSeleccionados[id];
        const subtotal = p.precio * p.cantidad;
        const subtotalCell = document.getElementById('subtotal-' + id);
        if (subtotalCell) subtotalCell.textContent = formatMoney(subtotal);
        let totalGeneral = 0;
        Object.keys(productosSeleccionados).forEach(function(k) {
            const pp = productosSeleccionados[k];
            totalGeneral += pp.precio * pp.cantidad;
        });
        totalGeneralEl.textContent = formatMoney(totalGeneral);
        updateHiddenInputs();
    }

    // Helper functions para items manuales
    window.actualizarPrecioManual = function(manualId, valor) {
        const idx = manualId.replace('manual_', '');
        const precio = parseFloat(valor) || 0;
        if (itemsManuales[idx] !== undefined) {
            itemsManuales[idx].precio = precio;
            actualizarTotalManual();
        }
    };

    window.actualizarCantidadManual = function(manualId, valor) {
        const idx = manualId.replace('manual_', '');
        const cant = parseFloat(valor) || 0;
        if (itemsManuales[idx] !== undefined) {
            itemsManuales[idx].cantidad = cant;
            actualizarTotalManual();
        }
    };

    window.quitarManual = function(manualId) {
        const idx = manualId.replace('manual_', '');
        itemsManuales.splice(idx, 1);
        renderTabla();
    };

    function actualizarTotalManual() {
        let totalGeneral = 0;
        Object.keys(productosSeleccionados).forEach(function(k) {
            const pp = productosSeleccionados[k];
            totalGeneral += pp.precio * pp.cantidad;
        });
        itemsManuales.forEach(function(m) {
            totalGeneral += m.precio * m.cantidad;
        });
        totalGeneralEl.textContent = formatMoney(totalGeneral);
        updateHiddenInputs();
    }

    // Renderizar tabla (productos + items manuales)
    function renderTabla() {
        const ids = Object.keys(productosSeleccionados);
        const manuales = itemsManuales.filter(m => m.cantidad > 0);
        
        if (ids.length === 0 && manuales.length === 0) {
            sinProductos.style.display = 'block';
            contenedorTabla.style.display = 'none';
            productosHidden.innerHTML = '';
            totalGeneralEl.textContent = formatMoney(0);
            return;
        }

        sinProductos.style.display = 'none';
        contenedorTabla.style.display = 'block';

        let html = '';
        let totalGeneral = 0;

        // Productos normales
        ids.forEach(function(id) {
            const p = productosSeleccionados[id];
            const subtotal = p.precio * p.cantidad;
            totalGeneral += subtotal;

            html += '<tr>'
                + '<td><strong>' + escHtml(p.nombre) + '</strong>'
                + (p.sku ? ' <small class="text-muted">(SKU: ' + escHtml(p.sku) + ')</small>' : '') + '</td>'
                + '<td class="text-center"><span class="badge bg-info">PRODUCTO</span></td>'
                + '<td><input type="number" step="0.01" min="0" '
                + 'value="' + p.precio + '" class="form-control form-control-sm text-end" '
                + 'onchange="actualizarPrecio(' + id + ', this.value)" '
                + 'oninput="actualizarPrecio(' + id + ', this.value)"></td>'
                + '<td>' + parseFloat(p.stock).toFixed(2) + '</td>'
                + '<td><input type="number" step="0.01" min="0.01" max="' + p.stock + '" '
                + 'value="' + p.cantidad + '" class="form-control form-control-sm" '
                + 'onchange="actualizarCantidad(' + id + ', this.value)" '
                + 'oninput="actualizarCantidad(' + id + ', this.value)"></td>'
                + '<td class="text-end fw-bold" id="subtotal-' + id + '">' + formatMoney(subtotal) + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger" '
                + 'onclick="quitarProducto(' + id + ')"><i class="bi bi-x-lg"></i></button></td>'
                + '</tr>';
        });

        // Items manuales (servicios)
        manuales.forEach(function(m, index) {
            const subtotal = m.precio * m.cantidad;
            totalGeneral += subtotal;
            const manualId = 'manual_' + index;

            html += '<tr>'
                + '<td><strong>' + escHtml(m.descripcion) + '</strong>'
                + ' <small class="text-muted">(Servicio)</small></td>'
                + '<td class="text-center"><span class="badge bg-warning">MANUAL</span></td>'
                + '<td><input type="number" step="0.01" min="0" '
                + 'value="' + m.precio + '" class="form-control form-control-sm text-end" '
                + 'onchange="actualizarPrecioManual(' + manualId + ', this.value)" '
                + 'oninput="actualizarPrecioManual(' + manualId + ', this.value)"></td>'
                + '<td class="text-center">-</td>'
                + '<td><input type="number" step="0.01" min="0.01" '
                + 'value="' + m.cantidad + '" class="form-control form-control-sm" '
                + 'onchange="actualizarCantidadManual(' + manualId + ', this.value)" '
                + 'oninput="actualizarCantidadManual(' + manualId + ', this.value)"></td>'
                + '<td class="text-end fw-bold" id="subtotal-' + manualId + '">' + formatMoney(subtotal) + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger" '
                + 'onclick="quitarManual(' + manualId + ')"><i class="bi bi-x-lg"></i></button></td>'
                + '</tr>';
        });

        tbody.innerHTML = html;
        totalGeneralEl.textContent = formatMoney(totalGeneral);
        updateHiddenInputs();
    }

    // Actualizar hidden inputs para el submit
    function updateHiddenInputs() {
        let html = '';

        // Productos desde busqueda
        Object.keys(productosSeleccionados).forEach(function(id) {
            const p = productosSeleccionados[id];
            if (p.cantidad > 0) {
                html += '<input type="hidden" name="items[' + id + '][producto_id]" value="' + p.id + '">';
                html += '<input type="hidden" name="items[' + id + '][nombre]" value="' + escHtml(p.nombre) + '">';
                html += '<input type="hidden" name="items[' + id + '][sku]" value="' + escHtml(p.sku) + '">';
                html += '<input type="hidden" name="items[' + id + '][cantidad]" value="' + p.cantidad + '">';
                html += '<input type="hidden" name="items[' + id + '][precio]" value="' + p.precio + '">';
            }
        });

        // Items manuales (servicios)
        itemsManuales.forEach(function(m) {
            if (m.cantidad > 0) {
                html += '<input type="hidden" name="items_manual[]" value="' + btoa(m.descripcion + '|' + m.cantidad + '|' + m.precio) + '">';
            }
        });

        productosHidden.innerHTML = html;
    }

    // Utilidad: escapar HTML
    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Abrir modal de búsqueda
    window.abrirModalProductos = function() {
        new bootstrap.Modal(document.getElementById('modalProductos')).show();
        setTimeout(function() { buscador.focus(); }, 300);
    };

    // Validar antes de enviar
    form.addEventListener('submit', function(e) {
        const ids = Object.keys(productosSeleccionados);
        const manuales = itemsManuales.filter(m => m.cantidad > 0);

        let tieneProductos = false;
        ids.forEach(function(id) {
            if (productosSeleccionados[id].cantidad > 0) {
                tieneProductos = true;
            }
        });

        if (!tieneProductos && manuales.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Sin items',
                text: 'Debe agregar al menos un producto o servicio para remitar.'
            });
            return false;
        }

        // Validar cantidades vs stock solo para productos (no para manuales)
        let errores = [];
        ids.forEach(function(id) {
            const p = productosSeleccionados[id];
            if (p.cantidad > p.stock) {
                errores.push(p.nombre + ' (Stock: ' + p.stock + ', Solicitado: ' + p.cantidad + ')');
            }
        });

        if (errores.length > 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Stock insuficiente',
                html: 'Los siguientes productos exceden el stock:<br><br>' + errores.join('<br>')
            });
            return false;
        }

        // Confirmar
        e.preventDefault();
        let textoConfirmacion = 'Se descontará el stock de los productos seleccionados.';
        if (manuales.length > 0) {
            textoConfirmacion += ' Se agregarán ' + manuales.length + (manuales.length === 1 ? ' servicio' : ' servicios') + '.';
        }

        Swal.fire({
            title: '¿Crear remito manual?',
            text: textoConfirmacion,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Sí, crear remito'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Funciones para items manuales (servicios sin producto)
    window.agregarItemManual = function() {
        const modal = new bootstrap.Modal(document.getElementById('modalItemManual'));
        modal.show();
    };

    window.guardarItemManual = function() {
        const descripcion = document.getElementById('descripcionManual').value.trim();
        const cantidad = parseFloat(document.getElementById('cantidadManual').value) || 0;
        const precio = parseFloat(document.getElementById('precioManual').value) || 0;

        if (!descripcion) {
            Swal.fire('Error', 'La descripción es obligatoria', 'error');
            return;
        }

        const idManual = 'manual_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);

        itemsManuales.push({
            id: idManual,
            descripcion: descripcion,
            cantidad: cantidad,
            precio: precio
        });

        document.getElementById('descripcionManual').value = '';
        document.getElementById('cantidadManual').value = 1;
        document.getElementById('precioManual').value = 0;

        const modalEl = document.getElementById('modalItemManual');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        renderTabla();
    };

    toggleTipoCliente();
});
</script>
