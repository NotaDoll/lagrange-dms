<style>
    .pd { color: var(--theme-text-dark); }
    .pd a { color: var(--theme-plum); }
    /* The general dashboard link color must not override primary button contrast. */
    .pd a.btn-primary,
    .pd a.btn-primary:hover,
    .pd a.btn-primary:focus,
    .pd a.btn-primary:active { color: var(--bs-btn-color, #fff); }
    .pd .card-body { padding: 1.25rem; }
    .pd-tag { display: inline-block; border-radius: 999px; padding: .3em .7em; font-size: .7rem; font-weight: 600; white-space: nowrap; }
    .pd-high { background: var(--theme-danger-bg); color: var(--theme-danger); }
    .pd-medium { background: var(--theme-warning-bg); color: var(--theme-warning); }
    .pd-ai { background: var(--theme-pink-light); color: var(--theme-plum); }
    .pd-room { background: var(--theme-success-bg); color: var(--theme-success); }
    /* Priority alert tiles: the color says how urgent, the number says how many. */
    .pd-tile-value { font-size: 2rem; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .pd-tile-topic { font-size: 1.05rem; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .pd-summary-theme-link { color: #5e1049; font-size: .875rem; font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    .pd-summary-theme-link:hover, .pd-summary-theme-link:focus-visible { color: #9b267a; }
    .pd-tile-ok { font-size: .9rem; color: var(--theme-text-muted); }
    .pd-tile-danger { border: 2px solid var(--theme-danger) !important; }
    .pd-tile-danger .pd-tile-value, .pd-tile-danger .pd-stat-label i { color: var(--theme-danger); }
    .pd-tile-warning { border-color: #e0a100 !important; }
    .pd-tile-warning .pd-tile-value, .pd-tile-warning .pd-stat-label i { color: var(--theme-warning); }
    .pd-tile-info .pd-stat-label i { color: var(--theme-plum); }
    .pd-tile-ok .bi { color: var(--theme-success); }
    .pd-tile.pd-tile-clear { background: var(--bs-tertiary-bg, #f8f9fa); }
    .pd-riskbar { display: flex; height: 8px; border-radius: 4px; overflow: hidden; background: var(--theme-border); margin: .35rem 0 .15rem; }
    .pd-risk-high { background: #d32f2f; }
    .pd-risk-medium { background: #f0a020; }
    .pd-risk-low { background: #7cb342; }
    .pd-chart { position: relative; height: 240px; }
    .pd-chart-short { height: 190px; }
    /* Follow-up table */
    .pd-main-reason { color: var(--theme-danger); }
    .pd-overdue { font-size: .7rem; color: var(--theme-danger); }
    .pd-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .pd-reason { font-size: .72rem; line-height: 1.3; padding: .25rem .6rem; border-radius: 999px; background: var(--theme-danger-bg); color: var(--theme-danger); }
    .pd-follow-search { max-width: 220px; min-height: 36px; }
    .pd-follow-table td:first-child { min-width: 140px; }
    .pd-history { display: inline-flex; gap: 3px; }
    .pd-history-cell { display: inline-flex; align-items: flex-end; justify-content: center; width: 22px; height: 26px; border-radius: 4px; font-size: .58rem; color: var(--theme-text-muted); }
    .pd-history-cell > span { transform: translateY(14px); }
    .pd-history { padding-bottom: 14px; }
    .pd-history-high { background: #d32f2f; }
    .pd-history-medium { background: #f0a020; }
    .pd-history-low { background: #7cb342; }
    .pd-history-none { background: transparent; border: 1px dashed var(--theme-border); }
    .pd-history-key { width: 10px; height: 10px; vertical-align: -1px; }
    .pd .min-w-0 { min-width: 0; }
    .pd details summary { cursor: pointer; }
    .pd-stat-label { font-size: .83rem; color: var(--theme-text-muted); font-weight: 500; margin-bottom: .65rem; }
    .pd-stat-value { font-size: clamp(1.3rem, 1.8vw, 1.85rem); font-weight: 700; font-variant-numeric: tabular-nums; }
    .pd-stat-value span { font-size: 1rem; font-weight: 500; color: var(--theme-text-muted); }
    .pd-progress { height: 8px; background: var(--theme-pink-light); }
    .pd-progress .progress-bar { background: var(--theme-plum); }
    .pd-note { font-size: .73rem; color: var(--theme-text-muted); line-height: 1.6; }
    .pd-follow-table { font-size: .8rem; min-width: 460px; }
    .pd-follow-table th, .pd-follow-table td { padding: .8rem .5rem; }
    .pd-follow-table summary { cursor: pointer; padding: .3rem 0; }
    .pd-spark { width: 100px; height: 40px; color: var(--theme-plum); }
    .pd-summary-text { white-space: pre-line; font-size: .85rem; overflow-wrap: anywhere; }
    .pd a:focus-visible, .pd button:focus-visible { outline: 2px solid var(--theme-plum); outline-offset: 3px; }
    @media (max-width: 575.98px) {
        .pd-chart { height: 200px; }
        .pd-stat-value { font-size: 1.6rem; }
    }
</style>
