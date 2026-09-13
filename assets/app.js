(() => {
    'use strict';

    const LEGACY_STORAGE_KEY = 'programacion-cultos-v1';
    const LOCAL_TEMPLATES_KEY = 'programacion-cultos-templates-v2';
    const appConfig = window.APP_CONFIG;
    const spanishDays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const spanishMonths = ['Ene.', 'Feb.', 'Mar.', 'Abr.', 'May.', 'Jun.', 'Jul.', 'Ago.', 'Sept.', 'Oct.', 'Nov.', 'Dic.'];
    const sheet = document.querySelector('#programSheet');
    const previewArea = document.querySelector('#previewArea');
    const rowsContainer = document.querySelector('#programRows');
    const controlsContainer = document.querySelector('#rowControls');
    const rowTemplate = document.querySelector('#rowTemplate');
    const saveStatus = document.querySelector('#saveStatus');
    const templateSelect = document.querySelector('#templateSelect');
    const templateName = document.querySelector('#templateName');
    const deleteTemplateButton = document.querySelector('#deleteTemplateButton');
    const pageControls = document.querySelector('#pageControls');
    const printPages = document.querySelector('#printPages');
    let templates = [];
    let activeTemplate = { id: null, version: null };
    let dirty = false;
    let busy = false;

    const clone = (value) => JSON.parse(JSON.stringify(value));

    function normalizePage(value, templateType = 'worship') {
        const defaults = clone(templateType === 'ushers' ? window.USHERS_DEFAULT_STATE : window.DEFAULT_STATE);
        const page = { ...defaults, ...(value || {}) };
        delete page.design;
        delete page.logo;
        page.headers = Array.isArray(page.headers) ? page.headers.slice(0, 3) : clone(defaults.headers);
        while (page.headers.length < 3) page.headers.push(defaults.headers[page.headers.length]);
        page.rows = Array.isArray(page.rows) && page.rows.length ? clone(page.rows) : clone(defaults.rows);
        page.rows = page.rows.map((row) => {
            const normalized = { ...(templateType === 'ushers' ? defaults.rows[0] : {}), ...row, id: row.id || makeId() };
            return normalized;
        });
        return page;
    }

    function normalizeDocument(value) {
        const templateType = value?.templateType === 'ushers' ? 'ushers' : 'worship';
        const defaults = clone(templateType === 'ushers' ? window.USHERS_DEFAULT_STATE : window.DEFAULT_STATE);
        if (value && Array.isArray(value.pages) && value.pages.length) {
            return {
                formatVersion: 2,
                templateType,
                logo: typeof value.logo === 'string' ? value.logo : '',
                design: { ...defaults.design, ...(value.design || {}) },
                pages: value.pages.map((page) => normalizePage(page, templateType)),
            };
        }
        return {
            formatVersion: 2,
            templateType,
            logo: typeof value?.logo === 'string' ? value.logo : '',
            design: { ...defaults.design, ...(value?.design || {}) },
            pages: [normalizePage(value || defaults, templateType)],
        };
    }

    function serializeDocument() {
        return {
            formatVersion: 2,
            templateType: documentState.templateType,
            logo: documentState.logo,
            design: clone(documentState.design),
            pages: documentState.pages.map((page) => {
                const copy = clone(page);
                delete copy.design;
                delete copy.logo;
                return copy;
            }),
        };
    }

    function initialDocument() {
        if (appConfig.storageMode === 'local') {
            try {
                const legacy = JSON.parse(localStorage.getItem(LEGACY_STORAGE_KEY));
                if (legacy && Array.isArray(legacy.rows) && Array.isArray(legacy.headers)) {
                    return normalizeDocument(legacy);
                }
            } catch (error) {
                console.warn('No se pudo recuperar el borrador anterior.', error);
            }
        }
        return normalizeDocument(window.DEFAULT_STATE);
    }

    let documentState = initialDocument();
    let activePageIndex = 0;
    let state;

    function activatePage(index) {
        activePageIndex = Math.max(0, Math.min(index, documentState.pages.length - 1));
        state = documentState.pages[activePageIndex];
        state.design = documentState.design;
        state.logo = documentState.logo;
    }

    activatePage(0);

    function formatDate(value) {
        if (!value) return 'Selecciona una fecha';
        if (documentState.templateType === 'ushers') {
            const [year, month, day] = value.split('-');
            return year && month && day ? `${day}/${month}/${year}` : value;
        }
        const date = new Date(`${value}T12:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        return `${spanishDays[date.getDay()]} ${String(date.getDate()).padStart(2, '0')} ${spanishMonths[date.getMonth()]}`;
    }

    function makeId() {
        return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
    }

    function setStatus(message, type = '') {
        saveStatus.textContent = message;
        saveStatus.dataset.status = type;
    }

    function markDirty() {
        dirty = true;
        setStatus('Cambios sin guardar', 'pending');
    }

    function setBusy(value) {
        busy = value;
        document.querySelectorAll('.template-manager button, .template-manager select').forEach((element) => {
            element.disabled = value || (element === deleteTemplateButton && !activeTemplate.id);
        });
    }

    async function api(action, options = {}) {
        const response = await fetch(`api.php?action=${encodeURIComponent(action)}${options.query || ''}`, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: options.body ? {
                'Content-Type': 'application/json',
                'X-CSRF-Token': appConfig.csrfToken,
            } : {},
            body: options.body ? JSON.stringify(options.body) : undefined,
        });
        let result;
        try {
            result = await response.json();
        } catch (error) {
            throw new Error('El servidor devolvió una respuesta inválida.');
        }
        if (response.status === 401) {
            window.location.assign('login.php');
            throw new Error('La sesión expiró.');
        }
        if (!response.ok) throw new Error(result.error || 'No se pudo completar la operación.');
        return result;
    }

    function localRecords() {
        try {
            const stored = JSON.parse(localStorage.getItem(LOCAL_TEMPLATES_KEY));
            return Array.isArray(stored) ? stored : [];
        } catch (error) {
            return [];
        }
    }

    function writeLocalRecords(records) {
        localStorage.setItem(LOCAL_TEMPLATES_KEY, JSON.stringify(records));
    }

    async function loadTemplateList(selectedId = activeTemplate.id) {
        if (appConfig.storageMode === 'server') {
            templates = (await api('list')).templates;
        } else {
            templates = localRecords()
                .map(({ content, ...summary }) => summary)
                .sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)));
        }
        templateSelect.replaceChildren(new Option('Nueva plantilla sin guardar', ''));
        templates.forEach((template) => {
            templateSelect.add(new Option(template.name, String(template.id)));
        });
        templateSelect.value = selectedId ? String(selectedId) : '';
        deleteTemplateButton.disabled = !activeTemplate.id;
    }

    async function fetchTemplate(id) {
        if (appConfig.storageMode === 'server') {
            return (await api('get', { query: `&id=${encodeURIComponent(id)}` })).template;
        }
        const template = localRecords().find((item) => String(item.id) === String(id));
        if (!template) throw new Error('Plantilla no encontrada.');
        return clone(template);
    }

    async function saveTemplate(asCopy = false) {
        if (busy) return;
        if (!allPagesFit()) {
            alert('Una de las programaciones no cabe en una hoja A4. Corrige las filas o tamaños antes de guardar.');
            return;
        }
        const name = templateName.value.trim();
        if (!name) {
            templateName.focus();
            alert('Escribe un nombre para guardar la plantilla.');
            return;
        }
        setBusy(true);
        setStatus('Guardando de forma segura…');
        try {
            if (appConfig.storageMode === 'server') {
                const result = await api('save', {
                    method: 'POST',
                    body: {
                        id: asCopy ? null : activeTemplate.id,
                        version: asCopy ? null : activeTemplate.version,
                        name,
                        content: serializeDocument(),
                    },
                });
                activeTemplate = { id: result.template.id, version: result.template.version };
            } else {
                const records = localRecords();
                const id = asCopy || !activeTemplate.id ? makeId() : activeTemplate.id;
                const existingIndex = records.findIndex((item) => String(item.id) === String(id));
                const version = existingIndex >= 0 ? Number(records[existingIndex].version) + 1 : 1;
                const record = { id, name, version, updated_at: new Date().toISOString(), content: serializeDocument() };
                if (existingIndex >= 0) records[existingIndex] = record;
                else records.push(record);
                writeLocalRecords(records);
                localStorage.removeItem(LEGACY_STORAGE_KEY);
                activeTemplate = { id, version };
            }
            dirty = false;
            await loadTemplateList(activeTemplate.id);
            setStatus('Plantilla guardada', 'saved');
        } catch (error) {
            setStatus('No se pudo guardar', 'error');
            alert(error.message);
        } finally {
            setBusy(false);
        }
    }

    async function selectTemplate(id) {
        if (!id) {
            startNewTemplate();
            return;
        }
        setBusy(true);
        setStatus('Cargando plantilla…');
        try {
            const template = await fetchTemplate(id);
            documentState = normalizeDocument(template.content);
            activatePage(0);
            activeTemplate = { id: template.id, version: Number(template.version) };
            templateName.value = template.name;
            dirty = false;
            placeStaticContent();
            renderRows();
            renderPageControls();
            templateSelect.value = String(template.id);
            setStatus('Plantilla cargada', 'saved');
        } catch (error) {
            templateSelect.value = activeTemplate.id ? String(activeTemplate.id) : '';
            setStatus('No se pudo cargar', 'error');
            alert(error.message);
        } finally {
            setBusy(false);
        }
    }

    function startNewTemplate(templateType = 'worship') {
        const defaults = templateType === 'ushers' ? window.USHERS_DEFAULT_STATE : window.DEFAULT_STATE;
        documentState = normalizeDocument({ ...clone(defaults), templateType });
        activatePage(0);
        activeTemplate = { id: null, version: null };
        templateName.value = '';
        templateSelect.value = '';
        dirty = false;
        placeStaticContent();
        renderRows();
        renderPageControls();
        deleteTemplateButton.disabled = true;
        setStatus('Nueva plantilla sin guardar');
    }

    async function deleteTemplate() {
        if (!activeTemplate.id || busy) return;
        if (!confirm(`¿Eliminar permanentemente la plantilla “${templateName.value}”?`)) return;
        setBusy(true);
        try {
            if (appConfig.storageMode === 'server') {
                await api('delete', { method: 'POST', body: { id: activeTemplate.id, version: activeTemplate.version } });
            } else {
                writeLocalRecords(localRecords().filter((item) => String(item.id) !== String(activeTemplate.id)));
            }
            startNewTemplate();
            await loadTemplateList();
            setStatus('Plantilla eliminada');
        } catch (error) {
            setStatus('No se pudo eliminar', 'error');
            alert(error.message);
        } finally {
            setBusy(false);
        }
    }

    function placeStaticContent() {
        document.querySelectorAll('[data-field]').forEach((element) => {
            element.textContent = state[element.dataset.field] || '';
        });
        document.querySelectorAll('[data-header]').forEach((element) => {
            element.textContent = state.headers[Number(element.dataset.header)] || '';
        });
        updateLogo();
        updateDesign();
        const isUshers = documentState.templateType === 'ushers';
        sheet.classList.toggle('ushers-sheet', isUshers);
        sheet.querySelector('.standard-heading').hidden = isUshers;
        sheet.querySelector('.ushers-heading').hidden = !isUshers;
        sheet.querySelector('.verse-card').hidden = isUshers;
        sheet.querySelector('.sheet-footer').hidden = isUshers;
        document.querySelector('#rowSectionTitle').textContent = isUshers ? 'Fechas y uniformes' : 'Fechas y filas';
        document.querySelector('#rowSectionHelp').textContent = isUshers
            ? 'Selecciona las prendas y sus colores. El dibujo del uniforme se actualiza automáticamente.'
            : 'Activa “Fila especial” para unir Directores y Predicador en esa fecha.';
        document.querySelectorAll('[data-ushers-only]').forEach((element) => {
            element.hidden = !isUshers;
        });
    }

    function updateLogo() {
        const image = document.querySelector('#churchLogo');
        const emblem = document.querySelector('#defaultEmblem');
        if (state.logo) {
            image.src = state.logo;
            image.hidden = false;
            emblem.hidden = true;
        } else {
            image.removeAttribute('src');
            image.hidden = true;
            emblem.hidden = false;
        }
    }

    function updateDesign() {
        const design = state.design;
        const images = {
            headerImage: document.querySelector('#headerDecorationImage'),
            headerLeftImage: document.querySelector('#headerLeftImage'),
            headerRightImage: document.querySelector('#headerRightImage'),
            watermarkImage: document.querySelector('#customWatermarkImage'),
            footerImage: document.querySelector('#customFooterImage'),
            verseFrameImage: document.querySelector('#verseFrameImage'),
        };
        Object.entries(images).forEach(([field, image]) => {
            if (design[field]) {
                image.src = design[field];
                image.hidden = false;
            } else {
                image.removeAttribute('src');
                image.hidden = true;
            }
        });

        document.querySelector('#defaultHeaderLeft').hidden = Boolean(design.headerLeftImage);
        document.querySelector('#defaultHeaderRight').hidden = Boolean(design.headerRightImage);
        document.querySelector('#defaultFooter').hidden = Boolean(design.footerImage);
        document.querySelector('.verse-card').classList.toggle('has-custom-frame', Boolean(design.verseFrameImage));

        const numeric = (field) => Number(design[field]);
        document.querySelector('#designRuntimeStyles').textContent = `
            #headerDecorationImage { height: ${numeric('headerHeight')}px; opacity: ${numeric('headerOpacity') / 100}; }
            #headerLeftImage, #defaultHeaderLeft { width: ${numeric('headerLeftSize')}px; opacity: ${numeric('headerLeftOpacity') / 100}; }
            #headerRightImage, #defaultHeaderRight { width: ${numeric('headerRightSize')}px; opacity: ${numeric('headerRightOpacity') / 100}; }
            #defaultHeaderLeft { height: ${numeric('headerLeftSize')}px; }
            #defaultHeaderRight { font-size: ${Math.round(numeric('headerRightSize') * 0.52)}px; }
            #customWatermarkImage, .page-watermark { width: ${numeric('watermarkSize')}px; opacity: ${numeric('watermarkOpacity') / 100}; left: ${numeric('watermarkX')}%; top: ${numeric('watermarkY')}%; }
            #customFooterImage, #defaultFooter, .page-custom-footer, .page-default-footer { height: ${numeric('footerHeight')}px; opacity: ${numeric('footerOpacity') / 100}; }
            .program-sheet { padding-bottom: ${numeric('footerHeight')}px; }
            .logo-wrap { height: ${Math.max(104, numeric('logoSize'))}px; }
            #churchLogo { max-width: ${numeric('logoSize')}px; max-height: ${numeric('logoSize')}px; }
            .verse-card { min-height: ${numeric('verseHeight')}px; }
            .verse-card .reference { left: ${numeric('verseReferenceX')}%; top: ${numeric('verseReferenceY')}%; font-size: ${numeric('referenceFontSize')}px; }
            .church-name { font-size: ${numeric('churchNameFontSize')}px; }
            .congregation { font-size: ${numeric('congregationFontSize')}px; }
            .ushers-program-label { font-size: ${numeric('ushersProgramLabelFontSize')}px; }
            .program-title, .ushers-program-title { font-size: ${numeric('programTitleFontSize')}px; }
            .program-table th { font-size: ${numeric('tableHeaderFontSize')}px; }
            .program-table tbody { font-size: ${numeric('tableBodyFontSize')}px; }
            .verse-card blockquote { font-size: ${numeric('verseFontSize')}px; }
            .coordinator-label { font-size: ${numeric('coordinatorLabelFontSize')}px; }
            .coordinators { font-size: ${numeric('coordinatorsFontSize')}px; }
        `;

        document.querySelectorAll('[data-design-setting]').forEach((input) => {
            input.value = design[input.dataset.designSetting];
            const output = input.dataset.output ? document.querySelector(`#${input.dataset.output}`) : null;
            if (output) output.textContent = `${input.value}${input.dataset.unit || ' px'}`;
        });
    }

    function readDesignImage(input) {
        const [file] = input.files;
        if (!file) return;
        if (file.size > 2 * 1024 * 1024 || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            alert('Usa una imagen PNG, JPEG o WebP de menos de 2 MB.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.addEventListener('load', () => {
            state.design[input.dataset.designImage] = reader.result;
            updateDesign();
            renderRows();
            markDirty();
        });
        reader.readAsDataURL(file);
    }

    function createRow(row) {
        if (documentState.templateType === 'ushers') return createUshersRow(row);
        const fragment = rowTemplate.content.cloneNode(true);
        const tableRow = fragment.querySelector('tr');
        tableRow.dataset.rowId = row.id;
        tableRow.classList.toggle('is-special', row.special);
        fragment.querySelector('.print-date').textContent = formatDate(row.date);
        fragment.querySelectorAll('[data-row-field]').forEach((editor) => {
            editor.textContent = row[editor.dataset.rowField] || '';
        });
        return fragment;
    }

    function safeColor(value, fallback) {
        return /^#[0-9a-f]{6}$/i.test(value || '') ? value : fallback;
    }

    function garmentSvg(type, color) {
        const fill = safeColor(color, '#ffffff');
        const gradientId = `fabric-${makeId()}`;
        const gradient = `<defs><linearGradient id="${gradientId}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".48"/><stop offset=".24" stop-color="${fill}"/><stop offset=".72" stop-color="${fill}"/><stop offset="1" stop-color="#000" stop-opacity=".28"/></linearGradient></defs>`;
        if (type === 'dress') {
            return `<svg class="garment garment-dress" viewBox="0 0 92 122" aria-hidden="true">${gradient}
                <path class="garment-drop" d="M30 13l11-6c2 8 8 8 10 0l11 6 15 13-8 16-11-6-2 20c8 16 17 34 28 55-25 9-51 9-76 0 11-21 20-39 28-55l-2-20-11 6-8-16z"/>
                <path class="garment-outline" fill="url(#${gradientId})" d="M27 10l13-6c2 8 10 8 12 0l13 6 15 14-8 17-12-6-3 21c8 16 17 34 28 55-25 10-53 10-78 0 11-21 20-39 28-55l-3-21-12 6-8-17z"/>
                <path class="garment-glow" d="M31 13l8-4-1 45C29 74 21 92 12 108l10 3c7-23 15-41 24-55l-3-42c-5 1-8 0-12-1z"/>
                <path class="garment-ink" d="M40 4c1 11 11 11 12 0M35 55c7 3 15 3 22 0M46 16v38M35 62l-13 45M46 61v53M57 62l13 45"/>
                <path class="garment-piping" d="M34 52c8 3 16 3 24 0l1 7c-9 3-18 3-27 0z"/>
                <path class="garment-collar-round" d="M39 5c1 8 4 12 7 12s6-4 7-12c-4 3-10 3-14 0z"/>
            </svg>`;
        }
        if (type === 'skirt') {
            return `<svg class="garment garment-bottom" viewBox="0 0 82 92" aria-hidden="true">${gradient}
                <path class="garment-drop" d="M17 10h50l3 15c2 19 6 38 11 55-25 10-50 10-75 0 6-18 10-36 12-55z"/>
                <path class="garment-outline" fill="url(#${gradientId})" d="M14 7h52l3 16c2 19 6 38 11 55-25 11-52 11-78 0 6-18 10-36 12-55z"/>
                <path class="garment-glow" d="M17 23h14c-1 21-5 39-11 56-5 1-10 0-15-2 6-18 10-36 12-54z"/>
                <path class="garment-ink" d="M14 22c18 3 36 3 55 0M27 27l-9 49M41 27v55M55 27l9 49M5 75c24 9 48 9 72 0"/>
                <path class="garment-band" fill="url(#${gradientId})" d="M14 7h52l3 16c-18 4-37 4-55 0z"/>
                <path class="garment-band-line" d="M16 12c16 3 32 3 48 0"/>
            </svg>`;
        }
        if (type === 'pants') {
            return `<svg class="garment garment-bottom" viewBox="0 0 74 96" aria-hidden="true">${gradient}
                <path class="garment-drop" d="M13 10h51c3 21 2 42-1 62l-2 17c-8 3-16 3-24 0l-2-43-4 43c-8 3-16 3-24 0l2-18c-2-20-1-41 4-61z"/>
                <path class="garment-outline" fill="url(#${gradientId})" d="M10 7h53c4 21 3 43 0 64l-2 18c-8 3-17 3-25 0l-2-43-4 43c-8 3-17 3-25 0l2-19C5 49 6 28 10 7z"/>
                <path class="garment-glow" d="M12 22h13c-2 23-1 45 2 66-6 2-12 2-18 0l2-18c-2-17-1-33 1-48z"/>
                <path class="garment-ink" d="M9 21c18 3 36 3 54 0M34 22v24M34 46L22 87M34 46l13 41M12 31l13 7M59 31l-13 7"/>
                <path class="garment-band" fill="url(#${gradientId})" d="M10 7h53l1 15c-18 4-37 4-56 0z"/>
            </svg>`;
        }
        return `<svg class="garment garment-top" viewBox="0 0 102 88" aria-hidden="true">${gradient}
            <path class="garment-drop" d="M31 12l15-6c3 9 8 9 11 0l15 6 24 16-10 22-15-8 3 38c-16 6-31 6-47 0l3-38-15 8L5 28z"/>
            <path class="garment-outline" fill="url(#${gradientId})" d="M28 9l17-6c3 9 9 9 12 0l17 6 24 17-10 22-16-9 3 40c-16 6-32 6-48 0l3-40-16 9L4 26z"/>
            <path class="garment-glow" d="M31 12l12-5-4 69c-4 1-8 1-12 0l3-37-15 8-5-10 18-14z"/>
            <path class="garment-ink" d="M51 20v55M30 39l-16 9M72 39l16 9M28 72c16 4 31 4 47 0"/>
            <path class="garment-collar" d="M45 3l6 17-13-7-7-5zM57 3l-6 17 13-7 7-5z"/>
            <path class="garment-pocket" d="M61 39h13l-1 12c-4 2-8 2-12 0z"/>
            <path class="garment-cuff" d="M8 39l7 10 16-9-4-7zM94 39l-7 10-16-9 4-7z"/>
            <circle class="garment-button" cx="55" cy="29" r="1.6"/><circle class="garment-button" cx="55" cy="40" r="1.6"/><circle class="garment-button" cx="55" cy="52" r="1.6"/>
        </svg>`;
    }

    function uniformIllustration(row) {
        if (row.uniformMode === 'dress') return garmentSvg('dress', row.dressColor);
        return `${garmentSvg(row.topType, row.topColor)}${garmentSvg(row.bottomType, row.bottomColor)}`;
    }

    function createUshersRow(row) {
        const tableRow = document.createElement('tr');
        tableRow.dataset.rowId = row.id;
        const dateCell = document.createElement('td');
        dateCell.className = 'date-cell';
        dateCell.innerHTML = `<span class="print-date">${formatDate(row.date)}</span>`;
        const namesCell = document.createElement('td');
        namesCell.className = 'ushers-names-cell';
        const names = document.createElement('div');
        names.className = 'cell-editor names-editor';
        names.contentEditable = 'true';
        names.dataset.rowField = 'names';
        names.spellcheck = true;
        names.textContent = row.names || '';
        namesCell.append(names);
        const uniformCell = document.createElement('td');
        uniformCell.className = 'uniform-cell';
        const illustration = document.createElement('div');
        illustration.className = 'uniform-illustration';
        illustration.title = row.uniformMode === 'dress' ? 'Vestido' : 'Uniforme de dos prendas';
        illustration.innerHTML = uniformIllustration(row);
        uniformCell.append(illustration);
        tableRow.append(dateCell, namesCell, uniformCell);
        return tableRow;
    }

    function applyUshersLayout(pageElement, pageData) {
        pageElement.classList.remove('ushers-compact', 'ushers-dense');
        if (documentState.templateType !== 'ushers') return;
        const visualLines = pageData.rows.reduce((total, row) => {
            const lines = String(row.names || '').split('\n');
            return total + Math.max(1, lines.reduce((count, line) => count + Math.max(1, Math.ceil(line.length / 32)), 0));
        }, 0);
        if (pageData.rows.length >= 15 || visualLines >= 30) {
            pageElement.classList.add('ushers-dense');
        } else if (pageData.rows.length >= 9 || visualLines >= 17) {
            pageElement.classList.add('ushers-compact');
        }
    }

    function pageCapacity() {
        const design = state.design;
        const rowHeight = Math.max(45, Number(design.tableBodyFontSize) * 2.7);
        const logoExtra = Math.max(0, Number(design.logoSize) - 104);
        if (documentState.templateType === 'ushers') {
            return Math.max(1, Math.floor((1123 - 265 - logoExtra) / Math.max(40, Number(design.tableBodyFontSize) * 2.4)));
        }
        const available = 1123 - 300 - logoExtra - Number(design.footerHeight) - Number(design.verseHeight) - 190;
        return Math.max(1, Math.floor(available / rowHeight));
    }

    function renderRows() {
        rowsContainer.replaceChildren();
        controlsContainer.replaceChildren();
        state.rows.forEach((row) => rowsContainer.append(createRow(row)));
        applyUshersLayout(sheet, state);

        state.rows.forEach((row, index) => {
            const control = document.createElement('div');
            control.className = 'row-control';
            control.dataset.rowId = row.id;
            if (documentState.templateType === 'ushers') {
                control.innerHTML = `<div class="row-control-top"><input type="date"><button type="button" class="remove-row" title="Eliminar fila" aria-label="Eliminar fila">×</button></div><div class="uniform-controls"><label>Presentación<select data-uniform-field="uniformMode"><option value="separate">Dos prendas</option><option value="dress">Vestido</option></select></label><div class="separate-uniform-fields"><label>Prenda superior<select data-uniform-field="topType"><option value="shirt">Camisa</option><option value="blouse">Blusa</option></select></label><label>Color superior<input type="color" data-uniform-field="topColor"></label><label>Prenda inferior<select data-uniform-field="bottomType"><option value="skirt">Falda</option><option value="pants">Pantalón</option></select></label><label>Color inferior<input type="color" data-uniform-field="bottomColor"></label></div><label class="dress-uniform-field">Color vestido<input type="color" data-uniform-field="dressColor"></label></div>`;
                control.querySelectorAll('[data-uniform-field]').forEach((input) => {
                    input.value = row[input.dataset.uniformField];
                });
                control.querySelector('.separate-uniform-fields').hidden = row.uniformMode === 'dress';
                control.querySelector('.dress-uniform-field').hidden = row.uniformMode !== 'dress';
            } else {
                control.innerHTML = '<div class="row-control-top"><input type="date"><button type="button" class="remove-row" title="Eliminar fila" aria-label="Eliminar fila">×</button></div><label class="special-toggle"><input type="checkbox"><span>Fila especial (unir 2 columnas)</span></label>';
                control.querySelector('input[type="checkbox"]').checked = Boolean(row.special);
            }
            const dateInput = control.querySelector('input[type="date"]');
            dateInput.value = row.date || '';
            dateInput.setAttribute('aria-label', `Fecha de la fila ${index + 1}`);
            controlsContainer.append(control);
        });

        const capacity = pageCapacity();
        const warning = document.querySelector('#pageFitWarning');
        const exceeds = state.rows.length > capacity;
        warning.hidden = !exceeds;
        warning.textContent = exceeds ? `Esta programación tiene ${state.rows.length} filas y solo caben ${capacity}. Elimina filas o reduce los tamaños antes de guardar.` : '';
        sheet.classList.toggle('page-overflow', exceeds);
        document.querySelector('#addRowButton').disabled = state.rows.length >= capacity;
        document.querySelector('#addRowButtonBottom').disabled = state.rows.length >= capacity;
    }

    function addRow() {
        if (state.rows.length >= pageCapacity()) {
            alert('Esta hoja A4 ya está completa. Agrega otra programación para continuar.');
            return;
        }
        const previous = state.rows[state.rows.length - 1];
        let date = '';
        if (previous?.date) {
            const next = new Date(`${previous.date}T12:00:00`);
            next.setDate(next.getDate() + 7);
            date = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
        }
        state.rows.push(documentState.templateType === 'ushers'
            ? { id: makeId(), date, names: '', uniformMode: 'separate', topType: 'shirt', topColor: '#ffffff', bottomType: 'skirt', bottomColor: '#9ca3af', dressColor: '#f2a7b5' }
            : { id: makeId(), date, directors: '', preacher: '', special: false, specialText: 'Culto especial' });
        renderRows();
        markDirty();
        controlsContainer.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function renderPageControls() {
        pageControls.replaceChildren();
        documentState.pages.forEach((page, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `page-control${index === activePageIndex ? ' is-active' : ''}`;
            button.dataset.pageIndex = String(index);
            const title = documentState.templateType === 'ushers'
                ? `${page.programTitle || 'Ujieres'}${page.period ? ` - ${page.period}` : ''}`
                : (page.programTitle || `Programación ${index + 1}`);
            button.textContent = `${index + 1}. ${title.length > 38 ? `${title.slice(0, 38)}…` : title}`;
            pageControls.append(button);
        });
        document.querySelector('#deletePageButton').disabled = documentState.pages.length === 1;
        document.querySelector('#movePageUpButton').disabled = activePageIndex === 0;
        document.querySelector('#movePageDownButton').disabled = activePageIndex === documentState.pages.length - 1;
    }

    function showPage(index) {
        activatePage(index);
        placeStaticContent();
        renderRows();
        renderPageControls();
    }

    function addProgramPage(duplicateCurrent = false) {
        let page;
        if (duplicateCurrent) {
            page = normalizePage(clone(state), documentState.templateType);
            page.rows = page.rows.map((row) => ({ ...row, id: makeId() }));
        } else {
            const defaults = documentState.templateType === 'ushers' ? window.USHERS_DEFAULT_STATE : window.DEFAULT_STATE;
            page = normalizePage(defaults, documentState.templateType);
            page.churchName = state.churchName;
            page.congregation = state.congregation;
            page.headers = clone(state.headers);
            page.coordinatorLabel = state.coordinatorLabel;
            page.coordinators = state.coordinators;
            if (documentState.templateType === 'ushers') page.period = state.period;
            page.rows = page.rows.map((row) => ({ ...row, id: makeId() }));
        }
        documentState.pages.splice(activePageIndex + 1, 0, page);
        showPage(activePageIndex + 1);
        markDirty();
    }

    function moveProgramPage(direction) {
        const target = activePageIndex + direction;
        if (target < 0 || target >= documentState.pages.length) return;
        const [page] = documentState.pages.splice(activePageIndex, 1);
        documentState.pages.splice(target, 0, page);
        showPage(target);
        markDirty();
    }

    function deleteProgramPage() {
        if (documentState.pages.length === 1) return;
        if (!confirm('¿Eliminar esta programación del documento?')) return;
        documentState.pages.splice(activePageIndex, 1);
        showPage(Math.min(activePageIndex, documentState.pages.length - 1));
        markDirty();
    }

    function allPagesFit() {
        const capacity = pageCapacity();
        if (documentState.pages.some((page) => page.rows.length > capacity)) return false;
        buildPrintPages();
        printPages.classList.add('is-measuring');
        const fits = Array.from(printPages.querySelectorAll('.program-sheet')).every((page) => page.scrollHeight <= page.clientHeight);
        printPages.classList.remove('is-measuring');
        return fits;
    }

    function buildPageElement(pageData) {
        const page = sheet.cloneNode(true);
        page.removeAttribute('id');
        page.classList.remove('page-overflow');
        page.querySelectorAll('[data-field]').forEach((element) => {
            element.textContent = pageData[element.dataset.field] || '';
        });
        page.querySelectorAll('[data-header]').forEach((element) => {
            element.textContent = pageData.headers[Number(element.dataset.header)] || '';
        });
        const body = page.querySelector('#programRows');
        body.removeAttribute('id');
        body.replaceChildren();
        pageData.rows.forEach((row) => body.append(createRow(row)));
        applyUshersLayout(page, pageData);
        return page;
    }

    function buildPrintPages() {
        printPages.replaceChildren();
        documentState.pages.forEach((page) => printPages.append(buildPageElement(page)));
    }

    function cleanWordHtml(node) {
        const copy = node.cloneNode(true);
        copy.querySelectorAll('[contenteditable]').forEach((element) => {
            element.textContent = element.innerText;
            element.removeAttribute('contenteditable');
        });
        copy.querySelectorAll('[hidden]').forEach((element) => element.remove());
        return copy.outerHTML;
    }

    function exportWord() {
        if (!allPagesFit()) {
            alert('Una de las programaciones no cabe en una hoja A4. Corrige las filas o tamaños antes de exportar.');
            return;
        }
        buildPrintPages();
        const styles = Array.from(document.styleSheets)
            .map((styleSheet) => {
                try { return Array.from(styleSheet.cssRules).map((rule) => rule.cssText).join('\n'); }
                catch (error) { return ''; }
            })
            .join('\n');
        const pages = Array.from(printPages.querySelectorAll('.program-sheet')).map(cleanWordHtml).join('');
        const documentHtml = `<!doctype html><html><head><meta charset="utf-8"><style>${styles}\n.program-sheet{box-shadow:none;margin:0 auto;page-break-after:always}.program-sheet:last-child{page-break-after:auto}.app-header,.editor-panel{display:none}.program-table thead{display:table-header-group}.program-table tr,.verse-card,.sheet-footer{page-break-inside:avoid}</style></head><body>${pages}</body></html>`;
        const blob = new Blob(['\ufeff', documentHtml], { type: 'application/msword' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${(state.programTitle || 'programacion').toLowerCase().replace(/[^a-z0-9áéíóúñ]+/gi, '-')}.doc`;
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    }

    previewArea.addEventListener('input', (event) => {
        const field = event.target.closest('[data-field]');
        const header = event.target.closest('[data-header]');
        const rowField = event.target.closest('[data-row-field]');
        if (field) {
            state[field.dataset.field] = field.innerText.trim();
            if (field.dataset.field === 'programTitle') renderPageControls();
        }
        if (header) {
            const index = Number(header.dataset.header);
            state.headers[index] = header.innerText.trim();
        }
        if (rowField) {
            const tableRow = rowField.closest('tr');
            const row = state.rows.find((item) => item.id === tableRow.dataset.rowId);
            if (row) row[rowField.dataset.rowField] = rowField.innerText.trim();
            if (documentState.templateType === 'ushers') applyUshersLayout(sheet, state);
        }
        markDirty();
    });

    previewArea.addEventListener('paste', (event) => {
        const editable = event.target.closest('[contenteditable]');
        if (!editable) return;
        event.preventDefault();
        const text = event.clipboardData?.getData('text/plain') || '';
        document.execCommand('insertText', false, text);
    });

    controlsContainer.addEventListener('change', (event) => {
        const control = event.target.closest('.row-control');
        const row = state.rows.find((item) => item.id === control?.dataset.rowId);
        if (!row) return;
        if (event.target.matches('input[type="date"]')) row.date = event.target.value;
        if (event.target.matches('input[type="checkbox"]')) row.special = event.target.checked;
        const uniformField = event.target.closest('[data-uniform-field]');
        if (uniformField) row[uniformField.dataset.uniformField] = uniformField.value;
        renderRows();
        markDirty();
    });

    controlsContainer.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.remove-row');
        if (!removeButton) return;
        if (state.rows.length === 1) {
            alert('La tabla debe conservar al menos una fila.');
            return;
        }
        const id = removeButton.closest('.row-control').dataset.rowId;
        state.rows = state.rows.filter((row) => row.id !== id);
        renderRows();
        markDirty();
    });

    document.querySelector('#logoInput').addEventListener('change', (event) => {
        const [file] = event.target.files;
        if (!file) return;
        if (file.size > 2 * 1024 * 1024 || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            alert('Usa una imagen PNG, JPEG o WebP de menos de 2 MB.');
            event.target.value = '';
            return;
        }
        const reader = new FileReader();
        reader.addEventListener('load', () => {
            documentState.logo = reader.result;
            state.logo = documentState.logo;
            updateLogo();
            markDirty();
        });
        reader.readAsDataURL(file);
    });

    document.querySelector('#removeLogoButton').addEventListener('click', () => {
        documentState.logo = '';
        state.logo = documentState.logo;
        document.querySelector('#logoInput').value = '';
        updateLogo();
        markDirty();
    });
    document.querySelectorAll('[data-design-image]').forEach((input) => {
        input.addEventListener('change', () => readDesignImage(input));
    });
    document.querySelectorAll('[data-remove-design-image]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.dataset.removeDesignImage;
            state.design[field] = '';
            const input = document.querySelector(`[data-design-image="${field}"]`);
            if (input) input.value = '';
            updateDesign();
            renderRows();
            markDirty();
        });
    });
    document.querySelectorAll('[data-design-setting]').forEach((input) => {
        input.addEventListener('input', () => {
            state.design[input.dataset.designSetting] = Number(input.value);
            updateDesign();
            markDirty();
        });
        input.addEventListener('change', renderRows);
    });
    document.querySelector('#addRowButton').addEventListener('click', addRow);
    document.querySelector('#addRowButtonBottom').addEventListener('click', addRow);
    pageControls.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page-index]');
        if (button) showPage(Number(button.dataset.pageIndex));
    });
    document.querySelector('#addPageButton').addEventListener('click', () => addProgramPage(false));
    document.querySelector('#duplicatePageButton').addEventListener('click', () => addProgramPage(true));
    document.querySelector('#movePageUpButton').addEventListener('click', () => moveProgramPage(-1));
    document.querySelector('#movePageDownButton').addEventListener('click', () => moveProgramPage(1));
    document.querySelector('#deletePageButton').addEventListener('click', deleteProgramPage);
    document.querySelector('#printButton').addEventListener('click', () => {
        if (!allPagesFit()) {
            alert('Una de las programaciones no cabe en una hoja A4. Corrige las filas o tamaños antes de imprimir.');
            return;
        }
        buildPrintPages();
        window.print();
    });
    document.querySelector('#wordButton').addEventListener('click', exportWord);
    document.querySelector('#saveTemplateButton').addEventListener('click', () => saveTemplate(false));
    document.querySelector('#duplicateTemplateButton').addEventListener('click', () => saveTemplate(true));
    deleteTemplateButton.addEventListener('click', deleteTemplate);
    templateName.addEventListener('input', markDirty);
    templateSelect.addEventListener('change', async (event) => {
        const nextId = event.target.value;
        if (dirty && !confirm('Hay cambios sin guardar. ¿Quieres descartarlos y abrir otra plantilla?')) {
            templateSelect.value = activeTemplate.id ? String(activeTemplate.id) : '';
            return;
        }
        await selectTemplate(nextId);
    });
    document.querySelector('#newButton').addEventListener('click', () => {
        if (dirty && !confirm('¿Crear una plantilla nueva y descartar los cambios sin guardar?')) return;
        startNewTemplate();
    });
    document.querySelector('#newUshersButton').addEventListener('click', () => {
        if (dirty && !confirm('¿Crear una plantilla de ujieres y descartar los cambios sin guardar?')) return;
        startNewTemplate('ushers');
    });
    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    async function initialize() {
        placeStaticContent();
        renderRows();
        renderPageControls();
        try {
            await loadTemplateList();
            setStatus(appConfig.storageMode === 'local' ? 'Modo de desarrollo local' : 'Conexión segura');
        } catch (error) {
            setStatus('Error de conexión', 'error');
            alert(error.message);
        }
    }

    initialize();
})();
