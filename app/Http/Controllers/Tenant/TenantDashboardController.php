<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantDashboardController extends Controller
{
    public function index(): View
    {
        return view('tenant.dashboard');
    }
}