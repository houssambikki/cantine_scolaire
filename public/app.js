function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    const q = input.value.toLowerCase();
    const rows = Array.from(table.querySelectorAll('tr')).slice(1); // skip header
    rows.forEach(tr => {
        const text = tr.innerText.toLowerCase();
        tr.style.display = text.includes(q) ? '' : 'none';
    });
}

function filterBySelect(selectId, tableId, colIndex) {
    const select = document.getElementById(selectId);
    const table = document.getElementById(tableId);
    if (!select || !table) return;
    const val = select.value.toLowerCase();
    const rows = Array.from(table.querySelectorAll('tr')).slice(1);
    rows.forEach(tr => {
        if (!val) {
            tr.style.display = '';
            return;
        }
        const cell = tr.children[colIndex];
        const txt = (cell?.innerText || '').toLowerCase();
        tr.style.display = txt.includes(val) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Filtre commandes élève
    const cmdSearch = document.getElementById('cmd-search');
    const cmdTable = document.getElementById('cmd-table');
    const cmdStatus = document.getElementById('cmd-status');
    function applyCmdFilters() {
        const q = (cmdSearch?.value || '').toLowerCase();
        const val = (cmdStatus?.value || '').toLowerCase();
        const rows = cmdTable ? Array.from(cmdTable.querySelectorAll('tr')).slice(1) : [];
        rows.forEach(tr => {
            const text = tr.innerText.toLowerCase();
            const statut = (tr.children[4]?.innerText || '').toLowerCase();
            const matchQ = text.includes(q);
            const matchS = val ? statut.includes(val) : true;
            tr.style.display = matchQ && matchS ? '' : 'none';
        });
    }
    cmdSearch?.addEventListener('input', applyCmdFilters);
    cmdStatus?.addEventListener('change', applyCmdFilters);

    // Filtre menus élève
    const menusGrid = document.getElementById('menus-grid');
    const menusSearch = document.getElementById('menus-search');
    const menusType = document.getElementById('menus-type');
    function filterMenus() {
        if (!menusGrid) return;
        const q = (menusSearch?.value || '').toLowerCase();
        const type = (menusType?.value || '').toLowerCase();
        menusGrid.querySelectorAll('.menu-card').forEach(card => {
            const txt = card.innerText.toLowerCase();
            const t = (card.dataset.type || '').toLowerCase();
            const matchTxt = txt.includes(q);
            const matchType = type ? t === type : true;
            card.style.display = matchTxt && matchType ? '' : 'none';
        });
    }
    menusSearch?.addEventListener('input', filterMenus);
    menusType?.addEventListener('change', filterMenus);

    // Animation d'apparition
    const items = document.querySelectorAll('.card, table');
    const obs = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    items.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(12px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        obs.observe(el);
    });
});
