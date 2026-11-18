{{--
    Shows at most 10 rows per page in every table (class "table") that has more, with page links underneath.
    Tables paginated by the server already have 10 or fewer rows, so nothing changes for them.
    - Opt out with <table data-no-paginate>.
    - Rows a page hides with its own filter (class "d-none") aren't counted; after filtering, call
      window.paginateTable(table, 1) to start again from page 1.
    - Printing always shows every row.
--}}
<style>
    .tp-hidden { display: none !important; }
    .tp-nav { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; margin-top: .75rem; }
    .tp-nav .pagination { margin: 0; }
    .tp-nav .page-link { min-width: 2rem; text-align: center; cursor: pointer; }
    @media print {
        .tp-hidden { display: table-row !important; }
        .tp-nav { display: none !important; }
    }
</style>
<script>
(() => {
    const PER_PAGE = 10;
    // Skip "No records found" style rows (a single cell spanning the table).
    const isDataRow = (row) => !row.hasAttribute('data-paginate-ignore') && !(row.cells.length === 1 && row.cells[0].colSpan > 1);

    const pageList = (current, total) => {
        const pages = new Set([1, total, current - 1, current, current + 1]);
        const sorted = [...pages].filter((p) => p >= 1 && p <= total).sort((a, b) => a - b);
        const out = [];
        sorted.forEach((p, i) => { if (i && p - sorted[i - 1] > 1) out.push('…'); out.push(p); });
        return out;
    };

    function paginate(table, page = 1) {
        const body = table.tBodies[0];
        if (!body) return;
        const rows = [...body.rows].filter(isDataRow);
        rows.forEach((row) => row.classList.remove('tp-hidden'));
        const visible = rows.filter((row) => !row.classList.contains('d-none'));
        const pages = Math.ceil(visible.length / PER_PAGE);

        if (pages <= 1) {
            table._tpNav?.remove();
            table._tpNav = null;
            return;
        }

        page = Math.min(Math.max(1, page), pages);
        table.dataset.tpPage = page;
        visible.forEach((row, i) => {
            if (i < (page - 1) * PER_PAGE || i >= page * PER_PAGE) row.classList.add('tp-hidden');
        });

        if (!table._tpNav) {
            table._tpNav = document.createElement('nav');
            table._tpNav.className = 'tp-nav';
            table._tpNav.setAttribute('aria-label', 'Table pages');
            (table.closest('.table-responsive') || table).after(table._tpNav);
            table._tpNav.addEventListener('click', (event) => {
                const target = event.target.closest('[data-page]');
                if (!target || target.closest('.disabled')) return;
                event.preventDefault();
                paginate(table, Number(target.dataset.page));
                (table.closest('.card, section') || table).scrollIntoView({ block: 'nearest' });
            });
        }

        const first = (page - 1) * PER_PAGE + 1;
        const last = Math.min(page * PER_PAGE, visible.length);
        const item = (label, target, { active = false, disabled = false, aria = '' } = {}) =>
            `<li class="page-item${active ? ' active' : ''}${disabled ? ' disabled' : ''}">` +
            (target === null
                ? `<span class="page-link">${label}</span>`
                : `<a class="page-link" href="#" data-page="${target}"${active ? ' aria-current="page"' : ''}${aria ? ` aria-label="${aria}"` : ''}>${label}</a>`) +
            '</li>';

        table._tpNav.innerHTML =
            `<span class="small text-muted">Showing ${first}–${last} of ${visible.length}</span>` +
            '<ul class="pagination pagination-sm">' +
            item('&lsaquo;', page - 1, { disabled: page === 1, aria: 'Previous page' }) +
            pageList(page, pages).map((p) => (p === '…' ? item('…', null, { disabled: true }) : item(p, p, { active: p === page }))).join('') +
            item('&rsaquo;', page + 1, { disabled: page === pages, aria: 'Next page' }) +
            '</ul>';
    }

    window.paginateTable = (table, page) => paginate(table, page ?? (Number(table.dataset.tpPage) || 1));
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('table.table:not([data-no-paginate])').forEach((table) => paginate(table));
    });
})();
</script>
