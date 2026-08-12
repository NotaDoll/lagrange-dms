<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Faq; // 1. Import the Faq model!

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::latest()
            ->paginate(10);

        // 2. Fetch the FAQs for the sidebar (Limit to 5 so it doesn't get too long)
        $faqs = Faq::latest()
            ->take(5)
            ->get();

        // 3. Pass $faqs to the view alongside announcements
        return view('tenant.announcements.index', compact('announcements', 'faqs'));
    }
}
