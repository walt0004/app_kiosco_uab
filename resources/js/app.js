import './bootstrap';
import Alpine from 'alpinejs';
import pos from './pos';

Alpine.data('pos', pos);

window.Alpine = Alpine;
Alpine.start();

// DataTables y Chart.js son pesados: solo se cargan en las páginas que los usan.
async function loadOptionalModules() {
    if (document.querySelector('table[data-datatable]')) {
        const { initDataTables } = await import('./datatable');
        initDataTables();
    }

    if (document.querySelector('canvas[data-chart]')) {
        const { initCharts } = await import('./charts');
        initCharts();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadOptionalModules);
} else {
    loadOptionalModules();
}
