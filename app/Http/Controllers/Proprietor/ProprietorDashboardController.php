<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Tenant;
use App\Services\ComplaintSummarizationService;
use App\Services\PaymentRiskService;
use App\Services\ProprietorDashboardService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProprietorDashboardController extends Controller
{
    public function index(Request $request, ProprietorDashboardService $dashboard)
    {
        return view('proprietor.dashboard', $dashboard->data($request->user()));
    }

    public function sendSms(Request $request, Tenant $tenant, PaymentRiskService $risk, SmsService $sms)
    {
        abort_unless($tenant->status === 'active' && $risk->evaluate($tenant)['risk_level'] === 'high', 422, 'SMS follow-up is available only for active high-risk tenants.');
        if (! filled($tenant->guardian_contact_number)) {
            return response()->json(['message' => 'Add a guardian mobile number in the tenant profile before sending a reminder.'], 422);
        }
        $validated = $request->validate(['resend_after' => ['sometimes', 'required', 'integer', 'min:1']]);
        $lock = Cache::lock('dashboard.sms.'.$tenant->id, 30);
        if (! $lock->get()) {
            return response()->json(['message' => 'A follow-up is already being sent. Please wait.'], 409);
        }
        try {
            $existing = $tenant->smsLogs()->paymentFollowUpToday()->latest('id')->first();
            $resend = $existing && (int) ($validated['resend_after'] ?? 0) === $existing->id;
            $reuse = $existing && ! $resend;
            $log = $reuse ? $existing : $sms->sendGuardianPaymentFollowUp($tenant);
            $status = $log->status;

            $message = match ($status) {
                'queued' => $reuse ? 'An SMS follow-up is already queued today.' : 'SMS queued in TextBee.',
                'unknown' => 'SMS status is unconfirmed. Check TextBee history before sending again.',
                'delivered' => 'SMS already delivered today.',
                'sent' => $reuse ? 'SMS already sent today.' : 'SMS sent to the guardian.',
                default => 'SMS could not be sent. Check the SMS gateway settings and try again.',
            };

            return response()->json([
                'message' => $message,
                'status' => $status,
                'log_id' => $log->id,
                'sent' => in_array($status, ['sent', 'delivered'], true),
            ], $status === 'failed' ? 502 : 200);
        } finally {
            $lock->release();
        }
    }

    public function generateSummary(Request $request, ComplaintSummarizationService $summarizer)
    {
        $complaints = Complaint::open()
            ->latest()
            ->get(['category', 'description', 'status', 'created_at']);

        if ($complaints->isEmpty()) {
            return response()->json(['message' => 'There are no open complaints to summarize.'], 422);
        }

        try {
            $summary = $summarizer->summarize($complaints, [
                'generated_by' => $request->user()->id,
            ]);

            return response()->json([
    'message'         => 'Summary generated and saved.',
    'id'              => $summary->id,
    'summary'         => $summary->summary_result,
    'complaint_count' => $complaints->count(),
]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to generate a summary: '.$exception->getMessage(),
            ], 502);
        }
    }
}
