<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::latest()
            ->paginate(10);

        // FAQs have their own page in the tenant menu, so they are not repeated here.
        return view('tenant.announcements.index', compact('announcements'));
    }
}
