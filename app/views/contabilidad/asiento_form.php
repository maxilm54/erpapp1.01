<div class="mb-3">
    <a href="<?= BASE_URL ?>/contabilidad/asientos" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver al Libro Diario
    </a>
</div>

<h3><i class="bi bi-journal-text"></i> <?= htmlspecialchars($title) ?></h3>

<div class="row mt-3">
    <div class="col-md-8">
        <form method="POST" action="<?= BASE_URL ?>/contabilidad/asientos/create" id="formAsiento">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="row mb-3">
                <div class="col-md-2">
                    <label class="form-label">N° Asiento</label>
                    <input type="text" class="form-control" value="#<?= $proximoNumero ?>" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="OPERACION">Operación</option>
                        <option value="APERTURA">Apertura</option>
                        <option value="AJUSTE">Ajuste</option>
                        <option value="CIERRE">Cierre</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Descripción *</label>
                    <input type="text" name="descripcion" class="form-control" required
                           placeholder="Descripción del asiento...">
                </div>
            </div>

            <!-- Líneas del asiento -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Líneas del Asiento (Debe / Haber)</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLinea">
                        <i class="bi bi-plus"></i> Agregar línea
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0" id="tablaLineas">
                        <thead class="table-light">
                            <tr>
                                <th style="width:45%">Cuenta</th>
                                <th style="width:20%">Debe</th>
                                <th style="width:20%">Haber</th>
                                <th style="width:15%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="cuenta_id[]" class="form-select form-select-sm" required>
                                        <option value="">Seleccionar cuenta...</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> - <?= htmlspecialchars($c['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="debe[]" class="form-control form-control-sm" step="0.01" min="0" value="0"></td>
                                <td><input type="number" name="haber[]" class="form-control form-control-sm" step="0.01" min="0" value="0"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btnRemove"><i class="bi bi-x"></i></button></td>
                            </tr>
                            <tr>
                                <td>
                                    <select name="cuenta_id[]" class="form-select form-select-sm" required>
                                        <option value="">Seleccionar cuenta...</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> - <?= htmlspecialchars($c['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="debe[]" class="form-control form-control-sm" step="0.01" min="0" value="0"></td>
                                <td><input type="number" name="haber[]" class="form-control form-control-sm" step="0.01" min="0" value="0"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btnRemove"><i class="bi bi-x"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <span><strong>Total Debe:</strong> <span id="totalDebe">$ 0,00</span></span>
                        <span><strong>Total Haber:</strong> <span id="totalHaber">$ 0,00</span></span>
                        <span id="balanceBadge" class="badge bg-secondary">Sin balancear</span>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2" placeholder="Notas adicionales..."></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="btnSubmit">
                    <i class="bi bi-check-lg"></i> Registrar Asiento
                </button>
                <a href="<?= BASE_URL ?>/contabilidad/asientos" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <div class="col-md-4">
        <div class="card bg-light">
            <div class="card-body">
                <h6>Reglas de Partida Doble</h6>
                <ul class="list-unstyled small">
                    <li><i class="bi bi-check-circle text-success"></i> Todo asiento debe tener al menos 2 líneas</li>
                    <li><i class="bi bi-check-circle text-success"></i> Total Debe = Total Haber</li>
                    <li><i class="bi bi-check-circle text-success"></i> No se permiten montos en cero</li>
                    <li><i class="bi bi-check-circle text-success"></i> Cada línea va a una cuenta diferente</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('#tablaLineas tbody');
    const btnAdd = document.getElementById('btnAddLinea');
    const form = document.getElementById('formAsiento');

    function lineaTemplate() {
        let options = '<option value="">Seleccionar cuenta...</option>';
        <?php foreach ($cuentas as $c): ?>
        options += '<option value="<?= $c["id"] ?>"><?= $c["codigo"] ?> - <?= addslashes(htmlspecialchars($c["nombre"])) ?></option>';
        <?php endforeach; ?>

        return `<tr>
            <td><select name="cuenta_id[]" class="form-select form-select-sm" required>${options}</select></td>
            <td><input type="number" name="debe[]" class="form-control form-control-sm" step="0.01" min="0" value="0"></td>
            <td><input type="number" name="haber[]" class="form-control form-control-sm" step="0.01" min="0" value="0"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btnRemove"><i class="bi bi-x"></i></button></td>
        </tr>`;
    }

    btnAdd.addEventListener('click', function() {
        tbody.insertAdjacentHTML('beforeend', lineaTemplate());
    });

    tbody.addEventListener('click', function(e) {
        if (e.target.closest('.btnRemove')) {
            const rows = tbody.querySelectorAll('tr');
            if (rows.length > 2) {
                e.target.closest('tr').remove();
                calcularTotales();
            }
        }
    });

    tbody.addEventListener('input', calcularTotales);

    function calcularTotales() {
        let debe = 0, haber = 0;
        document.querySelectorAll('input[name="debe[]"]').forEach(el => debe += parseFloat(el.value) || 0);
        document.querySelectorAll('input[name="haber[]"]').forEach(el => haber += parseFloat(el.value) || 0);

        document.getElementById('totalDebe').textContent = '$ ' + debe.toLocaleString('es-AR', {minimumFractionDigits:2});
        document.getElementById('totalHaber').textContent = '$ ' + haber.toLocaleString('es-AR', {minimumFractionDigits:2});

        const badge = document.getElementById('balanceBadge');
        if (Math.abs(debe - haber) < 0.01 && debe > 0) {
            badge.textContent = 'Balanceado';
            badge.className = 'badge bg-success';
        } else {
            badge.textContent = 'Diferencia: $ ' + Math.abs(debe-haber).toLocaleString('es-AR', {minimumFractionDigits:2});
            badge.className = 'badge bg-danger';
        }
    }

    form.addEventListener('submit', function(e) {
        let debe = 0, haber = 0;
        document.querySelectorAll('input[name="debe[]"]').forEach(el => debe += parseFloat(el.value) || 0);
        document.querySelectorAll('input[name="haber[]"]').forEach(el => haber += parseFloat(el.value) || 0);

        if (Math.abs(debe - haber) >= 0.01) {
            e.preventDefault();
            Swal.fire({icon:'error', title:'Asiento desbalanceado', text:'El total del Debe ($ '+debe.toLocaleString('es-AR',{minimumFractionDigits:2})+') debe ser igual al Haber ($ '+haber.toLocaleString('es-AR',{minimumFractionDigits:2})+').'});
            return false;
        }
        if (debe <= 0) {
            e.preventDefault();
            Swal.fire({icon:'error', title:'Montos inválidos', text:'El asiento debe tener montos mayores a cero.'});
            return false;
        }
    });
});
</script>
