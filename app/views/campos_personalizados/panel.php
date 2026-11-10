<?php
// Panel de valores de campos personalizados reutilizable. Variables esperadas (via extract):
//   $cpModulo => 'PRESUPUESTO' | 'NOTA_PEDIDO' | 'REMITO_SALIDA' | 'ORDEN_COMPRA'
//   $cpDocId  => id del documento
// Opcionales (modo edicion, ej: la vista de la NP):
//   $cpEditable   => true para mostrar el formulario con los campos configurados del modulo
//   $cpEditAction => URL POST que guarda los campos (ej: BASE_URL/notaspedido/guardar-campos/5)
//   $cpQuitarBase => URL base POST para quitar una fila heredada (se agrega /{docId}/{valorId})
require_once BASE_PATH . '/app/models/Campopersonalizado.php';

$cpModulo = strtoupper((string)($cpModulo ?? ''));
$cpDocId  = (int)($cpDocId ?? 0);
if (!$cpModulo || !$cpDocId || !Campopersonalizado::moduloValido($cpModulo)) {
    return; // sin datos validos no se dibuja nada (nunca romper la vista)
}

$cpModelo = new Campopersonalizado();
$cpLista  = $cpModelo->valoresVisibles($cpModulo, $cpDocId);

$cpEditable   = !empty($cpEditable) && !empty($cpEditAction);
$cpQuitarBase = (string)($cpQuitarBase ?? '');
$cpConfig     = $cpEditable ? $cpModelo->porModulo($cpModulo) : [];
$cpConfigIds  = array_map('intval', array_column($cpConfig, 'id'));

// Modo edicion: los campos configurados del modulo van en el formulario (prefill con
// valor propio o, si no hay, con el valor heredado del mismo nombre) y solo las filas
// "otras" (heredadas sin campo propio) se listan read-only.
$cpValores = $cpEditable ? $cpModelo->valoresMapaConHeredadas($cpModulo, $cpDocId) : [];
$cpOtras = array_values(array_filter($cpLista, function ($v) use ($cpConfigIds) {
    return !in_array((int)$v['campo_id'], $cpConfigIds, true);
}));
$cpMostrar = $cpEditable ? $cpOtras : $cpLista;

// Nada que mostrar ni editar => no se dibuja la card
if (empty($cpLista) && (!$cpEditable || empty($cpConfig))) {
    return;
}
?>
<div class="card mb-3">
    <div class="card-header bg-info text-white">
        <strong><i class="bi bi-card-list"></i> Información adicional</strong>
        <?php if ($cpEditable): ?>
            <small class="ms-1">— campos configurados en Empresa &gt; Configuraciones</small>
        <?php endif; ?>
    </div>
    <div class="card-body py-2">
        <?php if ($cpEditable && !empty($cpConfig)): ?>
            <?php $cpCampos = $cpConfig; ?>
            <form method="POST" action="<?= htmlspecialchars($cpEditAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                <?php require BASE_PATH . '/app/views/campos_personalizados/inputs_inner.php'; ?>
                <button class="btn btn-sm btn-primary mt-3"><i class="bi bi-save"></i> Guardar</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($cpMostrar)): ?>
            <?php if ($cpEditable && !empty($cpConfig)): ?>
                <hr class="my-2">
                <p class="text-muted small mb-1">
                    <i class="bi bi-arrow-down-up"></i> Heredados de otro módulo (ej: del presupuesto origen):
                </p>
            <?php endif; ?>
            <table class="table table-sm table-borderless mb-0">
                <?php foreach ($cpMostrar as $cp): ?>
                <tr>
                    <td class="text-muted" style="width:40%">
                        <strong><?= htmlspecialchars($cp['campo_nombre'] ?? ('Campo #' . $cp['campo_id'])) ?></strong>
                        <?php if ($cpEditable): ?><span class="badge bg-info-subtle text-dark">heredado</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string)$cp['valor']) ?></td>
                    <?php if ($cpEditable && $cpQuitarBase !== ''): ?>
                    <td class="text-end" style="width:50px">
                        <form method="POST" action="<?= htmlspecialchars($cpQuitarBase) ?>/<?= $cpDocId ?>/<?= (int)$cp['id'] ?>"
                              onsubmit="return confirm('¿Quitar este valor de este documento?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                            <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Quitar valor">
                                <i class="bi bi-x"></i>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>
