<?php
// Panel de adjuntos reutilizable. Variables esperadas (via extract):
//   $adjModulo     => 'PRESUPUESTO' | 'NOTA_PEDIDO' | 'REMITO_SALIDA' | 'PRODUCCION'
//   $adjDocId      => id del documento
require_once BASE_PATH . '/app/models/Adjunto.php';
require_once BASE_PATH . '/app/models/Configuracion.php';

$adjModulo = strtoupper((string)($adjModulo ?? ''));
$adjDocId = (int)($adjDocId ?? 0);
if (!$adjModulo || !$adjDocId || !Adjunto::moduloValido($adjModulo)) {
    return; // sin datos validos no se dibuja nada (nunca romper la vista)
}

$adjUsarAdjuntos = (new Configuracion())->activa('usar_adjuntos', false);
$adjLista = $adjUsarAdjuntos ? (new Adjunto())->porDocumento($adjModulo, $adjDocId) : [];
?>
<?php if ($adjUsarAdjuntos): ?>
<div class="card mb-3">
    <div class="card-header bg-secondary text-white">
        <strong><i class="bi bi-paperclip"></i> Adjuntos</strong>
        <span class="badge bg-light text-dark ms-1"><?= count($adjLista) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($adjLista)): ?>
            <p class="text-muted small mb-2">Sin adjuntos.</p>
        <?php else: ?>
            <table class="table table-sm table-hover align-middle mb-2">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th style="width:110px">Tamano</th>
                        <th style="width:160px">Subido</th>
                        <th style="width:120px">Por</th>
                        <th class="text-end" style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($adjLista as $a): ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_URL ?>/adjuntos/descargar/<?= (int)$a['id'] ?>" target="_blank">
                                <i class="bi bi-file-earmark"></i> <?= htmlspecialchars($a['nombre_original']) ?>
                            </a>
                        </td>
                        <td class="small"><?= number_format(((int)$a['tamano']) / 1024, 0) ?> KB</td>
                        <td class="small text-muted"><?= htmlspecialchars(substr((string)$a['created_at'], 0, 16)) ?></td>
                        <td class="small"><?= htmlspecialchars($a['usuario_nombre'] ?? '-') ?></td>
                        <td class="text-end">
                            <form method="POST" action="<?= BASE_URL ?>/adjuntos/eliminar/<?= (int)$a['id'] ?>" style="display:inline"
                                  onsubmit="return confirm('¿Eliminar el adjunto <?= htmlspecialchars($a['nombre_original']) ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar adjunto">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/adjuntos/subir/<?= $adjModulo ?>/<?= $adjDocId ?>"
              enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
            <input type="file" class="form-control form-control-sm" name="archivos[]" multiple
                   accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.heic,.doc,.docx,.odt,.xls,.xlsx,.ods,.csv,.ppt,.pptx,.txt,.zip,.rar,.msg,.eml"
                   required>
            <button class="btn btn-sm btn-primary"><i class="bi bi-upload"></i> Adjuntar</button>
        </form>
        <div class="form-text small">Hasta 10 MB por archivo. Varios archivos permitidos (Ctrl+click).</div>
    </div>
</div>
<?php endif; ?>
