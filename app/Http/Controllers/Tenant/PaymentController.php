<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\GatewayCheckout;
use App\Services\PaymentLedgerService;
use App\Services\TenantBalanceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentController extends Controller
{
    public function index(Request $request, TenantBalanceService $balanceService)
    {
        $tenant = auth()->user()->tenant()->firstOrFail();
        $tenant->load('payments.utilityBill.room');
        $balance = $balanceService->summary($tenant);
        $rentTerms = $balanceService->currentRent($tenant);

        $outstandingBalance = $balance['outstanding_balance'];
        $unpaidMonths = $balance['unpaid_months'];
        $totalPaid = $balance['total_paid'];
        $paidCount = $balance['paid_count'];
        $advancePayment = (float) $tenant->advance_payment;
        $paymentStatus = $outstandingBalance > 0 ? 'Outstanding' : 'Paid';
        $checkoutTableReady = Schema::hasTable('gateway_checkouts');
        $recentCheckouts = $checkoutTableReady
            ? GatewayCheckout::where('tenant_id', $tenant->id)->latest()->limit(5)->get()
            : collect();
        $gcashBalance = $balanceService->ledger($tenant)
            ->filter(fn ($entry) => ! $entry->utility_bill_id && ! $entry->hasRecordedPayment()
                && $entry->due_date->lte(\App\Models\Tenant::localDate()))
            ->sum('amount');

        $rows = $balanceService->ledger($tenant);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $payments = new LengthAwarePaginator($rows->forPage($page, 10), $rows->count(), 10, $page, ['path' => $request->url()]);

        return view('tenant.payments.index', compact(
            'payments',
            'rentTerms',
            'outstandingBalance',
            'unpaidMonths',
            'totalPaid',
            'paidCount',
            'advancePayment',
            'paymentStatus',
            'recentCheckouts',
            'checkoutTableReady',
            'gcashBalance',
        ));
    }

    public function checkout(Request $request, TenantBalanceService $balanceService)
    {
        $tenant = $request->user()->tenant()->firstOrFail();
        abort_unless(Schema::hasTable('gateway_checkouts') && config('services.xendit.secret_key'), 503, 'GCash checkout is not configured yet.');
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:100000']]);
        $amount = round((float) $validated['amount'], 2);
        $rentBalance = $balanceService->ledger($tenant)
            ->filter(fn ($entry) => ! $entry->utility_bill_id && ! $entry->hasRecordedPayment()
                && $entry->due_date->lte(\App\Models\Tenant::localDate()))
            ->sum('amount');
        if ($amount > $rentBalance) {
            return back()->withErrors(['amount' => 'The amount cannot be more than your unpaid rent and storage balance.']);
        }

        $base = rtrim(config('app.url'), '/');
        if (parse_url($base, PHP_URL_SCHEME) !== 'https') {
            return back()->withErrors(['gcash' => 'Xendit requires a public HTTPS app URL. Set APP_URL to your live domain or HTTPS tunnel, then clear the config cache.']);
        }

        $reference = 'TEN-'.$tenant->id.'-'.Str::upper(Str::random(16));
        $checkout = GatewayCheckout::create(['tenant_id' => $tenant->id, 'reference' => $reference, 'amount' => $amount]);
        $response = Http::withBasicAuth(config('services.xendit.secret_key'), '')
            ->acceptJson()->post(rtrim(config('services.xendit.api_url'), '/').'/sessions', [
                'reference_id' => $reference,
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'amount' => $amount,
                'currency' => 'PHP',
                'country' => 'PH',
                'allowed_payment_channels' => ['GCASH'],
                'description' => 'Tenant rent and storage payment',
                'metadata' => ['checkout_reference' => $reference, 'tenant_id' => (string) $tenant->id],
                'success_return_url' => $base.route('tenant.payments.return', $checkout, false),
                'cancel_return_url' => $base.route('tenant.payments.return', $checkout, false),
            ]);

        if (! $response->successful() || ! data_get($response->json(), 'payment_link_url')) {
            $checkout->update(['status' => 'failed']);
            report(new \RuntimeException('Xendit checkout creation failed: '.$response->body()));
            return back()->withErrors(['gcash' => 'GCash checkout could not be started. Check your Xendit setup and try again.']);
        }

        $checkout->update([
            'checkout_session_id' => data_get($response->json(), 'payment_session_id'),
            'checkout_url' => data_get($response->json(), 'payment_link_url'),
        ]);

        return redirect()->away($checkout->checkout_url);
    }

    public function return(Request $request, GatewayCheckout $checkout)
    {
        abort_unless($checkout->tenant_id === $request->user()->tenant()->value('id'), 404);
        return redirect()->route('tenant.payments.index')->with('status', $checkout->status === 'paid'
            ? 'GCash payment received and added to your ledger.'
            : 'Checkout returned. Your payment will appear here once Xendit confirms it.');
    }
}
