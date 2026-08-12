@extends('layouts.tenant')

@section('title', 'Dashboard')

@section('content')


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
