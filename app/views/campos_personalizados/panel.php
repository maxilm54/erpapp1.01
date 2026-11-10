<?php
// Panel de valores de campos personalizados reutilizable. Variables esperadas (via extract):
//   $cpModulo => 'PRESUPUESTO' | 'NOTA_PEDIDO' | 'REMITO_SALIDA' | 'ORDEN_COMPRA'
//   $cpDocId  => id del documento
require_once BASE_PATH . '/app/models/Campopersonalizado.php';

$cpModulo = strtoupper((string)($cpModulo ?? ''));
$cpDocId  = (int)($cpDocId ?? 0);
if (!$cpModulo || !$cpDocId || !Campopersonalizado::moduloValido($cpModulo)) {
    return; // sin datos validos no se dibuja nada (nunca romper la vista)
}

$cpLista = (new Campopersonalizado())->valoresVisibles($cpModulo, $cpDocId);
?>
<?php if (!empty($cpLista)): ?>
<div class="card mb-3">
    <div class="card-header bg-info text-white">
        <strong><i class="bi bi-card-list"></i> Información adicional</strong>
    </div>
    <div class="card-body py-2">
        <table class="table table-sm table-borderless mb-0">
            <?php foreach ($cpLista as $cp): ?>
            <tr>
                <td class="text-muted" style="width:40%">
                    <strong><?= htmlspecialchars($cp['campo_nombre'] ?? ('Campo #' . $cp['campo_id'])) ?></strong>
                </td>
                <td><?= htmlspecialchars((string)$cp['valor']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>
