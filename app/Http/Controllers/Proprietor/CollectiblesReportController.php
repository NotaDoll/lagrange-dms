<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CollectiblesReportController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('proprietor.reports.index', $request->only(['date_from', 'date_to', 'tenant_id']));
    }
}
