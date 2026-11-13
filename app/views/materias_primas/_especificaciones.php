<?php
/**
 * Campos de tipo de gestión y ficha técnica de una materia prima.
 * Lo incluyen form.php (alta) y edit.php (edición).
 *
 * Espera: $item (array de la MP o vacío) y $tiposGestion (valor => etiqueta).
 */
$item = $item ?? [];
$tiposGestion = $tiposGestion ?? Materiaprimaespecificacion::etiquetasTipoGestion();
$tipoActual = $item['tipo_gestion'] ?? 'UNIDAD';

$num = static function ($valor, int $decimales) {
    if ($valor === null || $valor === '') {
        return '';
    }
    return rtrim(rtrim(number_format((float)$valor, $decimales, '.', ''), '0'), '.');
};
?>

<div class="row">
    <div class="col-md-6">
        <label class="form-label" for="tipoGestion">Tipo de gestión <span class="text-danger">*</span></label>
        <select name="tipo_gestion" id="tipoGestion" class="form-select" required>
            <?php foreach ($tiposGestion as $valor => $etiqueta): ?>
                <option value="<?= htmlspecialchars($valor) ?>" <?= $tipoActual === $valor ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text" id="ayudaTipoGestion"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="obsEspecificacion">Observaciones</label>
        <input type="text" name="obs" id="obsEspecificacion" maxlength="255" class="form-control"
               value="<?= htmlspecialchars($item['obs'] ?? '') ?>" placeholder="Opcional">
        <div class="form-text">Máximo 255 caracteres.</div>
    </div>
</div>

<fieldset class="border rounded p-3 mt-3 mb-3">
    <legend class="float-none w-auto px-2 fs-6 text-muted">Ficha técnica (opcional)</legend>
    <p class="small text-muted mb-2">
        Los parámetros son genéricos; su significado depende del tipo de gestión.
        Si se dejan todos vacíos, la materia prima no tendrá ficha técnica.
    </p>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label class="form-label" for="parametroTexto1">Parámetro texto 1</label>
            <input type="text" name="parametro_texto_1" id="parametroTexto1" maxlength="50" class="form-control"
                   value="<?= htmlspecialchars($item['parametro_texto_1'] ?? '') ?>">
            <div class="form-text" data-ayuda="texto"></div>
        </div>
        <div class="col-md-6 mb-2">
            <label class="form-label" for="presentacion">Presentación</label>
            <input type="number" step="0.01" min="0" name="presentacion" id="presentacion" class="form-control"
                   value="<?= $num($item['presentacion'] ?? null, 2) ?>">
            <div class="form-text" data-ayuda="pres"></div>
        </div>
        <div class="col-md-6 mb-2">
            <label class="form-label" for="parametroNumerico1">Parámetro numérico 1</label>
            <input type="number" step="0.0001" min="0" name="parametro_numerico_1" id="parametroNumerico1" class="form-control"
                   value="<?= $num($item['parametro_numerico_1'] ?? null, 4) ?>">
            <div class="form-text" data-ayuda="num1"></div>
        </div>
        <div class="col-md-6 mb-2">
            <label class="form-label" for="parametroNumerico2">Parámetro numérico 2</label>
            <input type="number" step="0.0001" min="0" name="parametro_numerico_2" id="parametroNumerico2" class="form-control"
                   value="<?= $num($item['parametro_numerico_2'] ?? null, 4) ?>">
            <div class="form-text" data-ayuda="num2"></div>
        </div>
    </div>
</fieldset>

<script>
(function () {
    const ayudas = {
        UNIDAD: {
            texto: 'Forma, tipo o acabado. Ej: Redondo.',
            num1: 'Medida principal. Ej: diámetro en mm.',
            num2: 'Peso por unidad en gramos.',
            pres: 'Unidades por envase. Ej: 1 unidad por caja.'
        },
        LINEAL: {
            texto: 'Forma o tipo. Ej: Redondo, tipo de ligamento.',
            num1: 'Diámetro o ancho. Ej: 12.70 mm.',
            num2: 'Peso por metro. Ej: 0.99 kg/m.',
            pres: 'Largo de la pieza. Ej: 6 (barra) o 50 (rollo).'
        },
        SUPERFICIE: {
            texto: 'Tipo de tela, chapa o acabado.',
            num1: 'Ancho en metros. Ej: 1.50.',
            num2: 'Gramaje o espesor. Ej: 240.',
            pres: 'Largo del rollo en metros. Ej: 50.'
        },
        GRANEL: {
            texto: 'Variedad o tipo. Ej: Trigo pan.',
            num1: 'Densidad o medida principal.',
            num2: 'Peso por bulto. Ej: 25 kg.',
            pres: 'Contenido del envase. Ej: 25 (bolsa) o 1000 (big bag).'
        }
    };
    const ayudaTipo = {
        UNIDAD: 'Se cuenta por piezas: unidades, cajas, paquetes.',
        LINEAL: 'Se mide por longitud: metros, barras o rollos.',
        SUPERFICIE: 'Se mide por superficie: m², telas o chapas.',
        GRANEL: 'Se mide por peso o volumen: kg, litros.'
    };

    const select = document.getElementById('tipoGestion');
    if (!select) return;

    const actualizar = function () {
        const cfg = ayudas[select.value] || ayudas.UNIDAD;
        document.querySelectorAll('[data-ayuda]').forEach(function (el) {
            const clave = el.dataset.ayuda;
            if (clave) el.textContent = cfg[clave] || '';
        });
        const ayuda = document.getElementById('ayudaTipoGestion');
        if (ayuda) ayuda.textContent = ayudaTipo[select.value] || '';
    };

    select.addEventListener('change', actualizar);
    actualizar();
})();
</script>
