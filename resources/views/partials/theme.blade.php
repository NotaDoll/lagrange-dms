<style>
    :root {
        --theme-plum: #5E1049;
        --theme-plum-hover: #4A0D39;
        --theme-pink-light: #FDF2F8;
        --theme-pink-card: #FFF5F9;
        --theme-border: #E8DEE5;
        --theme-red: #B80000;
        --theme-text-muted: #6b7280;
        --theme-text-dark: #111827;
        --theme-blue-bg: #EBF5FF;
        --theme-blue-border: #BFDBFE;
        --theme-link-blue: #3b82f6;
        --theme-pink-link: #D974B2;

        --ann-plum: var(--theme-plum);
        --ann-plum-hover: var(--theme-plum-hover);
        --ann-pink-light: var(--theme-pink-light);
        --ann-pink-card: var(--theme-pink-card);
        --ann-border: var(--theme-border);
        --ann-red: var(--theme-red);
        --ann-text-muted: var(--theme-text-muted);

        --cp-plum: var(--theme-plum);
        --cp-plum-hover: var(--theme-plum-hover);
        --cp-pink-light: var(--theme-pink-light);
        --cp-border: var(--theme-border);

        --db-plum: var(--theme-plum);
        --db-plum-light: var(--theme-pink-light);
        --db-plum-border: var(--theme-border);
        --db-red: var(--theme-red);
        --db-text-muted: var(--theme-text-muted);
        --db-text-dark: var(--theme-text-dark);
        --db-blue-bg: var(--theme-blue-bg);
        --db-blue-border: var(--theme-blue-border);
        --db-pink-link: var(--theme-pink-link);
    }

    .db-wrapper { margin-top: -20px; }
    .db-info-banner {
        background-color: var(--theme-blue-bg);
        border: 1px solid var(--theme-blue-border);
        border-radius: 6px;
        padding: 14px 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.9rem;
        color: #1e3a8a;
    }
    .db-info-banner .close-btn {
        background: none;
        border: none;
        color: #6b7280;
        font-size: 1.2rem;
        cursor: pointer;
        line-height: 1;
    }

    .theme-page-header h1,
    .ann-header h1,
    .cp-header h1,
    .db-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 4px;
    }
    .db-header h1 { margin-bottom: 25px; }
    .theme-page-header p,
    .ann-header p,
    .cp-header p {
        color: var(--theme-text-muted);
        font-size: 0.9rem;
        margin-bottom: 20px;
    }
    .cp-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 24px;
    }
    .cp-header p { margin-bottom: 0; }

    .ann-page-wrapper { display: flex; gap: 30px; flex-wrap: wrap; }
    .ann-left-col { flex: 2; min-width: 300px; }
    .ann-right-col {
        flex: 1;
        min-width: 280px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .ann-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    .ann-tabs { display: flex; gap: 20px; font-weight: 500; font-size: 0.95rem; }
    .ann-tabs a { color: #9ca3af; text-decoration: none; transition: 0.2s; }
    .ann-tabs a.active { color: #1a1a1a; font-weight: 600; }
    .ann-tabs a:hover { color: #4b5563; }
    .ann-actions { display: flex; gap: 12px; }

    .theme-btn-outline,
    .ann-btn-outline,
    .cp-filter-btn,
    .cp-btn-cancel {
        background: transparent;
        border: 1px solid var(--theme-plum);
        color: var(--theme-plum);
        border-radius: 999px;
        padding: 8px 18px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
        cursor: pointer;
        text-decoration: none;
    }
    .ann-btn-outline { border-color: #d1d5db; color: #374151; }
    .theme-btn-outline:hover,
    .cp-filter-btn:hover,
    .cp-btn-cancel:hover {
        background: var(--theme-pink-light);
        color: var(--theme-plum);
    }
    .ann-btn-outline:hover { background: #f9fafb; border-color: #9ca3af; }

    .theme-btn-solid,
    .ann-btn-solid,
    .cp-submit-btn,
    .cp-filter-apply-btn,
    .ann-filter-apply-btn,
    .cp-btn-submit {
        background: var(--theme-plum);
        border: 1px solid var(--theme-plum);
        color: #fff;
        border-radius: 999px;
        padding: 8px 18px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: 0.2s;
        cursor: pointer;
        text-decoration: none;
    }
    .theme-btn-solid:hover,
    .ann-btn-solid:hover,
    .cp-submit-btn:hover,
    .cp-filter-apply-btn:hover,
    .ann-filter-apply-btn:hover,
    .cp-btn-submit:hover {
        background: var(--theme-plum-hover);
        border-color: var(--theme-plum-hover);
        color: #fff;
    }
    .cp-submit-btn {
        border: none;
        font-size: 0.88rem;
        padding: 10px 22px;
        box-shadow: 0 2px 4px rgba(94, 16, 73, 0.15);
    }
    .cp-submit-btn svg { width: 18px; height: 18px; }
    .cp-filter-btn { padding: 6px 18px; }
    .cp-filter-btn svg { width: 14px; height: 14px; fill: currentColor; }
    .cp-filter-apply-btn,
    .ann-filter-apply-btn { border: none; width: 100%; margin-top: 15px; padding: 10px; }
    .cp-btn-cancel,
    .cp-btn-submit { padding: 10px 24px; font-size: 0.9rem; }
    .cp-btn-submit { border: none; padding: 10px 28px; }

    .theme-stat-grid,
    .db-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
        margin-bottom: 35px;
    }
    .db-cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .theme-stat-card,
    .db-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid var(--theme-border);
        padding: 24px 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        text-decoration: none;
        display: block;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .db-card:hover { transform: translateY(-3px); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.06); }
    .theme-stat-label,
    .db-card-title {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--theme-text-muted);
        font-weight: 600;
        margin-bottom: 8px;
    }
    .theme-stat-value,
    .db-card-value {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--theme-text-dark);
        line-height: 1.2;
        margin-bottom: 6px;
    }
    .db-card-value.currency { display: flex; align-items: baseline; gap: 2px; }
    .db-card-value.currency span { font-size: 1.8rem; }
    .theme-stat-sub,
    .db-card-sub { font-size: 0.75rem; font-weight: 500; }
    .theme-stat-sub.red,
    .db-card-sub.red { color: var(--theme-red); }
    .theme-stat-sub.orange,
    .db-card-sub.orange { color: #D97706; }
    .theme-stat-sub.blue,
    .db-card-sub.blue { color: #2563EB; }
    .theme-stat-sub.green { color: #16a34a; }
    .theme-stat-sub.muted { color: var(--theme-text-muted); }

    .theme-card,
    .db-transactions-card,
    .cp-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid var(--theme-border);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        margin-bottom: 35px;
    }
    .cp-card {
        overflow: visible !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    .theme-card-header,
    .db-trans-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 24px;
        border-bottom: 1px solid #f3f4f6;
    }
    .theme-card-title,
    .db-trans-title {
        font-weight: 700;
        font-size: 1rem;
        color: var(--theme-text-dark);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }
    .theme-card-title svg,
    .db-trans-title svg { width: 18px; height: 18px; }
    .db-trans-see-all { font-size: 0.8rem; color: var(--theme-link-blue); text-decoration: none; }

    .theme-table thead th,
    .db-table thead th,
    .cp-table thead th {
        background-color: var(--theme-pink-light);
        color: var(--theme-plum);
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        padding: 14px 24px;
    }
    .theme-table tbody td,
    .db-table tbody td,
    .cp-table tbody td {
        padding: 16px 24px;
        font-size: 0.9rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .cp-table tbody td { font-size: 0.88rem; }
    .theme-table tbody tr:last-child td,
    .db-table tbody tr:last-child td,
    .cp-table tbody tr:last-child td { border-bottom: none; }

    .theme-link-pill,
    .db-link-view,
    .ann-read-more {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--theme-plum);
        text-decoration: none;
        cursor: pointer;
        background: none;
        border: none;
    }
    .db-link-view { color: var(--theme-pink-link); font-weight: 500; }
    .theme-link-pill:hover,
    .db-link-view:hover,
    .ann-read-more:hover { text-decoration: underline; }

    .ann-list { display: flex; flex-direction: column; gap: 15px; }
    .ann-item {
        background: var(--theme-pink-card);
        border: 1px solid #f3e8f0;
        border-radius: 8px;
        padding: 20px 24px;
        transition: 0.2s;
    }
    .ann-item.is-read {
        background: #ffffff;
        border-color: #e5e7eb;
    }
    .ann-item.is-unread {
        background: #fff5f9;
        border-color: #f6d9e7;
    }
    .ann-item:hover { border-color: var(--theme-border); }
    .ann-item-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
    .ann-item-title { font-weight: 600; font-size: 1rem; color: var(--theme-plum); margin: 0; }
    .ann-item-date { font-size: 0.75rem; color: #9ca3af; white-space: nowrap; }
    .ann-item-body { font-size: 0.9rem; color: #4b5563; line-height: 1.5; margin-bottom: 10px; }
    .ann-item-footer { display: flex; justify-content: flex-end; }

    .theme-pagination,
    .ann-pagination,
    .cp-footer {
        background-color: transparent;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding: 0;
        font-size: 0.85rem;
        color: var(--theme-text-muted);
    }
    .cp-footer {
        background-color: var(--theme-pink-light);
        margin-top: 0;
        padding: 12px 24px;
        border-top: 1px solid #f3f4f6;
    }
    .theme-pager,
    .ann-pager,
    .cp-pager { display: flex; align-items: center; gap: 8px; }
    .theme-pager-btn,
    .ann-pager-btn,
    .cp-pager-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 1px solid #d1d5db;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--theme-text-muted);
        text-decoration: none;
        font-size: 0.8rem;
        transition: 0.2s;
    }
    .cp-pager-btn { border-color: var(--theme-border); color: var(--theme-plum); }
    .theme-pager-btn:hover:not(.disabled),
    .ann-pager-btn:hover:not(.disabled) { border-color: var(--theme-plum); color: var(--theme-plum); }
    .cp-pager-btn:hover:not(.disabled) { background: var(--theme-pink-light); border-color: var(--theme-plum); }
    .theme-pager-btn.disabled,
    .ann-pager-btn.disabled,
    .cp-pager-btn.disabled { opacity: 0.5; pointer-events: none; }
    .theme-pager-current,
    .ann-pager-current,
    .cp-pager-current {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--theme-plum);
        color: #fff;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
    }

    .ann-sidebar-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
    }
    .ann-sidebar-title { font-weight: 600; font-size: 0.95rem; color: #1a1a1a; margin-bottom: 15px; }
    .ann-emergency-box,
    .db-emergency-banner { background: var(--theme-red); color: #fff; }
    .ann-emergency-box { border-radius: 12px; padding: 20px; }
    .ann-emergency-box h5 { font-weight: 700; font-size: 0.95rem; margin-bottom: 4px; }
    .ann-emergency-box p { font-size: 0.8rem; opacity: 0.9; margin-bottom: 15px; }
    .ann-emergency-box .contact-person { font-size: 0.85rem; }
    .ann-emergency-box .contact-phone {
        font-size: 1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ann-emergency-box .contact-phone svg { width: 16px; height: 16px; fill: currentColor; }
    .db-emergency-banner {
        border-radius: 10px;
        padding: 24px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .db-emergency-left h4 { font-size: 1.1rem; font-weight: 700; margin: 0 0 4px 0; color: #fff; }
    .db-emergency-left p { font-size: 0.9rem; margin: 0; opacity: 0.9; }
    .db-emergency-right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
    .db-emergency-right .contact-name { font-size: 0.9rem; opacity: 0.9; }
    .db-emergency-right .contact-phone {
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .db-emergency-right .contact-phone svg { width: 18px; height: 18px; fill: currentColor; }

    .ann-filter-dropdown,
    .cp-filter-popup {
        padding: 20px 24px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 25px rgba(0,0,0,0.06);
    }
    .ann-filter-dropdown { min-width: 320px; margin-top: 12px !important; }
    .ann-filter-header,
    .cp-filter-popup .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .cp-filter-popup .filter-header { margin-bottom: 12px; }
    .ann-filter-header h6,
    .cp-filter-popup .filter-header h6 { font-weight: 700; font-size: 1.1rem; margin: 0; color: #000; }
    .ann-filter-clear,
    .cp-filter-popup .filter-clear,
    .cp-clear-link {
        font-size: 0.8rem;
        color: var(--theme-link-blue);
        text-decoration: none;
        font-weight: 500;
    }
    .cp-clear-link { text-decoration: underline; margin-left: 4px; }
    .cp-filter-popup .filter-clear { cursor: pointer; }
    .ann-filter-section-title,
    .cp-filter-popup .filter-section-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: #a855a7;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .cp-filter-popup .filter-section-title { font-size: 0.8rem; }
    .ann-filter-dropdown .form-check-label,
    .cp-filter-popup .form-check-label { font-weight: 500; color: #333; font-size: 0.85rem; }
    .cp-filter-popup .form-check-label { color: var(--theme-plum); font-size: 0.9rem; }
    .ann-filter-dropdown .form-check-input,
    .cp-filter-popup .form-check-input { border-color: #d1d5db; cursor: pointer; }
    .ann-filter-dropdown .form-check-input:checked,
    .cp-filter-popup .form-check-input:checked {
        background-color: var(--theme-plum);
        border-color: var(--theme-plum);
    }
    .ann-filter-dropdown .form-select,
    .cp-filter-popup .form-select {
        border-radius: 4px;
        padding: 8px 12px;
        font-weight: 500;
        font-size: 0.9rem;
        border: 1px solid #d1d5db;
        width: 100%;
    }
    .cp-filter-popup .form-select { border-radius: 6px; padding: 10px 12px; }
    .cp-filter-label { font-size: 0.85rem; font-weight: 600; color: #333; margin-right: 4px; }
    .cp-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff;
        color: #333;
        border: 1px solid var(--theme-border);
        border-radius: 999px;
        padding: 3px 12px 3px 14px;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        transition: 0.2s;
    }
    .cp-chip:hover { background: #f9f9f9; }
    .cp-filter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 16px 24px;
        border-bottom: 1px solid #f3f4f6;
        position: relative;
    }
    .cp-filter-wrapper { position: relative; display: inline-block; }
    .cp-filter-popup {
        padding: 24px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        display: none;
        position: fixed;
        top: 45%;
        left: 80%;
        transform: translate(-50%, -50%);
        width: 340px;
        max-width: 95vw;
        max-height: 90vh;
        overflow-y: auto;
        z-index: 99999;
    }
    .cp-filter-popup.open { display: block; }
    .cp-filter-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.3);
        z-index: 99998;
    }
    .cp-filter-overlay.open { display: block; }
    .cp-status { font-size: 0.85rem; font-weight: 500; }
    .cp-status.submitted { color: #2563eb; }
    .cp-status.in_progress { color: #ea580c; }
    .cp-status.resolved { color: #16a34a; }
    .cp-row-menu {
        border: none;
        background: none;
        color: #9ca3af;
        font-size: 1.2rem;
        padding: 4px 8px;
        cursor: pointer;
    }
    .cp-row-menu:hover { color: var(--theme-plum); }

    .ann-modal .modal-content,
    .cp-modal .modal-content { border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.1); }
    .ann-modal .modal-content { border-radius: 4px; padding: 30px 40px; }
    .ann-modal .modal-body { padding: 0; }
    .ann-modal-back { display: flex; justify-content: flex-end; margin-bottom: 15px; }
    .ann-modal-back button { background: none; border: none; color: #4b5563; font-weight: 500; font-size: 0.85rem; }
    .ann-modal-title { font-size: 1.6rem; font-weight: 700; color: var(--theme-plum); margin-bottom: 6px; }
    .ann-modal-meta {
        font-size: 0.85rem;
        color: var(--theme-text-muted);
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ann-modal-meta span { font-weight: 500; }
    .ann-modal-divider { color: #9ca3af; }
    .ann-modal-text { font-size: 0.95rem; color: #374151; line-height: 1.7; margin-bottom: 20px; }
    .ann-modal-list { padding-left: 20px; margin-bottom: 30px; font-size: 0.95rem; color: #374151; line-height: 1.7; }

    @media (max-width: 900px) {
        .ann-page-wrapper { flex-direction: column-reverse; }
        .ann-right-col { flex-direction: row; flex-wrap: wrap; }
        .ann-right-col > * { flex: 1; min-width: 200px; }
        .db-emergency-banner { flex-direction: column; align-items: flex-start; text-align: left; }
        .db-emergency-right { align-items: flex-start; width: 100%; margin-top: 10px; }
    }
    @media (max-width: 768px) {
        .theme-stat-grid { grid-template-columns: 1fr; }
        .cp-filter-bar { flex-direction: column; align-items: stretch; }
        .cp-filter-btn { align-self: flex-end; }
        .theme-pagination,
        .cp-footer { flex-direction: column; gap: 10px; text-align: center; }
        .cp-filter-popup { width: 95vw; }
    }
    @media (max-width: 640px) {
        .db-header h1 { font-size: 1.5rem; }
        .db-cards-grid { grid-template-columns: 1fr; }
        .db-card-value,
        .theme-stat-value { font-size: 2rem; }
        .db-table thead,
        .theme-table thead { display: none; }
        .db-table tbody tr,
        .theme-table tbody tr {
            display: block;
            border-bottom: 1px solid #f3f4f6;
            padding: 16px 0;
        }
        .db-table tbody td,
        .theme-table tbody td {
            display: block;
            padding: 4px 24px;
            border: none;
            text-align: right;
        }
        .db-table tbody td::before,
        .theme-table tbody td::before {
            content: attr(data-label);
            float: left;
            font-weight: 700;
            color: var(--theme-plum);
        }
    }
    @media (max-width: 600px) {
        .ann-controls { flex-direction: column; align-items: stretch; gap: 10px; }
        .ann-actions { justify-content: flex-start; }
        .ann-tabs { justify-content: flex-start; }
        .ann-filter-dropdown { min-width: unset; width: 100%; }
    }
</style>
