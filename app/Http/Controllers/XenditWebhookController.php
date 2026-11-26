<?php

namespace App\Http\Controllers;

use App\Models\GatewayCheckout;
use App\Models\Payment;
use App\Services\PaymentLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class XenditWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentLedgerService $ledger): Response
    {
        $token = config('services.xendit.webhook_token');
        abort_unless($token && hash_equals($token, (string) $request->header('x-callback-token')), 401);

        $event = $request->json()->all();
        $data = data_get($event, 'data', []);
        $reference = data_get($data, 'reference_id');
        $sessionId = data_get($data, 'payment_session_id');
        $checkout = GatewayCheckout::where('reference', $reference)
            ->where('checkout_session_id', $sessionId)
            ->first();

        if (! $checkout) {
            return response('', 200);
        }

        if (data_get($event, 'event') === 'payment_session.expired') {
            if ($checkout->status === 'pending') {
                $checkout->update(['status' => 'expired']);
            }

            return response('', 200);
        }

        if (data_get($event, 'event') !== 'payment_session.completed'
            || data_get($data, 'status') !== 'COMPLETED'
            || ! data_get($data, 'payment_id')
            || data_get($data, 'currency') !== 'PHP'
            || round((float) data_get($data, 'amount'), 2) !== round((float) $checkout->amount, 2)) {
            return response('', 200);
        }

        DB::transaction(function () use ($checkout, $ledger, $data) {
            $checkout = GatewayCheckout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();
            if ($checkout->status === 'paid') {
                return;
            }

            $receipt = 'XENDIT-'.$checkout->reference;
            $ledger->recordPayment([
                'tenant_id' => $checkout->tenant_id,
                'applies_to' => Payment::RENT,
                'amount' => (float) $checkout->amount,
                'paid_date' => now()->toDateString(),
                'receipt_number' => $receipt,
                'apply_excess' => true,
                'note' => 'Xendit GCash payment ('.data_get($data, 'payment_id').')',
            ]);
            $checkout->update(['status' => 'paid', 'receipt_number' => $receipt, 'paid_at' => now()]);
        });

        return response('', 200);
    }
}
