<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Faq;
use Illuminate\Http\Request;

class ResidentContentController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('tab') === 'faqs') {
            return view('proprietor.resident-content.index', [
                'tab' => 'faqs',
                'faqs' => Faq::with('creator')->latest()->paginate(10)->appends('tab', 'faqs'),
            ]);
        }

        return view('proprietor.resident-content.index', [
            'tab' => 'announcements',
            'announcements' => Announcement::with('creator')->latest()->paginate(10)->appends('tab', 'announcements'),
        ]);
    }
}
