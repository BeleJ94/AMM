/** Registres compacts communs : pagination, recherche et filtres progressifs. */
(function (window, document, $) {
    'use strict';
    if (!$ || !$.fn.dataTable) return;
    let sequence = 0;
    const language = {
        url: '', processing: 'Chargement…', search: 'Recherche :', searchPlaceholder: 'Rechercher…',
        lengthMenu: '_MENU_ lignes par page', info: '_START_–_END_ sur _TOTAL_ résultats',
        infoEmpty: 'Aucun résultat', infoFiltered: '(sur _MAX_ au total)',
        emptyTable: 'Aucun résultat dans cette sélection.', zeroRecords: 'Aucun résultat ne correspond aux filtres.',
        paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' }
    };
    function json(value, fallback) { try { return JSON.parse(value); } catch (_) { return fallback; } }
    function text(value) { const element = document.createElement('div'); element.innerHTML = value; return element.textContent.trim().replace(/\s+/g, ' '); }
    function normalizeState(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
    }
    function isStateField(field) {
        return /^(?:status|statut|etat)(?:_|$)/i.test(field.name) || field.hasAttribute('data-register-state-filter') || /\b(statut|etat)\b/.test(normalizeState(Array.from(field.labels || [], function (label) { return label.textContent; }).join(' ')));
    }
    function stateAppearance(value) {
        const state = normalizeState(value);
        if (/^(paye|payee|payes|payees|execute|executee|executes|executees|termine|terminee|termines|terminees|atteint|atteinte|atteints|atteintes|clos|close|cloture|cloturee|archive|archivee|rapproche|rapprochee|solde|soldee)$/.test(state)) return { tone: 'success', complete: true };
        if (/^(actif|active|actifs|actives|renouvele|renouvelee|valide|validee|valides|validees|validee direction|prete pour ordre|pretes pour ordre|accuse|verrouille)$/.test(state)) return { tone: 'approved', complete: false };
        if (/^(en suspens|suspendu|suspendue|suspension|en pause)$/.test(state)) return { tone: 'hold', complete: false };
        if (/^(direction|validee comptable)$/.test(state)) return { tone: 'direction', complete: false };
        if (/^(rejete|rejetee|rejetes|rejetees|refuse|refusee|expire|expiree|echu|echue|overdue|en retard|retard|critique)$/.test(state)) return { tone: 'danger', complete: false };
        if (/^(annule|annulee|annules|annulees|resilie|resiliee|inactif|inactive|inactifs|inactives)$/.test(state)) return { tone: 'muted', complete: false };
        if (/^(soumise|soumis|a valider|a executer|emis|emise|en attente|partiel|partielle|partiellement payee|a risque|expire bientot|a rapprocher|non rapproche)$/.test(state)) return { tone: 'warning', complete: false };
        if (/^(a controler|recu|recue|assigne|assignee|en cours|en cours execution|en rapprochement|ouvert|ouverte|ordre emis)$/.test(state)) return { tone: 'info', complete: false };
        return { tone: 'neutral', complete: false };
    }
    function paintStates(table) {
        if (!table.tHead || !table.tBodies.length) return;
        const columns = Array.from(table.tHead.rows[0].cells).map(function (cell, index) { return /^(statut|etat)(\s|$)/.test(normalizeState(cell.textContent)) ? index : -1; }).filter(function (index) { return index >= 0; });
        if (!columns.length) return;
        const completeStates = json(table.dataset.registerCompleteStates, []).map(normalizeState);
        Array.from(table.tBodies[0].rows).forEach(function (row) {
            if (row.cells.length !== table.tHead.rows[0].cells.length) return;
            let complete = row.dataset.registerComplete === 'true';
            columns.forEach(function (index) {
                const cell = row.cells[index];
                const value = cell.dataset.registerState || row.dataset.registerState || row.dataset.requestState || cell.textContent;
                const state = stateAppearance(value);
                cell.classList.add('register-state-cell'); cell.dataset.registerTone = state.tone;
                if (row.dataset.registerComplete !== 'false') complete = complete || state.complete || completeStates.includes(normalizeState(value));
            });
            row.classList.toggle('register-row-complete', complete);
        });
    }
    function button(label, icon) {
        const element = document.createElement('button'); element.type = 'button';
        element.className = 'btn btn-sm btn-outline-secondary';
        const symbol = document.createElement('i'); symbol.className = 'bi ' + icon + ' me-1';
        element.append(symbol, document.createTextNode(label)); return element;
    }
    function progressive(form) {
        if (form.dataset.registerEnhanced) return;
        form.dataset.registerEnhanced = 'true'; form.classList.add('register-filters');
        const grid = form.matches('.row') ? form : form.querySelector('.row');
        if (!grid) return;
        const cells = Array.from(grid.children);
        const search = grid.querySelector('[name="recherche"], [name="q"], [name="search"], input[type="search"]');
        grid.querySelectorAll('select').forEach(function (field) { if (isStateField(field)) field.setAttribute('data-register-advanced', ''); });
        const availableSelects = Array.from(grid.querySelectorAll('select')).filter(function (field) { return !field.closest('[data-register-advanced]'); });
        const status = availableSelects[0];
        const advanced = document.createElement('div'); advanced.className = 'collapse register-advanced'; advanced.id = 'register-advanced-' + (++sequence);
        const advancedGrid = document.createElement('div'); advancedGrid.className = 'row g-2 align-items-end'; advanced.append(advancedGrid);
        const toolbar = document.createElement('div'); toolbar.className = 'register-filter-actions';
        const toggle = button('Plus de filtres', 'bi-sliders');
        toggle.setAttribute('data-bs-toggle', 'collapse'); toggle.setAttribute('data-bs-target', '#' + advanced.id);
        toggle.setAttribute('aria-controls', advanced.id); toggle.setAttribute('aria-expanded', 'false');
        const badge = document.createElement('span'); badge.className = 'badge bg-primary ms-1 d-none'; toggle.append(badge);
        let count = 0;
        cells.forEach(function (cell) {
            if (cell.matches('input[type="hidden"]')) return;
            const fields = cell.querySelectorAll('input:not([type="hidden"]), select, textarea');
            const forceAdvanced = cell.matches('[data-register-advanced]') || cell.querySelector('[data-register-advanced]');
            if (fields.length && (forceAdvanced || (!(search && cell.contains(search)) && !(status && cell.contains(status)) && !cell.matches('[data-register-basic]') && !cell.querySelector('[data-register-basic]')))) {
                advancedGrid.append(cell); count++;
            } else if (!fields.length && cell.querySelector('button, a')) {
                cell.classList.add('register-existing-actions'); toolbar.append(cell);
            } else { cell.classList.add('register-basic-field'); }
        });
        form.querySelectorAll('.form-control, .form-select').forEach(function (field) {
            field.classList.add(field.matches('select') ? 'form-select-sm' : 'form-control-sm');
            if (!field.id) field.id = 'register-field-' + (++sequence);
            const label = field.parentElement.querySelector('label');
            if (label && !label.htmlFor) label.htmlFor = field.id;
            if (!label && !field.getAttribute('aria-label')) field.setAttribute('aria-label', field.placeholder || (field.options && field.options[0] ? field.options[0].textContent : field.name));
        });
        if (count) toolbar.prepend(toggle);
        grid.append(toolbar);
        if (grid === form) { const holder = document.createElement('div'); holder.className = 'col-12'; holder.append(advanced); grid.append(holder); }
        else form.append(advanced);
        function update() {
            const selected = Array.from(advanced.querySelectorAll('input, select, textarea')).filter(function (field) { return field.type !== 'hidden' && field.value !== ''; }).length;
            badge.textContent = selected; badge.classList.toggle('d-none', selected === 0);
            toggle.classList.toggle('border-primary', selected > 0);
        }
        update(); form.addEventListener('change', update); form.addEventListener('input', update);
        form.addEventListener('reset', function () { setTimeout(update, 0); });
    }
    function columnFilters(table, api, existingForm) {
        if (table.dataset.registerMode === 'server') return;
        if (existingForm) {
            if (Array.from(existingForm.querySelectorAll('select')).some(isStateField)) return;
            const advanced = existingForm.querySelector('.register-advanced');
            if (!advanced) return;
            api.columns().every(function (index) {
                const title = this.header().textContent.trim();
                if (!/^(statut|etat)(\s|$)/.test(normalizeState(title))) return;
                const values = Array.from(new Set(this.data().toArray().map(text))).filter(Boolean).sort(function (a, b) { return a.localeCompare(b, 'fr'); });
                if (!values.length) return;
                const cell = document.createElement('div'); cell.className = 'col-12 col-sm-6 col-lg-3';
                const label = document.createElement('label'); label.className = 'form-label'; label.textContent = title;
                const select = document.createElement('select'); select.className = 'form-select form-select-sm'; select.id = 'register-state-' + (++sequence); select.setAttribute('data-register-state-filter', ''); label.htmlFor = select.id;
                select.append(new Option('Tous', '')); values.forEach(function (value) { select.append(new Option(value, value)); });
                select.addEventListener('change', function () { filter(table, index, select.value, true); });
                existingForm.addEventListener('reset', function () { setTimeout(function () { filter(table, index, select.value, true); }, 0); });
                cell.append(label, select); advanced.firstElementChild.append(cell);
                if (!existingForm.querySelector('[data-bs-target="#' + advanced.id + '"]')) {
                    const toggle = button('Plus de filtres', 'bi-sliders'); toggle.setAttribute('data-bs-toggle', 'collapse'); toggle.setAttribute('data-bs-target', '#' + advanced.id); toggle.setAttribute('aria-controls', advanced.id); toggle.setAttribute('aria-expanded', 'false');
                    existingForm.querySelector('.register-filter-actions').prepend(toggle);
                }
            });
            return;
        }
        let columns = json(table.dataset.registerFilterColumns, null);
        if (!columns) {
            columns = {};
            api.columns().every(function (index) {
                const title = this.header().textContent.trim();
                if (/^(statut|état|type|urgence|service|département|rôle|devise|catégorie|mode|flux)\b/i.test(title)) columns[index] = title;
            });
        }
        const entries = Array.isArray(columns) ? columns.map(function (column) { return [column.index, column.label]; }) : Object.entries(columns);
        if (!entries.length) return;
        const form = document.createElement('form'); form.className = 'register-column-filters';
        const grid = document.createElement('div'); grid.className = 'row g-2 align-items-end'; form.append(grid);
        entries.forEach(function (entry) {
            const index = Number(entry[0]); if (index < 0 || index >= api.columns().count()) return;
            const values = Array.from(new Set(api.column(index).data().toArray().map(text))).filter(function (value) { return value && value !== '—' && value.length < 140; }).sort(function (a, b) { return a.localeCompare(b, 'fr'); });
            if (!values.length || values.length > 100) return;
            const cell = document.createElement('div'); cell.className = 'col-12 col-sm-6 col-lg-3';
            const label = document.createElement('label'); label.className = 'form-label'; label.textContent = entry[1];
            const select = document.createElement('select'); select.className = 'form-select form-select-sm'; select.id = 'register-column-' + (++sequence); select.dataset.registerColumn = index; label.htmlFor = select.id;
            if (/statut|état/i.test(entry[1])) { select.name = 'status'; select.setAttribute('data-register-state-filter', ''); }
            select.append(new Option('Tous', '')); values.forEach(function (value) { select.append(new Option(value, value)); });
            select.addEventListener('change', function () { filter(table, index, select.value, true); });
            cell.append(label, select); grid.append(cell);
        });
        if (!grid.children.length) return;
        form.addEventListener('submit', function (event) { event.preventDefault(); });
        const resetCell = document.createElement('div'); resetCell.className = 'col-auto';
        const reset = button('Réinitialiser', 'bi-arrow-counterclockwise'); reset.addEventListener('click', function () {
            form.querySelectorAll('select').forEach(function (select) { select.value = ''; api.column(Number(select.dataset.registerColumn)).search(''); });
            form.dispatchEvent(new Event('change')); api.draw();
        }); resetCell.append(reset); grid.append(resetCell);
        const wrapper = $(table).closest('.dataTables_wrapper')[0]; wrapper.prepend(form); progressive(form);
    }
    function filter(table, column, value, exact) {
        if (typeof table === 'string') table = document.querySelector(table);
        if (!table || !$.fn.dataTable.isDataTable(table)) return;
        $(table).DataTable().column(column).search(exact && value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : value, !!exact, !exact).draw();
    }
    function init(root) {
        root = root || document;
        root.querySelectorAll('form[data-register-filters]').forEach(progressive);
        const tables = root.matches && root.matches('[data-register-table]') ? [root] : Array.from(root.querySelectorAll('[data-register-table]'));
        tables.forEach(function (table) {
            if ($.fn.dataTable.isDataTable(table) || !table.tHead || !table.tBodies.length) return;
            const card = table.closest('.card') || table.parentElement;
            const form = card.querySelector('form[data-register-filters]');
            if (form) progressive(form);
            const server = table.dataset.registerMode === 'server';
            const originalIndex = Array.from(document.querySelectorAll('[data-register-table]')).indexOf(table);
            if (!table.id) table.id = 'register-table-' + originalIndex;
            const selector = '#' + CSS.escape(table.id);
            Array.from(table.tBodies[0].rows).forEach(function (row) { if (Array.from(row.cells).some(function (cell) { return cell.colSpan > 1; })) row.remove(); });
            table.classList.add('table-sm', 'compact', 'register-table', 'align-middle');
            table.tHead.classList.remove('table-dark'); table.tHead.classList.add('table-light');
            const options = $.extend(true, {
                pageLength: 15, lengthMenu: [15, 25, 50, 100], order: [], autoWidth: false,
                dom: '<"row g-2 align-items-center mb-2 register-table-tools"<"col-sm-5"l><"col-sm-7"f>>rt<"row g-2 align-items-center mt-2"<"col-md-5"i><"col-md-7"p>>',
                language: language
            }, json(table.dataset.registerOptions, {}));
            options.language = $.extend({}, language, options.language, { url: '' });
            options.pageLength = [15, 25, 50, 100].includes(Number(new URL(location.href).searchParams.get('per_page'))) ? Number(new URL(location.href).searchParams.get('per_page')) : 15;
            options.lengthMenu = [15, 25, 50, 100];
            if (server) {
                const pageSize = Number(table.dataset.registerPageSize);
                if ([15, 25, 50, 100].includes(pageSize)) options.pageLength = pageSize;
                const page = Math.max(1, Number(new URL(location.href).searchParams.get('page')) || 1);
                options.displayStart = Math.min(page - 1, Math.max(0, Math.ceil(Number(table.dataset.registerTotal || 0) / options.pageLength) - 1)) * options.pageLength;
            }
            if (table.dataset.registerOrder) options.order = json(table.dataset.registerOrder, []);
            const savedState = options.stateLoadParams;
            options.stateLoadParams = function (settings, state) { state.length = options.pageLength; if (savedState) savedState(settings, state); };
            const actionColumns = Array.from(table.tHead.rows[0].cells).map(function (cell, index) { return /actions?|détail|prochaine action/i.test(cell.textContent) ? index : -1; }).filter(function (index) { return index >= 0; });
            options.columnDefs = (options.columnDefs || []).concat([{ targets: actionColumns, orderable: false }]);
            if (server) {
                options.serverSide = true; options.processing = true; options.ordering = false; options.searching = false;
                options.deferLoading = Number(table.dataset.registerTotal || 0);
                const originalCreatedRow = options.createdRow;
                options.createdRow = function (row, data, index, cells) {
                    const metadata = data._registerMetadata;
                    if (metadata) {
                        metadata.row.forEach(function (attribute) { row.setAttribute(attribute[0], attribute[1]); });
                        metadata.cells.forEach(function (attributes, cellIndex) { attributes.forEach(function (attribute) { cells[cellIndex].setAttribute(attribute[0], attribute[1]); }); });
                    }
                    if (originalCreatedRow) originalCreatedRow(row, data, index, cells);
                };
                options.ajax = function (request, callback) {
                    const url = new URL(location.href); url.searchParams.delete('export');
                    url.searchParams.set('page', Math.floor(request.start / request.length) + 1);
                    url.searchParams.set('per_page', request.length);
                    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (response) {
                        if (!response.ok) throw new Error('Chargement impossible.'); return response.text();
                    }).then(function (html) {
                        const page = new DOMParser().parseFromString(html, 'text/html');
                        const incoming = page.querySelector(selector) || page.querySelectorAll('[data-register-table]')[originalIndex];
                        if (!incoming) throw new Error('Registre indisponible. Actualisez la page.');
                        const rows = Array.from(incoming.tBodies[0].rows).filter(function (row) { return row.cells.length === api.columns().count() && !Array.from(row.cells).some(function (cell) { return cell.colSpan > 1; }); });
                        const total = Number(incoming.dataset.registerTotal || 0);
                        const attributes = function (element) { return Array.from(element.attributes).filter(function (attribute) { return /^(class|style|title|data-[\w-]+|aria-[\w-]+)$/.test(attribute.name); }).map(function (attribute) { return [attribute.name, attribute.value]; }); };
                        callback({ draw: request.draw, recordsTotal: total, recordsFiltered: total, data: rows.map(function (row) {
                            const data = Array.from(row.cells, function (cell) { return cell.innerHTML; });
                            data._registerMetadata = { row: attributes(row), cells: Array.from(row.cells, attributes) };
                            return data;
                        }) });
                        const error = card.querySelector('.register-error'); if (error) error.remove();
                    }).catch(function () {
                        callback({ draw: request.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        if (!card.querySelector('.register-error')) { const error = document.createElement('div'); error.className = 'alert alert-warning register-error m-2'; error.setAttribute('role', 'alert'); error.textContent = 'Le registre n’a pas pu être chargé. Actualisez la page pour réessayer.'; card.prepend(error); }
                    });
                };
                card.querySelectorAll('[data-register-pagination]').forEach(function (element) { element.classList.add('d-none'); });
            } else if (form && form.querySelector('[name="recherche"], input[type="search"]')) options.dom = options.dom.replace('<"col-sm-7"f>', '');
            const api = $(table).DataTable(options);
            paintStates(table);
            card.classList.add('register-card');
            if (form && form.dataset.registerClient !== 'true' && form.querySelector('[data-register-column]')) {
                form.addEventListener('submit', function (event) { event.preventDefault(); });
                const search = form.querySelector('[name="recherche"], input[type="search"]');
                if (search) search.addEventListener('input', function () { api.search(search.value).draw(); });
                form.querySelectorAll('[data-register-column]').forEach(function (field) { field.addEventListener('change', function () { filter(table, Number(field.dataset.registerColumn), field.value, true); }); });
            }
            columnFilters(table, api, form);
        });
    }
    function destroy(table) {
        if (typeof table === 'string') table = document.querySelector(table);
        if (table && $.fn.dataTable.isDataTable(table)) $(table).DataTable().destroy();
    }
    window.AMMRegisters = { init: init, destroy: destroy, filter: filter, paintStates: paintStates };
    $(document).on('init.dt.registerStates draw.dt.registerStates', function (event, settings) { if (settings && settings.nTable) paintStates(settings.nTable); });
    const printState = new Map();
    window.addEventListener('beforeprint', function () {
        document.querySelectorAll('[data-register-table]').forEach(function (table) {
            if (!$.fn.dataTable.isDataTable(table) || table.dataset.registerMode === 'server') return;
            const api = $(table).DataTable();
            if (!printState.has(table)) printState.set(table, { length: api.page.len(), page: api.page() });
            api.page.len(-1).draw(false);
        });
    });
    window.addEventListener('afterprint', function () {
        printState.forEach(function (state, table) {
            if ($.fn.dataTable.isDataTable(table)) $(table).DataTable().page.len(state.length).draw(false).page(state.page).draw('page');
        });
        printState.clear();
    });
    $(function () { init(document); });
    document.addEventListener('shown.bs.modal', function (event) { init(event.target); $(event.target).find('[data-register-table]').each(function () { if ($.fn.dataTable.isDataTable(this)) $(this).DataTable().columns.adjust(); }); });
    document.addEventListener('shown.bs.tab', function () { $('[data-register-table]').each(function () { if ($.fn.dataTable.isDataTable(this)) $(this).DataTable().columns.adjust(); }); });
})(window, document, window.jQuery);
