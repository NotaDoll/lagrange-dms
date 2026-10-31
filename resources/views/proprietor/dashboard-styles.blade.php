<style>
    .pd { color: var(--theme-text-dark); }
    .pd .btn { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
    .pd a { color: var(--theme-plum); }
    .pd .btn-primary { color: white; }
    .pd .card { border-color: var(--theme-border); }
    .pd .card-body { padding: 1.25rem; }
    .pd-tag { display: inline-block; border-radius: 6px; padding: .3rem .5rem; font-size: .7rem; font-weight: 700; white-space: nowrap; }
    .pd-high { background: #fce8e8; color: #a01818; }
    .pd-medium { background: #fff2d8; color: #805000; }
    .pd-ai { background: var(--theme-pink-light); color: var(--theme-plum); }
    .pd-room { background: #e6f4ed; color: #226045; }
    .pd-alerts li { display: flex; align-items: baseline; gap: .8rem; padding: .6rem 0; border-bottom: 1px solid var(--theme-border); }
    .pd-alerts li:last-child { border-bottom: 0; padding-bottom: 0; }
    .pd-alerts a { font-size: .85rem; line-height: 1.65; text-decoration-thickness: 1px; text-underline-offset: 3px; }
    .pd-stat-label { font-size: .83rem; color: var(--theme-text-muted); font-weight: 500; margin-bottom: .65rem; }
    .pd-stat-value { font-size: clamp(1.3rem, 1.8vw, 1.85rem); font-weight: 700; font-variant-numeric: tabular-nums; }
    .pd-stat-value span { font-size: 1rem; font-weight: 500; color: var(--theme-text-muted); }
    .pd-progress { height: 8px; background: var(--theme-pink-light); }
    .pd-progress .progress-bar { background: var(--theme-plum); }
    .pd-note { font-size: .73rem; color: var(--theme-text-muted); line-height: 1.6; }
    .pd-follow-table { font-size: .8rem; min-width: 660px; }
    .pd-follow-table th, .pd-follow-table td { padding: .8rem .5rem; }
    .pd-follow-table summary { cursor: pointer; padding: .3rem 0; }
    .pd-spark { width: 100px; height: 40px; color: var(--theme-plum); }
    .pd-summary-text { white-space: pre-line; font-size: .85rem; overflow-wrap: anywhere; }
    .pd a:focus-visible, .pd button:focus-visible { outline: 2px solid var(--theme-plum); outline-offset: 3px; }
    @media (max-width: 575.98px) {
        .pd-alerts li { align-items: flex-start; flex-direction: column; gap: .35rem; }
        .pd-stat-value { font-size: 1.6rem; }
    }
</style>
