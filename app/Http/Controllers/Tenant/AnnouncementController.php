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

        return view('tenant.announcements.index', compact('announcements'));
    }
}
