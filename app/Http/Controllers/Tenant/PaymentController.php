<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant()->firstOrFail();

        $payments = Payment::where('tenant_id', $tenant->id)
            ->latest('due_date')
            ->paginate(15);

        return view('tenant.payments.index', compact('payments'));
    }
}
