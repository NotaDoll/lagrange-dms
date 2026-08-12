@extends('layouts.tenant')

@section('title', 'Dashboard')

@section('content')

<style>
    /* ===== DASHBOARD VARIABLES ===== */
    :root {
        --db-plum: #5E1049;
        --db-plum-light: #FDF2F8;
        --db-plum-border: #E8DEE5;
        --db-red: #B80000;
        --db-text-muted: #6B7280;
        --db-text-dark: #111827;
        --db-blue-bg: #EBF5FF;
        --db-blue-border: #BFDBFE;
        --db-pink-link: #D974B2;
    }

    .db-wrapper {
        margin-top: -20px; /* Tighten spacing to header */
    }

    /* ===== INFO BANNER ===== */
    .db-info-banner {
        background-color: var(--db-blue-bg);
        border: 1px solid var(--db-blue-border);
        border-radius: 6px;
        padding: 14px 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.9rem;
        color: #1e3a8a; /* Dark blue text */
    }
    .db-info-banner .close-btn {
        background: none;
        border: none;
        color: #6b7280;
        font-size: 1.2rem;
        cursor: pointer;
        line-height: 1;
    }

    /* ===== PAGE HEADER ===== */
    .db-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 25px;
    }

    /* ===== CARDS GRID ===== */
    .db-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 24px;
        margin-bottom: 35px;
    }

    .db-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid var(--db-plum-border);
        padding: 24px 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        text-decoration: none;
        display: block;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .db-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.06);
    }

    .db-card-title {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--db-text-muted);
        font-weight: 600;
        margin-bottom: 8px;
    }

    .db-card-value {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--db-text-dark);
        line-height: 1.2;
        margin-bottom: 6px;
    }

    .db-card-value.currency {
        display: flex;
        align-items: baseline;
        gap: 2px;
    }
    .db-card-value.currency span {
        font-size: 1.8rem;
    }

    .db-card-sub {
        font-size: 0.75rem;
        font-weight: 500;
    }
    .db-card-sub.red { color: #B80000; }
    .db-card-sub.orange { color: #D97706; }
    .db-card-sub.blue { color: #2563EB; }

    /* ===== TRANSACTIONS CARD ===== */
    .db-transactions-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid var(--db-plum-border);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        margin-bottom: 35px;
    }

    .db-trans-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 24px;
        border-bottom: 1px solid #f3f4f6;
    }
    .db-trans-title {
        font-weight: 700;
        font-size: 1rem;
        color: var(--db-text-dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .db-trans-title svg {
        width: 18px; height: 18px;
    }
    .db-trans-see-all {
        font-size: 0.8rem;
        color: #3b82f6;
        text-decoration: none;
    }

    .db-table thead th {
        background-color: var(--db-plum-light);
        color: var(--db-plum);
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        padding: 14px 24px;
    }
    .db-table tbody td {
        padding: 16px 24px;
        font-size: 0.9rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .db-table tbody tr:last-child td { border-bottom: none; }

    .db-link-view {
        color: var(--db-pink-link);
        text-decoration: none;
        font-weight: 500;
    }
    .db-link-view:hover { text-decoration: underline; }

    /* ===== EMERGENCY CONTACT BANNER ===== */
    .db-emergency-banner {
        background-color: var(--db-red);
        border-radius: 10px;
        padding: 24px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        color: #fff;
    }

    .db-emergency-left h4 {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: #fff;
    }
    .db-emergency-left p {
        font-size: 0.9rem;
        margin: 0;
        opacity: 0.9;
    }

    .db-emergency-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
    }
    .db-emergency-right .contact-name {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    .db-emergency-right .contact-phone {
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .db-emergency-right .contact-phone svg {
        width: 18px; height: 18px; fill: currentColor;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 900px) {
        .db-emergency-banner { flex-direction: column; align-items: flex-start; text-align: left; }
        .db-emergency-right { align-items: flex-start; width: 100%; margin-top: 10px; }
    }
    @media (max-width: 640px) {
        .db-header h1 { font-size: 1.5rem; }
        .db-cards-grid { grid-template-columns: 1fr; }
        .db-card-value { font-size: 2rem; }
        .db-table thead { display: none; }
        .db-table tbody tr { display: block; border-bottom: 1px solid #f3f4f6; padding: 16px 0; }
        .db-table tbody td { display: block; padding: 4px 24px; border: none; text-align: right; }
        .db-table tbody td::before {
            content: attr(data-label);
            float: left;
            font-weight: 700;
            color: var(--db-plum);
        }
    }
</style>

<div class="db-wrapper">

    <!-- Info Banner -->
    <div class="db-info-banner">
        <span>
            <span style="color: #2563eb; font-weight: 700; margin-right: 5px;">i</span>
            First-time users: Your login credentials, including your temporary password, were provided by the administrator. You will be required to change your password upon your first login.
        </span>
        <button type="button" class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
    </div>

    <!-- Header -->
    <div class="db-header">
        <h1>Dashboard</h1>
    </div>

    <!-- Cards Section -->
    <div class="db-cards-grid">
        <!-- Outstanding Balance Card - Kept, but data cleared -->
        <div class="db-card">
            <div class="db-card-title">OUTSTANDING BALANCE</div>
            <div class="db-card-value currency">
                <span>₱</span>0.00
            </div>
            <!-- Removed the red "Due by..." text -->
        </div>

        <!-- Pending Complaints Card -->
        <a href="{{ route('tenant.complaints.index') }}" class="db-card db-card-link">
            <div class="db-card-title">PENDING COMPLAINTS</div>
            <div class="db-card-value">{{ $complaintsCount }}</div>
            <div class="db-card-sub orange">{{ $complaintsCount > 0 ? 'Under Review' : 'No pending complaints' }}</div>
        </a>

        <!-- Unread Announcements Card -->
        <a href="{{ route('tenant.announcements.index') }}" class="db-card db-card-link">
            <div class="db-card-title">UNREAD ANNOUNCEMENTS</div>
            <div class="db-card-value">{{ $announcementsCount }}</div>
            <div class="db-card-sub blue">Latest: Christmas Party Meeting</div>
        </a>
    </div>

    <!-- Recent Transactions Table -->
    <div class="db-transactions-card">
        <div class="db-trans-header">
            <div class="db-trans-title">
                <!-- Clock Icon -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                Recent Transactions
            </div>
            <a href="{{ route('tenant.payments.index') }}" class="db-trans-see-all">See all transactions</a>
        </div>

        <div class="table-responsive">
            <table class="table db-table mb-0">
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Sample data removed as requested. This will just show the headers -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Emergency Contact Banner -->
    <div class="db-emergency-banner">
        <div class="db-emergency-left">
            <h4>Emergency Contact</h4>
            <p>For urgent room issues, call the dorm manager.</p>
        </div>
        <div class="db-emergency-right">
            <span class="contact-name">Merlinda Vilanueva</span>
            <div class="contact-phone">
                <!-- Phone Icon SVG -->
                <svg viewBox="0 0 24 24">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                0912-345-6789
            </div>
        </div>
    </div>

</div>

@endsection
