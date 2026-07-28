/**
 * AMM-ERP - Script JavaScript principal
 * Lubumbashi, RDC
 */

'use strict';

// ============================================================
// CONFIGURATION GLOBALE DATATABLES
// ============================================================
if (typeof $.fn !== 'undefined' && $.fn.DataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
        },
        pageLength: 25,
        responsive: true,
        dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>rt<"row mt-2"<"col-sm-6"i><"col-sm-6"p>>',
    });
}

// ============================================================
// UTILITAIRES AJAX
// ============================================================

/**
 * Effectue une requête POST AJAX et retourne une promesse.
 * @param {string} url
 * @param {Object} data
 * @returns {Promise}
 */
function ajaxPost(url, data = {}) {
    const formData = new FormData();
    for (const [key, val] of Object.entries(data)) {
        formData.append(key, val);
    }
    return fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    }).then(r => {
        if (!r.ok) throw new Error('Erreur réseau: ' + r.status);
        return r.json();
    });
}

// ============================================================
// NOTIFICATIONS
// ============================================================

/**
 * Rafraîchit le compteur de notifications dans la navbar.
 */
function rafraichirCompteurNotifs() {
    fetch(APP_URL + '/ajax/notifications.php')
        .then(r => r.json())
        .then(data => {
            const nonLues = data.filter(n => n.est_lu == 0).length;
            const badge   = document.querySelector('#btnNotifs .badge');
            if (nonLues > 0) {
                if (!badge) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    newBadge.textContent = nonLues > 99 ? '99+' : nonLues;
                    document.getElementById('btnNotifs').appendChild(newBadge);
                } else {
                    badge.textContent = nonLues > 99 ? '99+' : nonLues;
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(() => {});
}

// Rafraîchir les notifs toutes les 60 secondes
setInterval(rafraichirCompteurNotifs, 60000);

// ============================================================
// CONFIRMATION AVANT ACTIONS CRITIQUES
// ============================================================
document.addEventListener('DOMContentLoaded', function() {

    // Recherche globale accessible au clavier (Cmd/Ctrl + K).
    document.addEventListener('keydown', function(event) {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            const search = document.querySelector('.app-search-form input[type="search"]');
            if (search) search.focus();
        }
    });

    // -----------------------------------------------------------
    // MODE COMPACT (densite interface)
    // -----------------------------------------------------------
    const densityKey = 'amm_density_mode';
    const densityBtn = document.getElementById('btnDensityToggle');

    const applyDensity = (mode) => {
        if (mode === 'compact') {
            document.body.classList.add('density-compact');
            if (densityBtn) densityBtn.setAttribute('title', 'Mode compact actif');
        } else {
            document.body.classList.remove('density-compact');
            if (densityBtn) densityBtn.setAttribute('title', 'Basculer le mode compact');
        }
    };

    const savedDensity = localStorage.getItem(densityKey) || 'normal';
    applyDensity(savedDensity);

    if (densityBtn) {
        densityBtn.addEventListener('click', function() {
            const isCompact = document.body.classList.contains('density-compact');
            const nextMode = isCompact ? 'normal' : 'compact';
            localStorage.setItem(densityKey, nextMode);
            applyDensity(nextMode);
        });
    }

    // Ajouter une confirmation sur les liens/boutons data-confirm
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            const message = el.getAttribute('data-confirm') || 'Confirmer cette action ?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Initialiser les tooltips Bootstrap
    const tooltipEls = document.querySelectorAll('[title]');
    tooltipEls.forEach(el => {
        if (el.tagName === 'A' || el.tagName === 'BUTTON') {
            new bootstrap.Tooltip(el, { trigger: 'hover' });
        }
    });

    // Mise en surbrillance des lignes de tableau survolées
    document.querySelectorAll('.table tbody tr').forEach(function(row) {
        row.style.cursor = 'pointer';
        row.addEventListener('dblclick', function() {
            const lien = row.querySelector('a');
            if (lien) window.location.href = lien.href;
        });
    });

    // Clic sur composants dashboard pour ouvrir les details en modal.
    document.querySelectorAll('[data-dashboard-detail]').forEach(function(el) {
        el.classList.add('cursor-pointer');
        el.addEventListener('click', function() {
            const key = el.getAttribute('data-dashboard-detail');
            if (!key || !window.dashboardDetailData || !window.dashboardDetailData[key]) {
                return;
            }
            const config = Object.assign({}, window.dashboardDetailData[key]);
            // Capturer la couleur du KPI pour l'accent du modal
            const valueEl = el.querySelector('.kpi-value');
            if (valueEl) {
                const colorCls = Array.from(valueEl.classList).find(function(c) { return c.startsWith('text-'); });
                if (colorCls) { config._color = colorCls.replace('text-', ''); }
            }
            openDashboardDetailModal(config);
        });
    });

});

// ============================================================
// FONCTIONS EXPORTATION CSV
// ============================================================

/**
 * Télécharge le tableau HTML actuel en CSV.
 * @param {string} tableId  ID du tableau HTML
 * @param {string} nomFichier Nom du fichier sans extension
 */
function exporterCSV(tableId, nomFichier = 'export') {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    const csv  = [];

    rows.forEach(row => {
        if (row.dataset.exportSkip === '1') return;
        if (row.classList.contains('d-none')) return;
        const cells = row.querySelectorAll('th:not([data-export-skip]), td:not([data-export-skip])');
        const rowData = Array.from(cells).map(cell => {
            const rawValue = cell.dataset.exportValue !== undefined
                ? cell.dataset.exportValue
                : cell.textContent.trim();
            const value = String(rawValue).trim().replace(/\s+/g, ' ');
            if (/^-?\d+(\.\d+)?$/.test(value)) {
                return value.replace('.', ',');
            }
            return '"' + value.replace(/"/g, '""') + '"';
        });
        csv.push(rowData.join(';'));
    });

    const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href  = url;
    link.setAttribute('download', nomFichier + '_' + new Date().toISOString().slice(0,10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ============================================================
// TABLEAUX COMPACTS TRESORERIE
// ============================================================
function initTreasuryMovementTables() {
    document.querySelectorAll('[data-movement-controls]').forEach(function(controls) {
        const tableId = controls.getAttribute('data-movement-controls');
        const table = document.getElementById(tableId);
        if (!table) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const startInput = controls.querySelector('[data-filter-start]');
        const endInput = controls.querySelector('[data-filter-end]');
        const sortSelect = controls.querySelector('[data-sort-order]');
        const modeSelect = controls.querySelector('[data-display-mode]');
        const resetButton = controls.querySelector('[data-reset-filters]');
        const pager = document.querySelector('[data-pagination-for="' + tableId + '"]');
        const pageInfo = pager ? pager.querySelector('[data-page-info]') : null;
        const prevButton = pager ? pager.querySelector('[data-page-prev]') : null;
        const nextButton = pager ? pager.querySelector('[data-page-next]') : null;
        const pageSize = parseInt(controls.getAttribute('data-page-size') || '12', 10);
        let currentPage = 1;

        function masterRows() {
            return Array.from(tbody.querySelectorAll('tr.movement-master-row'));
        }

        function detailRowFor(row) {
            const target = row.querySelector('[data-bs-target]');
            if (!target) return null;
            return tbody.querySelector(target.getAttribute('data-bs-target'));
        }

        function rowDateInRange(row) {
            const date = row.dataset.date || '';
            const start = startInput ? startInput.value : '';
            const end = endInput ? endInput.value : '';
            if (start && date < start) return false;
            if (end && date > end) return false;
            return true;
        }

        function applyTableState() {
            const sortDirection = sortSelect ? sortSelect.value : 'asc';
            const mode = modeSelect ? modeSelect.value : 'continuous';
            const rows = masterRows().sort(function(a, b) {
                const comparison = (a.dataset.sort || '').localeCompare(b.dataset.sort || '');
                return sortDirection === 'desc' ? -comparison : comparison;
            });

            rows.forEach(function(row) {
                const detail = detailRowFor(row);
                tbody.appendChild(row);
                if (detail) tbody.appendChild(detail);
            });

            const filtered = rows.filter(rowDateInRange);
            const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;

            rows.forEach(function(row) {
                const visibleByDate = filtered.includes(row);
                let visibleByPage = true;
                if (mode === 'paginated') {
                    const index = filtered.indexOf(row);
                    visibleByPage = index >= (currentPage - 1) * pageSize && index < currentPage * pageSize;
                }
                const visible = visibleByDate && visibleByPage;
                row.classList.toggle('d-none', !visible);
                const detail = detailRowFor(row);
                if (detail) {
                    detail.classList.toggle('d-none', !visible);
                    if (!visible && detail.classList.contains('show') && window.bootstrap) {
                        bootstrap.Collapse.getOrCreateInstance(detail, { toggle: false }).hide();
                    }
                }
            });

            if (pager) {
                pager.classList.toggle('d-none', mode !== 'paginated');
            }
            if (pageInfo) {
                pageInfo.textContent = filtered.length + ' mouvement(s)' + (mode === 'paginated' ? ' - page ' + currentPage + '/' + totalPages : '');
            }
            if (prevButton) prevButton.disabled = currentPage <= 1;
            if (nextButton) nextButton.disabled = currentPage >= totalPages;
        }

        [startInput, endInput, sortSelect, modeSelect].forEach(function(input) {
            if (!input) return;
            input.addEventListener('change', function() {
                currentPage = 1;
                applyTableState();
            });
        });

        if (resetButton) {
            resetButton.addEventListener('click', function() {
                if (startInput) startInput.value = startInput.defaultValue || '';
                if (endInput) endInput.value = endInput.defaultValue || '';
                if (sortSelect) sortSelect.value = 'asc';
                if (modeSelect) modeSelect.value = 'continuous';
                currentPage = 1;
                applyTableState();
            });
        }
        if (prevButton) {
            prevButton.addEventListener('click', function() {
                currentPage = Math.max(1, currentPage - 1);
                applyTableState();
            });
        }
        if (nextButton) {
            nextButton.addEventListener('click', function() {
                currentPage += 1;
                applyTableState();
            });
        }

        applyTableState();
    });
}

document.addEventListener('DOMContentLoaded', initTreasuryMovementTables);

/**
 * Exporte un dataset tabulaire en CSV.
 * @param {Array<string>} headers
 * @param {Array<Array<string|number|null>>} rows
 * @param {string} nomFichier
 */
function exporterCSVMatrix(headers, rows, nomFichier = 'export') {
    const csv = [];
    csv.push(headers.map(h => '"' + String(h).replace(/"/g, '""') + '"').join(';'));

    rows.forEach(row => {
        const line = row.map(cell => '"' + String(cell == null ? '' : cell).replace(/"/g, '""') + '"').join(';');
        csv.push(line);
    });

    const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href  = url;
    link.setAttribute('download', nomFichier + '_' + new Date().toISOString().slice(0,10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Ouvre une vue imprimable du tableau pour export PDF via le navigateur.
 * @param {string} title
 * @param {Array<string>} headers
 * @param {Array<Array<string|number|null>>} rows
 */
function exporterPDFMatrix(title, headers, rows) {
    const escapeHtml = function(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const headHtml = headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
    const bodyHtml = rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('');

    const popup = window.open('', '_blank');
    if (!popup) {
        alert('Veuillez autoriser les popups pour exporter en PDF.');
        return;
    }

    popup.document.write(`
        <!doctype html>
        <html lang="fr">
        <head>
            <meta charset="utf-8" />
            <title>${escapeHtml(title || 'Details dashboard')}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 22px; color: #1d2d3d; }
                h1 { font-size: 18px; margin: 0 0 10px; }
                .meta { color: #5b6f82; font-size: 12px; margin-bottom: 12px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th, td { border: 1px solid #d4deea; padding: 6px 8px; text-align: left; vertical-align: top; }
                th { background: #eef4fb; }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <h1>${escapeHtml(title || 'Details dashboard')}</h1>
            <div class="meta">Genere le ${new Date().toLocaleString()}</div>
            <table>
                <thead><tr>${headHtml}</tr></thead>
                <tbody>${bodyHtml}</tbody>
            </table>
            <script>
                window.onload = function() {
                    window.print();
                };
            <\/script>
        </body>
        </html>
    `);
    popup.document.close();
}

// ============================================================
// BADGES POUR LES MODALES DÉTAIL DASHBOARD
// ============================================================

function _dsdBadge(bg, color, label) {
    return '<span class="dsd-badge" style="background:' + bg + ';color:' + color + ';border:1px solid ' + color + '44">'
         + label + '</span>';
}

function badgeCellUrgence(val) {
    var v = String(val || '').toLowerCase();
    var map = {
        'haute':   { c: '#dc3545', bg: 'rgba(220,53,69,.12)',    dot: true, label: 'Haute'   },
        'moyenne': { c: '#fd7e14', bg: 'rgba(253,126,20,.12)',    dot: true, label: 'Moyenne' },
        'basse':   { c: '#6c757d', bg: 'rgba(108,117,125,.12)',   dot: true, label: 'Basse'   },
    };
    var s = map[v] || { c: '#6c757d', bg: 'rgba(108,117,125,.12)', dot: false, label: val || '-' };
    var dot = s.dot
        ? '<span style="width:6px;height:6px;border-radius:50%;background:' + s.c + ';display:inline-block;margin-right:5px;flex-shrink:0"></span>'
        : '';
    return '<span class="dsd-badge dsd-badge-dot" style="background:' + s.bg + ';color:' + s.c + ';border:1px solid ' + s.c + '44">'
         + dot + s.label + '</span>';
}

function badgeCellStatut(val) {
    var v = String(val || '');
    var map = {
        // Courriers
        'Recu':     { c: '#0989ab', bg: 'rgba(13,202,240,.12)', label: 'Reçu'     },
        'Assigne':  { c: '#0d6efd', bg: 'rgba(13,110,253,.12)', label: 'Assigné'  },
        'Accuse':   { c: '#fd7e14', bg: 'rgba(253,126,20,.12)', label: 'Accusé'   },
        'En_cours': { c: '#fd7e14', bg: 'rgba(253,126,20,.12)', label: 'En cours' },
        'Termine':  { c: '#198754', bg: 'rgba(25,135,84,.12)',  label: 'Terminé'  },
        'Clos':     { c: '#198754', bg: 'rgba(25,135,84,.12)',  label: 'Clos'     },
        'Archive':  { c: '#6c757d', bg: 'rgba(108,117,125,.12)',label: 'Archivé'  },
        'Overdue':  { c: '#dc3545', bg: 'rgba(220,53,69,.12)',  label: 'Overdue'  },
        // Contrats
        'actif':     { c: '#198754', bg: 'rgba(25,135,84,.12)',  label: 'Actif'     },
        'expire':    { c: '#dc3545', bg: 'rgba(220,53,69,.12)',  label: 'Expiré'    },
        'resilie':   { c: '#6c757d', bg: 'rgba(108,117,125,.12)',label: 'Résilié'   },
        'renouvele': { c: '#0989ab', bg: 'rgba(13,202,240,.12)', label: 'Renouvelé' },
    };
    var s = map[v] || { c: '#6c757d', bg: 'rgba(108,117,125,.12)', label: v || '-' };
    return _dsdBadge(s.bg, s.c, s.label);
}

/**
 * Affiche un modal de details dashboard avec design pro.
 * config.{title, columns, rows, links?, exportName?, _color?}
 */
function openDashboardDetailModal(config) {
    if (!config || !Array.isArray(config.columns) || !Array.isArray(config.rows)) { return; }

    // Palette couleurs selon le KPI cliqué
    var pal = ({
        primary: { accent: '#0d6efd', bg: 'rgba(13,110,253,.06)',  text: '#084298' },
        warning: { accent: '#e67e00', bg: 'rgba(253,126,20,.07)',   text: '#7d3c00' },
        danger:  { accent: '#dc3545', bg: 'rgba(220,53,69,.06)',    text: '#842029' },
        info:    { accent: '#0989ab', bg: 'rgba(13,202,240,.07)',   text: '#055160' },
        success: { accent: '#198754', bg: 'rgba(25,135,84,.06)',    text: '#0a3622' },
    })[config._color] || { accent: '#0d6efd', bg: 'rgba(13,110,253,.06)', text: '#084298' };

    // Créer le modal DOM une seule fois
    if (!document.getElementById('dashboardDetailModal')) {
        var el = document.createElement('div');
        el.className = 'modal fade';
        el.id = 'dashboardDetailModal';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML =
            '<div class="modal-dialog modal-xl modal-dialog-scrollable">' +
            '  <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">' +
            '    <div id="dsdAccent" style="height:4px"></div>' +
            '    <div class="modal-header py-3 px-4 border-0" id="dsdHeader">' +
            '      <div class="d-flex align-items-start gap-3 flex-grow-1">' +
            '        <div class="dsd-icon-wrap d-flex align-items-center justify-content-center rounded-3" id="dsdIconWrap" style="width:40px;height:40px;flex-shrink:0">' +
            '          <i id="dsdIcon" class="bi fs-5"></i>' +
            '        </div>' +
            '        <div class="flex-grow-1">' +
            '          <h5 class="modal-title fw-bold mb-0 lh-sm" id="dashboardDetailModalTitle"></h5>' +
            '          <small id="dsdSubtitle" class="opacity-60 mt-1 d-block"></small>' +
            '        </div>' +
            '      </div>' +
            '      <button type="button" class="btn-close ms-3" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '    </div>' +
            '    <div class="px-4 py-2 border-bottom bg-light">' +
            '      <div class="input-group input-group-sm">' +
            '        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>' +
            '        <input type="search" class="form-control bg-white border-start-0 ps-0" id="dsdSearchInput" placeholder="Filtrer dans les résultats...">' +
            '      </div>' +
            '    </div>' +
            '    <div class="modal-body p-0">' +
            '      <div id="dsdEmptyState" class="text-center py-5 d-none">' +
            '        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted opacity-25"></i>' +
            '        <p class="text-muted mb-0 fw-semibold small">Aucune donnée disponible</p>' +
            '      </div>' +
            '      <div id="dsdTableWrap" class="table-responsive">' +
            '        <table class="table table-hover align-middle mb-0" id="dashboardDetailModalTable"></table>' +
            '      </div>' +
            '    </div>' +
            '    <div class="modal-footer px-4 py-3 d-flex justify-content-between align-items-center" style="background:#f8fafc">' +
            '      <div class="d-flex gap-2">' +
            '        <button type="button" class="btn btn-sm btn-outline-primary" id="btnDashboardDetailExport">' +
            '          <i class="bi bi-filetype-csv me-1"></i>CSV' +
            '        </button>' +
            '        <button type="button" class="btn btn-sm btn-outline-danger" id="btnDashboardDetailExportPdf">' +
            '          <i class="bi bi-filetype-pdf me-1"></i>PDF' +
            '        </button>' +
            '      </div>' +
            '      <small id="dsdRowCount" class="text-muted"></small>' +
            '      <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">' +
            '        Fermer' +
            '      </button>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(el);
    }

    var titleEl     = document.getElementById('dashboardDetailModalTitle');
    var tableEl     = document.getElementById('dashboardDetailModalTable');
    var tableWrap   = document.getElementById('dsdTableWrap');
    var exportBtn   = document.getElementById('btnDashboardDetailExport');
    var exportPdfBtn= document.getElementById('btnDashboardDetailExportPdf');
    var accentEl    = document.getElementById('dsdAccent');
    var headerEl    = document.getElementById('dsdHeader');
    var iconWrapEl  = document.getElementById('dsdIconWrap');
    var iconEl      = document.getElementById('dsdIcon');
    var subtitleEl  = document.getElementById('dsdSubtitle');
    var emptyEl     = document.getElementById('dsdEmptyState');
    var searchInput = document.getElementById('dsdSearchInput');
    var rowCountEl  = document.getElementById('dsdRowCount');

    // Appliquer la couleur d'accentuation
    accentEl.style.background            = pal.accent;
    headerEl.style.background            = 'linear-gradient(120deg,' + pal.bg + ' 0%,rgba(255,255,255,0) 70%)';
    iconWrapEl.style.background          = pal.bg;
    iconEl.style.color                   = pal.accent;
    iconEl.className                     = 'bi fs-5 ' + (config._iconClass || 'bi-bar-chart-line');

    // Textes
    titleEl.textContent   = config.title || 'Détails';
    var total             = config.rows.length;
    subtitleEl.textContent = total + ' enregistrement' + (total !== 1 ? 's' : '');
    rowCountEl.textContent = total + ' résultat' + (total !== 1 ? 's' : '');
    searchInput.value      = '';

    // Escape HTML
    var esc = function(v) {
        return String(v == null ? '' : v)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    };

    var hasLinks = Array.isArray(config.links) && config.links.some(Boolean);

    if (total === 0) {
        emptyEl.classList.remove('d-none');
        tableWrap.classList.add('d-none');
    } else {
        emptyEl.classList.add('d-none');
        tableWrap.classList.remove('d-none');

        var thStyle = 'position:sticky;top:0;z-index:1;'
            + 'background:' + pal.bg + ';'
            + 'color:' + pal.text + ';'
            + 'font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;'
            + 'white-space:nowrap;font-weight:700;'
            + 'border-bottom:2px solid ' + pal.accent + ';padding:.7rem 1rem;';

        var headersHtml = config.columns.map(function(col) {
            return '<th style="' + thStyle + '">' + esc(col) + '</th>';
        }).join('');
        if (hasLinks) {
            headersHtml += '<th style="' + thStyle + 'width:36px"></th>';
        }

        var bodyHtml = config.rows.map(function(row, i) {
            var link = hasLinks ? (config.links[i] || '') : '';
            var cells = row.map(function(cell, ci) {
                var colName = (config.columns[ci] || '').toLowerCase().trim();
                var content;
                if (colName === 'urgence') {
                    content = badgeCellUrgence(cell);
                } else if (colName === 'statut') {
                    content = badgeCellStatut(cell);
                } else {
                    content = esc(cell);
                }
                return '<td style="padding:.6rem 1rem;border-color:rgba(0,0,0,.04);font-size:.875rem">' + content + '</td>';
            }).join('');
            if (link) {
                return '<tr class="dsd-row-link" data-detail-link="' + esc(link) + '">'
                    + cells
                    + '<td style="padding:.6rem .75rem;text-align:right;width:36px">'
                    + '<i class="bi bi-arrow-right-short dsd-arrow" style="font-size:1.2rem;color:#adb5bd;transition:transform .15s,color .15s"></i>'
                    + '</td></tr>';
            }
            return '<tr>' + cells + '</tr>';
        }).join('');

        tableEl.innerHTML = '<thead><tr>' + headersHtml + '</tr></thead><tbody>' + bodyHtml + '</tbody>';

        // Hover arrow animation
        tableEl.querySelectorAll('.dsd-row-link').forEach(function(row) {
            var arrow = row.querySelector('.dsd-arrow');
            row.style.cursor = 'pointer';
            row.addEventListener('mouseenter', function() {
                if (arrow) { arrow.style.transform = 'translateX(4px)'; arrow.style.color = pal.accent; }
            });
            row.addEventListener('mouseleave', function() {
                if (arrow) { arrow.style.transform = ''; arrow.style.color = '#adb5bd'; }
            });
            row.addEventListener('click', function() {
                var link = row.getAttribute('data-detail-link');
                if (link) window.location.href = link;
            });
        });
    }

    // Recherche live
    searchInput.oninput = function() {
        var term = this.value.toLowerCase().trim();
        var rows = tableEl.querySelectorAll('tbody tr');
        var visible = 0;
        rows.forEach(function(row) {
            var show = !term || row.textContent.toLowerCase().includes(term);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        rowCountEl.textContent = (term ? visible + ' / ' : '') + total + ' résultat' + (total !== 1 ? 's' : '');
    };

    // Exports
    exportBtn.onclick = function() {
        exporterCSVMatrix(config.columns, config.rows, config.exportName || 'export_dashboard');
    };
    exportPdfBtn.onclick = function() {
        exporterPDFMatrix(config.title || 'Détails', config.columns, config.rows);
    };

    bootstrap.Modal.getOrCreateInstance(document.getElementById('dashboardDetailModal')).show();
}

// ============================================================
// CHARGEMENT AVEC FEEDBACK VISUEL
// ============================================================

/**
 * Affiche un toast Bootstrap avec un message.
 * @param {string} message
 * @param {string} type 'success' | 'danger' | 'warning' | 'info'
 */
function afficherToast(message, type = 'success') {
    // Créer le conteneur si inexistant
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position:fixed;top:70px;right:20px;z-index:9999;';
        document.body.appendChild(container);
    }

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${type} border-0 show mb-2`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toastEl);

    // Supprimer automatiquement après 4 secondes
    setTimeout(() => toastEl.remove(), 4000);
}

// ============================================================
// VARIABLE GLOBALE APP_URL (injectée depuis PHP via footer)
// ============================================================
// La variable APP_URL est définie dans le footer.php via une balise script
// Pour l'utiliser, assurez-vous que footer.php contient :
// <script>const APP_URL = '<?= APP_URL ?>';</script>
