<?php
/**
 * Backups de las bases del ERP (master + tenants).
 * Solo superadmin. El destino queda fuera del webroot.
 */

$formatearBytes = function (int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};

$formatearFecha = function (int $ts): string {
    return date('d/m/Y H:i', $ts);
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-save2"></i> <?= htmlspecialchars($title) ?></h3>
    <?php if (!empty($destino)): ?>
    <span class="badge bg-secondary">
        <i class="bi bi-folder2-open"></i> <?= htmlspecialchars($destino) ?>
    </span>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if (!empty($problemas)): ?>
<div class="alert alert-danger">
    <h6 class="alert-heading"><i class="bi bi-x-octagon"></i> El entorno no permite generar respaldos</h6>
    <ul class="mb-0 small">
        <?php foreach ($problemas as $p): ?>
        <li><?= htmlspecialchars($p) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- ================================================================== -->
<!-- Generar respaldo                                                    -->
<!-- ================================================================== -->
<div class="row mb-3">
    <?php
    $opciones = [
        ['master', 'bi-server', 'Master', 'border-primary', 'Solo la base <code>app_master</code>: empresas, usuarios y tareas.'],
        ['tenants', 'bi-buildings', 'Tenants', 'border-success', 'Un .sql por cada empresa activa. No incluye la master.'],
        ['full', 'bi-archive', 'Full (todo)', 'border-danger', 'Master + todos los tenants en una sola carpeta. Es la copia completa.'],
    ];
    foreach ($opciones as $op):
    ?>
    <div class="col-md-4">
        <div class="card h-100 <?= $op[3] ?>">
            <div class="card-body">
                <h5 class="card-title"><i class="bi <?= $op[1] ?>"></i> <?= $op[2] ?></h5>
                <p class="card-text small text-muted"><?= $op[4] ?></p>
                <form method="POST" action="<?= BASE_URL ?>/admin/backups-ejecutar">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                    <input type="hidden" name="modo" value="<?= $op[0] ?>">
                    <button type="submit" class="btn btn-outline-primary w-100"
                            <?= empty($problemas) ? '' : 'disabled' ?>
                            onclick="return confirm('Se genera una copia de las bases ahora. Continuar?')">
                        <i class="bi bi-download"></i> Generar respaldo
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ================================================================== -->
<!-- Respaldos existentes                                                -->
<!-- ================================================================== -->
<div class="card mb-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Respaldos existentes</h5>
        <span class="badge bg-dark"><?= count($respaldos) ?> carpeta(s)</span>
    </div>
    <div class="card-body p-0">

        <?php if (empty($respaldos)): ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            Todavia no hay respaldos. Genera uno con los botones de arriba.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Respaldo</th>
                        <th>Fecha</th>
                        <th class="text-end">Tamaño</th>
                        <th>Archivos</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($respaldos as $r): ?>
                    <tr>
                        <td><code class="small"><?= htmlspecialchars($r['nombre']) ?></code></td>
                        <td class="small"><?= $formatearFecha($r['fecha']) ?></td>
                        <td class="text-end small"><?= $formatearBytes($r['bytes']) ?></td>
                        <td>
                            <?php foreach ($r['sqls'] as $sql): ?>
                            <a class="badge bg-light text-dark border me-1 text-decoration-none"
                               href="<?= BASE_URL ?>/admin/backups-download/<?= urlencode($r['nombre']) ?>/<?= urlencode($sql) ?>"
                               title="Descargar <?= htmlspecialchars($sql) ?>">
                                <i class="bi bi-file-earmark-arrow-down"></i>
                                <?= htmlspecialchars(str_replace('.sql', '', $sql)) ?>
                            </a>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="<?= BASE_URL ?>/admin/backups-delete/<?= urlencode($r['nombre']) ?>"
                                  class="d-inline"
                                  onsubmit="return confirm('Se elimina el respaldo <?= htmlspecialchars($r['nombre']) ?>. No se puede deshacer. Continuar?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar respaldo">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ================================================================== -->
<!-- Bases que entran en un respaldo + cron                               -->
<!-- ================================================================== -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-database"></i> Bases incluidas</h5>
            </div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($bases as $nombre => $meta): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <code><?= htmlspecialchars($nombre) ?></code>
                        <span class="text-muted ms-1"><?= htmlspecialchars($meta['descripcion']) ?></span>
                    </span>
                    <span class="badge bg-<?= $meta['tipo'] === 'MASTER' ? 'primary' : 'success' ?>">
                        <?= $meta['tipo'] ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Respaldo automatico (cron)</h5>
            </div>
            <div class="card-body small">
                <p class="mb-2">Para respaldar todos los dias, correr por scheduler (una sola linea):</p>
                <pre class="bg-dark text-light p-2 rounded small mb-3">php <?= BASE_PATH ?>/app/helpers/backup.php --daily --keep=14</pre>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light">
                            <tr><th>Corridas/dia</th><th>--keep</th><th>Historial real</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1 (p. ej. 21:00)</td>
                                <td><code>--keep=7</code></td>
                                <td class="text-success">7 dias</td>
                            </tr>
                            <tr>
                                <td>2 (p. ej. 06:00 y 21:00)</td>
                                <td><code>--keep=7</code></td>
                                <td class="text-danger">3 dias y medio</td>
                            </tr>
                            <tr>
                                <td>2 (p. ej. 06:00 y 21:00)</td>
                                <td><code>--keep=14</code></td>
                                <td class="text-success">7 dias</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mb-2">
                    <strong>--keep es la cantidad de carpetas, no de dias.</strong>
                    Si corrés dos veces por dia, para una semana alcanza <code>--keep=14</code>.
                </p>

                <div class="alert alert-warning py-2 small mb-2">
                    <i class="bi bi-exclamation-triangle"></i>
                    Con <code>--keep=1</code> (default de <code>--daily</code>) solo queda la ultima copia:
                    si la BD se corrompe antes del cron, se respalda la corrupcion y se tira la
                    ultima copia buena.
                </div>

                <div class="alert alert-info py-2 small mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Ojo con el ambito.</strong> El cron protege contra corrupcion de datos,
                    no contra perdida de la maquina. Conviene copiar la carpeta de respaldos a
                    otro lado (nube o disco) con algo tan simple como un <code>robocopy</code>
                    programado.
                </div>
            </div>
        </div>
    </div>
</div>
