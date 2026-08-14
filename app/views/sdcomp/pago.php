<?php $esVenta = $mov['tipo'] === 'VENTA'; ?>

<h4><i class="bi bi-cash"></i> <?= $esVenta ? 'Registrar Cobro' : 'Registrar Pago' ?> - Comprobante #<?= $mov['id'] ?></h4>
<hr>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:140px">Tipo</td><td><span class="badge <?= $esVenta ? 'bg-info' : 'bg-warning text-dark' ?>"><?= $esVenta ? 'Salida' : 'Entrada' ?></span></td></tr>
                    <tr><td class="text-muted">Cliente/Prov.</td><td><?= htmlspecialchars($mov['razon_social'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Monto Total</td><td class="fw-bold">$ <?= number_format($mov['monto_total'], 2, ',', '.') ?></td></tr>
                    <tr><td class="text-muted">Saldo Pendiente</td><td class="text-danger fw-bold">$ <?= number_format($mov['saldo_pendiente'], 2, ',', '.') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<form method="post" class="row g-3">
    <div class="col-md-4">
        <label class="form-label"><?= $esVenta ? 'Monto a cobrar' : 'Monto a pagar' ?></label>
        <input type="number" name="monto" class="form-control" step="0.01" min="0.01"
               max="<?= $mov['saldo_pendiente'] ?>" value="<?= $mov['saldo_pendiente'] ?>" required>
        <div class="form-text">Maximo: $ <?= number_format($mov['saldo_pendiente'], 2, ',', '.') ?></div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Descripcion (opcional)</label>
        <input type="text" name="descripcion" class="form-control" placeholder="Ej: Efectivo, Transferencia, etc.">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-check-lg"></i> Guardar
        </button>
    </div>
    <div class="col-12">
        <a href="<?= BASE_URL ?>/sdcomp/show/<?= $mov['id'] ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
