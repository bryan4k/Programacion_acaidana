<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$config = load_config();
$nonce = begin_secure_request($config);
$developmentMode = $config === null && is_local_development();
if ($config === null && !$developmentMode) {
    render_configuration_error($nonce);
}
$user = $developmentMode ? ['id' => 0, 'email' => 'desarrollo@local'] : require_user();

$months = [
    1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
    'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'
];

$today = new DateTimeImmutable('today');
$monthStart = $today->modify('first day of this month');
$monthEnd = $today->modify('last day of this month');
$dates = [];
$cursor = $monthStart;

while ($cursor <= $monthEnd) {
    if ($cursor->format('N') === '4') {
        $dates[] = [
            'id' => bin2hex(random_bytes(6)),
            'date' => $cursor->format('Y-m-d'),
            'directors' => '',
            'preacher' => '',
            'special' => false,
            'specialText' => 'Culto de Doctrina',
        ];
    }
    $cursor = $cursor->modify('+1 day');
}

$defaultState = [
    'churchName' => 'IGLESIA EVANGELICA INTERAMERICANA',
    'congregation' => 'ACAIDANA',
    'programTitle' => 'PROGRAMACION DAMAS Y CABALLEROS ' . $months[(int) $today->format('n')] . ' DE ' . $today->format('Y'),
    'headers' => ['FECHA', 'DIRECTORES', 'PREDICADOR'],
    'verse' => '“Yo soy el que doy testimonio de mí mismo, y el Padre que me envió da testimonio de mí”',
    'reference' => 'S. JUAN 8:18',
    'coordinatorLabel' => 'Coordinadores:',
    'coordinators' => "Marta Lucia Pérez Gaviria\nLuis Enrique Pérez Martínez",
    'logo' => '',
    'design' => [
        'headerImage' => '',
        'headerHeight' => 150,
        'headerOpacity' => 100,
        'headerLeftImage' => '',
        'headerLeftSize' => 210,
        'headerLeftOpacity' => 100,
        'headerRightImage' => '',
        'headerRightSize' => 210,
        'headerRightOpacity' => 100,
        'watermarkImage' => '',
        'watermarkSize' => 320,
        'watermarkOpacity' => 14,
        'watermarkX' => 50,
        'watermarkY' => 46,
        'footerImage' => '',
        'footerHeight' => 150,
        'footerOpacity' => 55,
        'verseFrameImage' => '',
        'verseHeight' => 170,
        'verseReferenceX' => 50,
        'verseReferenceY' => 86,
        'logoSize' => 100,
        'churchNameFontSize' => 23,
        'congregationFontSize' => 22,
        'ushersProgramLabelFontSize' => 44,
        'programTitleFontSize' => 18,
        'tableHeaderFontSize' => 17,
        'tableBodyFontSize' => 17,
        'verseFontSize' => 22,
        'referenceFontSize' => 15,
        'coordinatorLabelFontSize' => 18,
        'coordinatorsFontSize' => 18,
    ],
    'rows' => $dates,
];

