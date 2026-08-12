<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Complaint; // Make sure to import the Complaint model!
use App\Models\Announcement; // Make sure to import the Announcement model!

class TenantDashboardController extends Controller
{
    public function index(): View
    {
        // 1. Get the current tenant linked to the logged-in user
        // Note: This assumes your User model has a 'tenant' relationship.
        $tenant = auth()->user()->tenant;

        // 2. Count the total complaints for this specific tenant
        $complaintsCount = Complaint::where('tenant_id', $tenant->id)->count();

        // 3. Count the total announcements (You can adjust this if you have an 'unread' scope later)
        $announcementsCount = Announcement::count();

        // 4. Pass both counts to the dashboard view
        return view('tenant.dashboard', compact('complaintsCount', 'announcementsCount'));
    }
}
