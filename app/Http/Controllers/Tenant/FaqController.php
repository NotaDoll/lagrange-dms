<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Faq;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::latest()
            ->paginate(10);

        return view('tenant.faqs.index', compact('faqs'));
    }
}