$ushersDefaultState = [
    'churchName' => 'IGLESIA EVANGÉLICA INTERAMERICANA ACAIDANA',
    'congregation' => '',
    'programTitle' => 'Ujieres',
    'period' => $months[(int) $today->format('n')] . ' DE ' . $today->format('Y'),
    'headers' => ['FECHA', 'NOMBRE', 'UNIFORME'],
    'verse' => '',
    'reference' => '',
    'coordinatorLabel' => 'Coordinadores:',
    'coordinators' => '',
    'logo' => '',
    'design' => array_merge($defaultState['design'], [
        'footerHeight' => 60,
        'verseHeight' => 120,
        'churchNameFontSize' => 22,
        'programTitleFontSize' => 54,
        'tableHeaderFontSize' => 17,
        'tableBodyFontSize' => 16,
    ]),
    'rows' => array_map(static fn (array $row): array => [
        'id' => $row['id'],
        'date' => $row['date'],
        'names' => '',
        'uniformMode' => 'separate',
        'topType' => 'shirt',
        'topColor' => '#ffffff',
        'bottomType' => 'skirt',
        'bottomColor' => '#9ca3af',
        'dressColor' => '#f2a7b5',
    ], $dates),
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c2f68">
    <meta http-equiv="Content-Security-Policy" content="<?= htmlspecialchars(content_security_policy($nonce), ENT_QUOTES, 'UTF-8') ?>">
    <title>Programación de cultos</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style id="designRuntimeStyles" nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>"></style>
</head>
<body>
    <header class="app-header">
        <div>
            <span class="eyebrow">Herramienta de edición</span>
            <h1>Programación de cultos</h1>
        </div>
        <div class="header-actions">
            <span class="save-status" id="saveStatus" aria-live="polite">Sin cambios</span>
            <button class="button button-light" type="button" id="newButton">Nueva</button>
            <button class="button button-light" type="button" id="wordButton">Exportar Word</button>
            <button class="button button-gold" type="button" id="printButton">Imprimir / PDF</button>
            <?php if (!$developmentMode): ?>
                <form method="post" action="logout.php" class="logout-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <button class="button button-light" type="submit">Salir</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <main class="workspace">
        <aside class="editor-panel" aria-label="Controles de edición">
            <section class="panel-section panel-stack template-manager">
                <div class="section-heading">
                    <div>
                        <span class="step-number">01</span>
                        <h2>Mis plantillas</h2>
                    </div>
                </div>
                <label class="field-label" for="templateSelect">Plantilla guardada</label>
                <select id="templateSelect" class="panel-input">
                    <option value="">Nueva plantilla sin guardar</option>
                </select>
                <label class="field-label" for="templateName">Nombre de la plantilla</label>
                <input id="templateName" class="panel-input" type="text" maxlength="120" placeholder="Ej. Cultos de agosto 2026">
                <div class="template-actions">
                    <button class="panel-primary" type="button" id="saveTemplateButton">Guardar plantilla</button>
                    <button class="panel-secondary" type="button" id="duplicateTemplateButton">Guardar copia</button>
                    <button class="panel-danger" type="button" id="deleteTemplateButton" disabled>Eliminar</button>
                    <button class="panel-secondary ushers-new-button" type="button" id="newUshersButton">Nueva de ujieres</button>
                </div>
                <p class="security-note">El contenido se cifra antes de almacenarse en el servidor.</p>
            </section>

            <section class="panel-section panel-stack page-manager">
                <div class="section-heading">
                    <div>
                        <span class="step-number">02</span>
                        <h2>Programaciones del documento</h2>
                    </div>
                    <button class="icon-button" id="addPageButton" type="button" title="Agregar programación" aria-label="Agregar programación">+</button>
                </div>
                <p>Cada programación ocupa una hoja A4 independiente.</p>
                <div class="page-controls" id="pageControls"></div>
                <div class="page-actions">
                    <button type="button" id="duplicatePageButton">Duplicar</button>
                    <button type="button" id="movePageUpButton">Subir</button>
                    <button type="button" id="movePageDownButton">Bajar</button>
                    <button type="button" id="deletePageButton">Eliminar</button>
                </div>
            </section>

            <section class="panel-section">
                <span class="step-number">03</span>
                <div>
                    <h2>Edita el contenido</h2>
                    <p>Haz clic sobre cualquier texto de la hoja para cambiarlo y luego guarda la plantilla.</p>
                </div>
            </section>

            <section class="panel-section panel-stack">
                <div class="section-heading">
                    <div>
                        <span class="step-number">04</span>
                        <h2>Imagen institucional</h2>
                    </div>
                </div>
                <label class="upload-button" for="logoInput">Seleccionar logo</label>
                <input type="file" id="logoInput" accept="image/png,image/jpeg,image/webp" hidden>
                <button class="text-button" type="button" id="removeLogoButton">Quitar imagen</button>
                <label class="range-label">Tamaño <span id="logoSizeValue"></span></label>
                <input class="panel-range" type="range" data-design-setting="logoSize" data-output="logoSizeValue" min="40" max="180" step="5" aria-label="Tamaño del logo">
            </section>

            <section class="panel-section panel-stack">
                <div class="section-heading">
                    <div>
                        <span class="step-number">05</span>
                        <h2>Diseño e imágenes</h2>
                    </div>
                </div>
                <p>Personaliza las imágenes decorativas. Se recomienda usar PNG con fondo transparente.</p>

                <div class="asset-control">
                    <div class="asset-title"><strong>Fondo central de cabecera</strong><span id="headerHeightValue"></span></div>
                    <div class="asset-buttons">
                        <label for="headerImageInput">Subir imagen</label>
                        <input type="file" id="headerImageInput" data-design-image="headerImage" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" data-remove-design-image="headerImage">Quitar</button>
                    </div>
                    <input type="range" data-design-setting="headerHeight" data-output="headerHeightValue" min="60" max="300" step="5" aria-label="Altura de imagen de cabecera">
                    <label class="range-label">Opacidad <span id="headerOpacityValue"></span></label>
                    <input type="range" data-design-setting="headerOpacity" data-output="headerOpacityValue" data-unit="%" min="5" max="100" step="1">
                </div>

                <div class="asset-control asset-control-side">
                    <div class="asset-title"><strong>Cabecera izquierda</strong><span id="headerLeftSizeValue"></span></div>
                    <div class="asset-buttons">
                        <label for="headerLeftImageInput">Subir imagen</label>
                        <input type="file" id="headerLeftImageInput" data-design-image="headerLeftImage" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" data-remove-design-image="headerLeftImage">Quitar</button>
                    </div>
                    <input type="range" data-design-setting="headerLeftSize" data-output="headerLeftSizeValue" min="60" max="380" step="5" aria-label="Tamaño de imagen izquierda">
                    <label class="range-label">Opacidad <span id="headerLeftOpacityValue"></span></label>
                    <input type="range" data-design-setting="headerLeftOpacity" data-output="headerLeftOpacityValue" data-unit="%" min="5" max="100" step="1">
                </div>

                <div class="asset-control asset-control-side">
                    <div class="asset-title"><strong>Cabecera derecha</strong><span id="headerRightSizeValue"></span></div>
                    <div class="asset-buttons">
                        <label for="headerRightImageInput">Subir imagen</label>
                        <input type="file" id="headerRightImageInput" data-design-image="headerRightImage" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" data-remove-design-image="headerRightImage">Quitar</button>
                    </div>
                    <input type="range" data-design-setting="headerRightSize" data-output="headerRightSizeValue" min="60" max="380" step="5" aria-label="Tamaño de imagen derecha">
                    <label class="range-label">Opacidad <span id="headerRightOpacityValue"></span></label>
                    <input type="range" data-design-setting="headerRightOpacity" data-output="headerRightOpacityValue" data-unit="%" min="5" max="100" step="1">
                </div>

                <div class="asset-control">
                    <div class="asset-title"><strong>Marca de agua</strong><span id="watermarkSizeValue"></span></div>
                    <div class="asset-buttons">
                        <label for="watermarkImageInput">Subir imagen</label>
                        <input type="file" id="watermarkImageInput" data-design-image="watermarkImage" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" data-remove-design-image="watermarkImage">Quitar</button>
                    </div>
                    <label class="range-label">Tamaño</label>
                    <input type="range" data-design-setting="watermarkSize" data-output="watermarkSizeValue" min="80" max="700" step="10">
                    <label class="range-label">Opacidad <span id="watermarkOpacityValue"></span></label>
                    <input type="range" data-design-setting="watermarkOpacity" data-output="watermarkOpacityValue" data-unit="%" min="3" max="60" step="1">
                    <div class="range-pair">
                        <label>Horizontal <input type="range" data-design-setting="watermarkX" min="0" max="100" step="1"></label>
                        <label>Vertical <input type="range" data-design-setting="watermarkY" min="0" max="100" step="1"></label>
                    </div>
                </div>

                <div class="asset-control">
                    <div class="asset-title"><strong>Pie de página</strong><span id="footerHeightValue"></span></div>
                    <div class="asset-buttons">
                        <label for="footerImageInput">Subir imagen</label>
                        <input type="file" id="footerImageInput" data-design-image="footerImage" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" data-remove-design-image="footerImage">Quitar</button>
                    </div>
                    <input type="range" data-design-setting="footerHeight" data-output="footerHeightValue" min="60" max="320" step="5" aria-label="Altura de imagen de pie de página">
                    <label class="range-label">Opacidad <span id="footerOpacityValue"></span></label>
                    <input type="range" data-design-setting="footerOpacity" data-output="footerOpacityValue" data-unit="%" min="5" max="100" step="1">
                </div>

                <div class="asset-control">
                    <div class="asset-title"><strong>Cuadro del versículo</strong><span id="verseHeightValue"></span></div>
                    <div class="asset-buttons">
                        <label for="verseFrameImageInput">Subir marco</label>
                        <input type="file" id="verseFrameImageInput" data-design-image="verseFrameImage" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" data-remove-design-image="verseFrameImage">Quitar</button>
                    </div>
                    <input type="range" data-design-setting="verseHeight" data-output="verseHeightValue" min="120" max="360" step="5" aria-label="Altura del cuadro del versículo">
                    <label class="range-label">Posición horizontal de la cita</label>
                    <input type="range" data-design-setting="verseReferenceX" min="10" max="90" step="1">
                    <label class="range-label">Posición vertical de la cita</label>
                    <input type="range" data-design-setting="verseReferenceY" min="55" max="92" step="1">
                </div>
            </section>

            <section class="panel-section panel-stack">
                <div class="section-heading">
                    <div>
                        <span class="step-number">06</span>
                        <h2>Tamaños de letra</h2>
                    </div>
                </div>
                <p>Ajusta cada sección de manera independiente.</p>
                <div class="font-controls">
                    <label>Nombre iglesia <span id="churchNameFontSizeValue"></span><input type="range" data-design-setting="churchNameFontSize" data-output="churchNameFontSizeValue" min="12" max="42" step="1"></label>
                    <label>Congregación <span id="congregationFontSizeValue"></span><input type="range" data-design-setting="congregationFontSize" data-output="congregationFontSizeValue" min="12" max="42" step="1"></label>
                    <label data-ushers-only hidden>Palabra PROGRAMACIÓN <span id="ushersProgramLabelFontSizeValue"></span><input type="range" data-design-setting="ushersProgramLabelFontSize" data-output="ushersProgramLabelFontSizeValue" min="22" max="64" step="1"></label>
                    <label>Título programación <span id="programTitleFontSizeValue"></span><input type="range" data-design-setting="programTitleFontSize" data-output="programTitleFontSizeValue" min="10" max="72" step="1"></label>
                    <label>Encabezado tabla <span id="tableHeaderFontSizeValue"></span><input type="range" data-design-setting="tableHeaderFontSize" data-output="tableHeaderFontSizeValue" min="9" max="28" step="1"></label>
                    <label>Contenido tabla <span id="tableBodyFontSizeValue"></span><input type="range" data-design-setting="tableBodyFontSize" data-output="tableBodyFontSizeValue" min="9" max="30" step="1"></label>
                    <label>Texto del versículo <span id="verseFontSizeValue"></span><input type="range" data-design-setting="verseFontSize" data-output="verseFontSizeValue" min="11" max="38" step="1"></label>
                    <label>Cita bíblica <span id="referenceFontSizeValue"></span><input type="range" data-design-setting="referenceFontSize" data-output="referenceFontSizeValue" min="9" max="28" step="1"></label>
                    <label>Etiqueta coordinadores <span id="coordinatorLabelFontSizeValue"></span><input type="range" data-design-setting="coordinatorLabelFontSize" data-output="coordinatorLabelFontSizeValue" min="10" max="30" step="1"></label>
                    <label>Nombres coordinadores <span id="coordinatorsFontSizeValue"></span><input type="range" data-design-setting="coordinatorsFontSize" data-output="coordinatorsFontSizeValue" min="10" max="32" step="1"></label>
                </div>
            </section>

            <section class="panel-section panel-stack">
                <div class="section-heading">
                    <div>
                        <span class="step-number">07</span>
                        <h2 id="rowSectionTitle">Fechas y filas</h2>
                    </div>
                    <button class="icon-button" id="addRowButton" type="button" title="Agregar fecha" aria-label="Agregar fecha">+</button>
                </div>
                <p id="rowSectionHelp">Activa “Fila especial” para unir Directores y Predicador en esa fecha.</p>
                <div id="rowControls" class="row-controls"></div>
                <button class="add-row-button" id="addRowButtonBottom" type="button">+ Agregar otra fecha</button>
                <p class="page-fit-warning" id="pageFitWarning" hidden></p>
            </section>

            <div class="tip">
                <strong>Consejo de impresión</strong>
                <span>En la ventana de impresión selecciona tamaño A4 y activa “gráficos de fondo”.</span>
            </div>
        </aside>

        <section class="preview-area" id="previewArea" aria-label="Vista previa del documento">
            <div class="preview-label"><span></span> Vista previa A4</div>
            <article class="program-sheet" id="programSheet">
                <img class="custom-header-image" id="headerDecorationImage" alt="" hidden>
                <img class="custom-header-side custom-header-left" id="headerLeftImage" alt="" hidden>
                <img class="custom-header-side custom-header-right" id="headerRightImage" alt="" hidden>
                <img class="custom-watermark-image" id="customWatermarkImage" alt="" hidden>
                <img class="custom-footer-image" id="customFooterImage" alt="" hidden>
                <div class="landscape-footer" id="defaultFooter" aria-hidden="true">
                    <span class="book">⌑</span>
                    <span class="hills"></span>
                    <span class="chapel">♜</span>
                </div>
                <div class="watermark watermark-left" id="defaultHeaderLeft"></div>
                <div class="watermark watermark-right" id="defaultHeaderRight">✦</div>

                <header class="sheet-header">
                    <div class="logo-wrap" id="logoWrap">
                        <img id="churchLogo" alt="Logo de la iglesia" hidden>
                        <div class="default-emblem" id="defaultEmblem" aria-hidden="true">
                            <span class="cross">✝</span>
                            <b>IEI</b>
                        </div>
                    </div>
                    <div class="standard-heading">
                        <h2 class="editable church-name" contenteditable="true" data-field="churchName" spellcheck="true"></h2>
                        <div class="editable congregation" contenteditable="true" data-field="congregation" spellcheck="true"></div>
                        <div class="ornament"><span>◇</span></div>
                        <div class="editable program-title" contenteditable="true" data-field="programTitle" spellcheck="true"></div>
                    </div>
                    <div class="ushers-heading" hidden>
                        <h2 class="editable church-name ushers-church-name" contenteditable="true" data-field="churchName" spellcheck="true"></h2>
                        <div class="ushers-program-label">PROGRAMACIÓN</div>
                        <div class="editable ushers-program-title" contenteditable="true" data-field="programTitle" spellcheck="true"></div>
                        <div class="editable ushers-period" contenteditable="true" data-field="period" spellcheck="true"></div>
                    </div>
                </header>

                <div class="table-wrap">
                    <table class="program-table">
                        <thead>
                            <tr>
                                <th><span class="editable" contenteditable="true" data-header="0"></span></th>
                                <th><span class="editable" contenteditable="true" data-header="1"></span></th>
                                <th><span class="editable" contenteditable="true" data-header="2"></span></th>
                            </tr>
                        </thead>
                        <tbody id="programRows"></tbody>
                    </table>
                </div>

                <section class="verse-card">
                    <img class="verse-frame-image" id="verseFrameImage" alt="" hidden>
                    <span class="branch branch-left">❧</span>
                    <blockquote class="editable" contenteditable="true" data-field="verse" spellcheck="true"></blockquote>
                    <span class="branch branch-right">❧</span>
                    <strong class="editable reference" contenteditable="true" data-field="reference" spellcheck="true"></strong>
                </section>

                <footer class="sheet-footer">
                    <div class="editable coordinator-label" contenteditable="true" data-field="coordinatorLabel" spellcheck="true"></div>
                    <div class="editable coordinators" contenteditable="true" data-field="coordinators" spellcheck="true"></div>
                </footer>

            </article>
            <div class="print-pages" id="printPages" aria-hidden="true"></div>
        </section>
    </main>

    <template id="rowTemplate">
        <tr>
            <td class="date-cell"><span class="print-date"></span></td>
            <td class="directors-cell"><div class="cell-editor" contenteditable="true" data-row-field="directors" spellcheck="true"></div></td>
            <td class="preacher-cell"><div class="cell-editor" contenteditable="true" data-row-field="preacher" spellcheck="true"></div></td>
            <td class="special-cell" colspan="2"><div class="cell-editor" contenteditable="true" data-row-field="specialText" spellcheck="true"></div></td>
        </tr>
    </template>

    <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
        window.DEFAULT_STATE = <?= json_encode($defaultState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.USHERS_DEFAULT_STATE = <?= json_encode($ushersDefaultState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.APP_CONFIG = <?= json_encode([
            'storageMode' => $developmentMode ? 'local' : 'server',
            'csrfToken' => $_SESSION['csrf'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="<?= htmlspecialchars(asset_url('assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
