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
        <?php require BASE_PATH . '/app/views/campos_personalizados/inputs_inner.php'; ?>
    </div>
</div>
<?php endif; ?>
