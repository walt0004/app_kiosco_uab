import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';

const language = {
    emptyTable: 'No hay datos para mostrar.',
    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
    infoEmpty: 'Sin registros',
    infoFiltered: '(filtrado de _MAX_ registros en total)',
    lengthMenu: 'Mostrar _MENU_ registros',
    loadingRecords: 'Cargando...',
    processing: 'Cargando...',
    search: 'Buscar:',
    zeroRecords: 'No se encontraron resultados con esa búsqueda.',
    paginate: {
        first: 'Primero',
        last: 'Último',
        next: 'Siguiente',
        previous: 'Anterior',
    },
    aria: {
        orderable: 'Ordenar por esta columna',
        orderableReverse: 'Invertir el orden',
        orderableRemove: 'Quitar el orden',
        paginate: {
            first: 'Primera página',
            last: 'Última página',
            next: 'Página siguiente',
            previous: 'Página anterior',
        },
    },
};

// Los errores no deben mostrar un alert() del navegador: se muestran dentro de la página.
DataTable.ext.errMode = 'none';

function parseJson(value, fallback) {
    try {
        return value ? JSON.parse(value) : fallback;
    } catch {
        return fallback;
    }
}

function showError(el, message) {
    const wrapper = el.closest('[data-dt-wrapper]') || el.parentElement;
    let box = wrapper.querySelector('[data-dt-error]');

    if (!box) {
        box = document.createElement('p');
        box.dataset.dtError = '1';
        box.setAttribute('role', 'alert');
        box.className = 'mb-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900';
        wrapper.prepend(box);
    }

    box.textContent = message;
}

function clearError(el) {
    const wrapper = el.closest('[data-dt-wrapper]') || el.parentElement;
    wrapper.querySelector('[data-dt-error]')?.remove();
}

/**
 * Inicializa todas las <table data-datatable>.
 *
 * Atributos admitidos:
 *  data-url            URL JSON (modo servidor). Sin ella, la tabla se mejora tal cual está en el HTML.
 *  data-columns        definición de columnas (modo servidor): [{data, title, orderable, className}]
 *  data-order          orden inicial, p. ej. [[0,"desc"]]
 *  data-filters        selector CSS de un formulario cuyos campos se envían como filtros
 *  data-page-length    filas por página (25 por defecto)
 *  data-search-placeholder  texto de ayuda del buscador
 */
export function initDataTables() {
    document.querySelectorAll('table[data-datatable]').forEach((el) => {
        if (el.dataset.dtReady) {
            return;
        }
        el.dataset.dtReady = '1';

        const form = el.dataset.filters ? document.querySelector(el.dataset.filters) : null;

        const options = {
            language: {
                ...language,
                searchPlaceholder: el.dataset.searchPlaceholder || '',
            },
            pageLength: parseInt(el.dataset.pageLength || '25', 10),
            lengthMenu: [10, 25, 50, 100],
            searchDelay: 400,
            autoWidth: false,
            order: parseJson(el.dataset.order, []),
        };

        if (el.dataset.url) {
            options.processing = true;
            options.serverSide = true;
            options.columns = parseJson(el.dataset.columns, []);
            options.ajax = {
                url: el.dataset.url,
                data(d) {
                    if (form) {
                        for (const [key, value] of new FormData(form)) {
                            d[key] = value;
                        }
                    }
                },
            };
        }

        const table = new DataTable(el, options);

        table.on('xhr.dt', (e, settings, json) => {
            clearError(el);

            document.querySelectorAll('[data-dt-summary]').forEach((node) => {
                const key = node.dataset.dtSummary;
                if (json && json.summary && key in json.summary) {
                    node.textContent = json.summary[key];
                }
            });
        });

        table.on('error.dt', () => {
            showError(el, 'No se pudieron cargar los datos. Recarga la página; si el problema sigue, vuelve a ingresar al sistema.');
        });

        if (form && el.dataset.url) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                table.draw();
            });

            form.querySelectorAll('select, input[type="date"]').forEach((field) => {
                field.addEventListener('change', () => table.draw());
            });

            form.querySelector('[data-dt-reset]')?.addEventListener('click', (event) => {
                event.preventDefault();
                form.reset();
                form.querySelectorAll('[data-default]').forEach((field) => {
                    field.value = field.dataset.default;
                });
                table.search('').draw();
            });
        }
    });
}
