@extends('layouts.proprietor')

@section('title', 'At Risk Tenants')

@section('content')
    @include('proprietor.dashboard-styles')
    <x-breadcrumbs :items="['Payment Ledger' => route('proprietor.payments.index'), 'At Risk Tenants' => null]" />
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">At Risk Tenants</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('proprietor.dashboard') }}#pd-follow" class="btn btn-outline-secondary"><i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard</a>
            <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Ledger</a>
        </div>
    </div>

    {{-- The same "Tenants to follow up" list as the dashboard, with more rows per page. --}}
    <section class="card" aria-labelledby="pd-follow"><div class="card-body">
        @include('proprietor.partials.follow-up', ['perPage' => 20, 'showViewAll' => false])
    </div></section>

    @include('proprietor.partials.follow-up-modals')
    @include('proprietor.dashboard-script')
@endsection
