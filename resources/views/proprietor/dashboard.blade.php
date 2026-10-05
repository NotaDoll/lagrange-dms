@extends('layouts.proprietor')

@section('title', 'Proprietor Dashboard')

@section('content')
       <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Welcome, {{ auth()->user()->name }}</h1>
            <div class="text-muted">
                Here's what's happening in your property today.
            </div>
        </div>
    </div>

    {{-- KPI CARDS --}}
    <div class="row g-3 mb-4">

        {{-- Total Tenants --}}
        <div class="col-12 col-sm-6 col-xl">
            <a
                href="{{ route('proprietor.tenants.index') }}"
                class="text-decoration-none text-body"
            >
                <div class="card h-100 dashboard-kpi-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-muted mb-1">
                                    Total Tenants
                                </div>

                                <div class="fs-2 fw-bold">
                                    {{ $totalTenants }}
                                </div>
                            </div>

                            <div class="dashboard-kpi-icon">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            Registered tenants
                        </div>
                    </div>
                </div>
            </a>
        </div>


        {{-- Occupancy Rate --}}
        <div class="col-12 col-sm-6 col-xl">
            <a
                href="{{ route('proprietor.rooms.index') }}"
                class="text-decoration-none text-body"
            >
                <div class="card h-100 dashboard-kpi-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-muted mb-1">
                                    Occupancy Rate
                                </div>

                                <div class="fs-2 fw-bold">
                                    {{ $occupancyRate }}%
                                </div>
                            </div>

                            <div class="dashboard-kpi-icon">
                                <i class="bi bi-house-door"></i>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            {{ $occupiedBeds }} of {{ $totalBeds }} beds occupied
                        </div>
                    </div>
                </div>
            </a>
        </div>


        {{-- Collected This Month --}}
        <div class="col-12 col-sm-6 col-xl">
            <a
                href="{{ route('proprietor.reports.index') }}"
                class="text-decoration-none text-body"
            >
                <div class="card h-100 dashboard-kpi-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-muted mb-1">
                                    Collected This Month
                                </div>

                                <div class="fs-2 fw-bold">
                                    ₱{{ number_format($totalCollectedThisMonth, 2) }}
                                </div>
                            </div>

                            <div class="dashboard-kpi-icon">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            Paid payments this month
                        </div>
                    </div>
                </div>
            </a>
        </div>


        {{-- Unpaid Amount --}}
        <div class="col-12 col-sm-6 col-xl">
            <a
                href="{{ route('proprietor.payments.index') }}"
                class="text-decoration-none text-body"
            >
                <div class="card h-100 dashboard-kpi-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-muted mb-1">
                                    Unpaid Amount
                                </div>

                                <div class="fs-2 fw-bold">
                                    ₱{{ number_format($totalUnpaid, 2) }}
                                </div>
                            </div>

                            <div class="dashboard-kpi-icon">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            Pending + overdue payments
                        </div>
                    </div>
                </div>
            </a>
        </div>


        {{-- Complaints --}}
        <div class="col-12 col-sm-6 col-xl">
            <a
                href="{{ route('proprietor.complaints.index') }}"
                class="text-decoration-none text-body"
            >
                <div class="card h-100 dashboard-kpi-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-muted mb-1">
                                    Needs Attention
                                </div>

                                <div class="fs-2 fw-bold">
                                    {{ $unresolvedComplaintsCount }}
                                </div>
                            </div>

                            <div class="dashboard-kpi-icon">
                                <i class="bi bi-chat-left-text"></i>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            Unresolved complaints
                        </div>
                    </div>
                </div>
            </a>
        </div>

    </div>
@endsection
