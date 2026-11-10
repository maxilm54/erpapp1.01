<?php
// Inputs de campos personalizados reutilizable. Variables esperadas (via extract):
//   $cpCampos  => configuracion de campos del modulo (Campopersonalizado::porModulo)
//   $cpValores => mapa [campo_id => valor] para prellenar en edicion (opcional)
require_once BASE_PATH . '/app/models/Campopersonalizado.php';

$cpCampos  = is_array($cpCampos ?? null) ? $cpCampos : [];
$cpValores = is_array($cpValores ?? null) ? $cpValores : [];
?>
<?php if (!empty($cpCampos)): ?>
<!-- Campos personalizados (config Empresa -> Configuraciones) -->
<div class="card border-primary mt-3">
    <div class="card-header bg-primary-subtle py-2">
        <strong><i class="bi bi-card-list"></i> Información adicional</strong>
        <small class="text-muted">— campos configurados en Empresa &gt; Configuraciones</small>
    </div>
    <div class="card-body py-2">
        <div class="row g-3">
            <?php foreach ($cpCampos as $cp): ?>
                <?php
                    $cpId    = (int)$cp['id'];
                    $cpValor = (string)($cpValores[$cpId] ?? '');
                    $cpTipo  = $cp['tipo'] ?? 'texto';
                ?>
                <div class="col-md-4">
                    <label class="form-label" for="cp_campo_<?= $cpId ?>"><?= htmlspecialchars($cp['nombre']) ?></label>
                    <?php if ($cpTipo === 'numero'): ?>
                        <input type="number" step="any" id="cp_campo_<?= $cpId ?>"
                               name="campos_cp[<?= $cpId ?>]" value="<?= htmlspecialchars($cpValor) ?>"
                               class="form-control">
                    <?php elseif ($cpTipo === 'fecha'): ?>
                        <input type="date" id="cp_campo_<?= $cpId ?>"
                               name="campos_cp[<?= $cpId ?>]" value="<?= htmlspecialchars($cpValor) ?>"
                               class="form-control">
                    <?php else: ?>
                        <input type="text" id="cp_campo_<?= $cpId ?>"
                               name="campos_cp[<?= $cpId ?>]" value="<?= htmlspecialchars($cpValor) ?>"
                               class="form-control" maxlength="255">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
