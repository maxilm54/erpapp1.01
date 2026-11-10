<?php
// Fila de inputs de campos personalizados (sin card, sin form). Variables esperadas:
//   $cpCampos  => configuracion de campos del modulo (Campopersonalizado::porModulo)
//   $cpValores => mapa [campo_id => valor] para prellenar
require_once BASE_PATH . '/app/models/Campopersonalizado.php';

$cpCampos  = is_array($cpCampos ?? null) ? $cpCampos : [];
$cpValores = is_array($cpValores ?? null) ? $cpValores : [];
?>
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
