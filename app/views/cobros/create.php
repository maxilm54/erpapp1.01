<?php
$clientes = $clientes ?? [];
$cajas = $cajas ?? [];
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/cobros" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cobros
    </a>
</div>

<h3><i class="bi bi-cash-coin"></i> Nuevo Cobro</h3>

<form method="POST" action="<?= BASE_URL ?>/cobros/store" id="formCobro" class="mt-3">

    <!-- Sección: Cliente -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person"></i> Cliente</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <label for="cliente_id" class="form-label fw-bold">Seleccionar Cliente *</label>
                    <select id="cliente_id" name="cliente_id" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?> <?= !empty($c['cuit']) ? '(CUIT: ' . $c['cuit'] . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Clientes ocasionales con deuda -->
            <?php if (!empty($ocasionales)): ?>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Clientes Ocasionales con Deuda</label>
                    <select id="cliente_occasional" class="form-select">
                        <option value="">Seleccionar cliente ocasional...</option>
                        <?php foreach ($ocasionales as $occ): ?>
                            <option value="<?= htmlspecialchars($occ['cliente_nombre']) ?>"
                                    data-debito="<?= $occ['total_debito'] ?>"
                                    data-credito="<?= $occ['total_credito'] ?>"
                                    data-saldo="<?= $occ['saldo'] ?>">
                                <?= htmlspecialchars($occ['cliente_nombre']) ?> — Saldo: $ <?= number_format((float)$occ['saldo'], 2, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <!-- Campo oculto para el nombre del cliente ocasional -->
            <input type="hidden" id="cliente_nombre" name="cliente_nombre" value="">
        </div>
    </div>

    <!-- Sección: Deudas del cliente -->
    <div class="card mb-4" id="cardDeudas" style="display:none;">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Deuda del Cliente</h5>
        </div>
        <div class="card-body">
            <div id="deudasInfo" class="alert alert-warning mb-0">
                <div class="row text-center">
                    <div class="col-md-4">
                        <strong>Debito Total:</strong><br>
                        <span id="deudasDebito" class="fs-5">$ 0,00</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Credito Total:</strong><br>
                        <span id="deudasCredito" class="fs-5">$ 0,00</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Saldo:</strong><br>
                        <span id="deudasSaldo" class="fs-5 fw-bold">$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección: Datos del cobro -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-cash"></i> Datos del Cobro</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="monto" class="form-label fw-bold">Monto *</label>
                    <input type="number" step="0.01" min="0.01" id="monto" name="monto" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label for="medio_pago" class="form-label fw-bold">Medio de Pago *</label>
                    <select id="medio_pago" name="medio_pago" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Tarjeta">Tarjeta</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="caja_banco_id" class="form-label fw-bold">Caja / Banco</label>
                    <select id="caja_banco_id" name="caja_banco_id" class="form-select">
                        <option value="">Sin asignar</option>
                        <?php foreach ($cajas as $cb): ?>
                            <option value="<?= $cb['id'] ?>"><?= htmlspecialchars($cb['nombre']) ?> (Saldo: $ <?= number_format((float)$cb['saldo_actual'], 2, ',', '.') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control" rows="2"
                              placeholder="Notas del cobro..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success" id="btnSubmit">
            <i class="bi bi-check-lg"></i> Registrar Cobro
        </button>
        <a href="<?= BASE_URL ?>/cobros" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectCliente = document.getElementById('cliente_id');
    const selectOcasional = document.getElementById('cliente_occasional');
    const clienteNombreInput = document.getElementById('cliente_nombre');
    const cardDeudas = document.getElementById('cardDeudas');
    const deudasDebito = document.getElementById('deudasDebito');
    const deudasCredito = document.getElementById('deudasCredito');
    const deudasSaldo = document.getElementById('deudasSaldo');
    const montoInput = document.getElementById('monto');
    const form = document.getElementById('formCobro');

    function formatMoney(val) {
        return '$ ' + val.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function mostrarDeudas(debito, credito, saldo) {
        deudasDebito.textContent = formatMoney(debito);
        deudasCredito.textContent = formatMoney(credito);
        deudasSaldo.textContent = formatMoney(saldo);
        if (saldo > 0) {
            montoInput.value = saldo.toFixed(2);
        }
        cardDeudas.style.display = 'block';
    }

    // Al cambiar cliente registrado
    selectCliente.addEventListener('change', function() {
        const clienteId = this.value;
        if (!clienteId) {
            cardDeudas.style.display = 'none';
            return;
        }
        // Deseleccionar ocasional
        if (selectOcasional) selectOcasional.value = '';
        clienteNombreInput.value = this.value ? '' : '';

        fetch('<?= BASE_URL ?>/cobros/deudas/' + clienteId)
            .then(r => r.json())
            .then(data => {
                if (data && data.length > 0) {
                    const d = data[0];
                    mostrarDeudas(
                        parseFloat(d.Debito) || 0,
                        parseFloat(d.Credito) || 0,
                        parseFloat(d.saldo) || 0
                    );
                } else {
                    cardDeudas.style.display = 'none';
                }
            })
            .catch(() => {
                cardDeudas.style.display = 'none';
            });
    });

    // Al cambiar cliente ocasional
    if (selectOcasional) {
        selectOcasional.addEventListener('change', function() {
            const nombre = this.value;
            if (!nombre) {
                cardDeudas.style.display = 'none';
                selectCliente.value = '';
                clienteNombreInput.value = '';
                return;
            }
            // Setear cliente_id = 9999 (OCASIONAL) en el select de arriba
            selectCliente.value = '9999';
            clienteNombreInput.value = nombre;

            // Usar datos del option
            const debito = parseFloat(this.selectedOptions[0].dataset.debito) || 0;
            const credito = parseFloat(this.selectedOptions[0].dataset.credito) || 0;
            const saldo = parseFloat(this.selectedOptions[0].dataset.saldo) || 0;
            mostrarDeudas(debito, credito, saldo);
        });
    }

    // Validar antes de enviar
    form.addEventListener('submit', function(e) {
        const monto = parseFloat(montoInput.value) || 0;
        if (monto <= 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Monto inválido',
                text: 'El monto debe ser mayor a cero.'
            });
            return false;
        }

        e.preventDefault();
        Swal.fire({
            title: '¿Registrar cobro?',
            text: 'Se registrará el cobro y se generará el comprobante.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Sí, registrar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
