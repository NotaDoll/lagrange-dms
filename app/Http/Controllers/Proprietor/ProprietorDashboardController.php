<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Tenant;
use App\Services\ComplaintSummarizationService;
use App\Services\GuardianSmsMessage;
use App\Services\PaymentRiskService;
use App\Services\ProprietorDashboardService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProprietorDashboardController extends Controller
{
    public function index(Request $request, ProprietorDashboardService $dashboard)
    {
        return view('proprietor.dashboard', $dashboard->data($request->user(), $request->integer('trend', 6)));
    }

    public function sendSms(Request $request, Tenant $tenant, PaymentRiskService $risk, SmsService $sms)
    {
        abort_unless($tenant->status === 'active' && $risk->evaluate($tenant)['risk_level'] === 'high', 422, 'SMS follow-up is available only for active high-risk tenants.');
        if (! filled($tenant->guardian_contact_number)) {
            return response()->json(['message' => 'Add a guardian mobile number in the tenant profile before sending a reminder.'], 422);
        }
        $validated = $request->validate([
            'resend_after' => ['sometimes', 'required', 'integer', 'min:1'],
            // The proprietor may edit the reminder before sending; blank means use the Settings template.
            'message' => ['sometimes', 'nullable', 'string', 'max:'.GuardianSmsMessage::MAX_LENGTH],
        ]);
        $lock = Cache::lock('dashboard.sms.'.$tenant->id, 30);
        if (! $lock->get()) {
            return response()->json(['message' => 'A follow-up is already being sent. Please wait.'], 409);
        }
        try {
            $existing = $tenant->smsLogs()->paymentFollowUpToday()->latest('id')->first();
            $resend = $existing && (int) ($validated['resend_after'] ?? 0) === $existing->id;
            $reuse = $existing && ! $resend;
            $log = $reuse ? $existing : $sms->sendGuardianPaymentFollowUp($tenant, $validated['message'] ?? null);
            $status = $log->status;

            $message = match ($status) {
                'queued' => $reuse ? 'An SMS follow-up is already queued today.' : 'SMS queued in TextBee.',
                'unknown' => 'SMS status is unconfirmed. Check TextBee history before sending again.',
                'delivered' => 'SMS already delivered today.',
                'sent' => $reuse ? 'SMS already sent today.' : 'SMS sent to the guardian.',
                default => 'SMS could not be sent. '.($log->error_message ?: 'Check the TextBee settings and try again.'),
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

    /** The reminder for one tenant, filled in from the Settings template, for the proprietor to review or edit. */
    public function smsPreview(Tenant $tenant, PaymentRiskService $risk, GuardianSmsMessage $messages)
    {
        abort_unless($tenant->status === 'active' && $risk->evaluate($tenant)['risk_level'] === 'high', 422, 'SMS follow-up is available only for active high-risk tenants.');

        return response()->json($this->previewRow($tenant, $messages));
    }

    /** Every high-risk tenant whose guardian can be texted, each with a personalized message. */
    public function bulkSmsPreview(PaymentRiskService $risk, GuardianSmsMessage $messages)
    {
        $rows = $this->bulkCandidates($risk)->map(fn (Tenant $tenant) => $this->previewRow($tenant, $messages))->values();

        return response()->json(['recipients' => $rows]);
    }

    /**
     * Send the reviewed messages (one per tenant). Each tenant is re-checked on the server, and a
     * guardian already texted today is skipped so nobody gets the same reminder twice.
     */
    public function bulkSendSms(Request $request, PaymentRiskService $risk, SmsService $sms)
    {
        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:200'],
            'recipients.*.tenant_id' => ['required', 'integer', 'distinct'],
            'recipients.*.message' => ['required', 'string', 'max:'.GuardianSmsMessage::MAX_LENGTH],
        ], ['recipients.*.message.required' => 'One of the messages is empty. Write a message or untick that guardian.']);

        $eligible = $this->bulkCandidates($risk)->keyBy('id');
        $results = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => []];

        foreach ($validated['recipients'] as $item) {
            $tenant = $eligible->get($item['tenant_id']);
            $status = 'skipped';
            $reason = 'No longer high risk or no guardian number.';

            if ($tenant) {
                $lock = Cache::lock('dashboard.sms.'.$tenant->id, 30);
                if (! $lock->get()) {
                    $reason = 'Already being sent.';
                } else {
                    try {
                        if ($tenant->smsLogs()->paymentFollowUpToday()->exists()) {
                            $reason = 'Already texted today.';
                        } else {
                            $log = $sms->sendGuardianPaymentFollowUp($tenant, $item['message']);
                            $status = $log->status === 'failed' ? 'failed' : 'sent';
                            $reason = $log->status === 'failed' ? ($log->error_message ?: 'TextBee did not accept it.') : null;
                        }
                    } finally {
                        $lock->release();
                    }
                }
            }

            $results[$status]++;
            $results['rows'][] = ['tenant_id' => (int) $item['tenant_id'], 'status' => $status, 'reason' => $reason];
        }

        $results['message'] = sprintf('%d sent, %d skipped, %d failed.', $results['sent'], $results['skipped'], $results['failed']);

        return response()->json($results, $results['sent'] === 0 && $results['failed'] > 0 ? 502 : 200);
    }

    /**
     * Ask TextBee what happened to recent reminders (queued → sent/delivered/failed) and return
     * today's status per tenant. The dashboard calls this after loading, so statuses stay current
     * even when Laravel's scheduler (which normally runs sms:sync-textbee) isn't running.
     */
    public function refreshSmsStatuses(SmsService $sms)
    {
        $updated = 0;
        $error = null;
        try {
            $updated = $sms->syncTextBeeStatuses();
        } catch (\RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        $statuses = \App\Models\SmsLog::query()->paymentFollowUpToday()->orderBy('id')->get(['tenant_id', 'status'])
            ->mapWithKeys(fn ($log) => [$log->tenant_id => $log->status]);

        return response()->json(['updated' => $updated, 'statuses' => $statuses, 'error' => $error]);
    }

    /** Active high-risk tenants with a guardian number. */
    private function bulkCandidates(PaymentRiskService $risk)
    {
        return Tenant::with(['user', 'payments', 'monthModes', 'roomAssignments.bed.room'])
            ->where('status', 'active')->get()
            ->filter(fn (Tenant $tenant) => filled($tenant->guardian_contact_number) && $risk->evaluate($tenant)['risk_level'] === 'high')
            ->sortBy(fn (Tenant $tenant) => strtolower((string) $tenant->name))->values();
    }

    private function previewRow(Tenant $tenant, GuardianSmsMessage $messages): array
    {
        $filled = $messages->forTenant($tenant);
        $number = preg_replace('/\D+/', '', (string) $tenant->guardian_contact_number);

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => GuardianSmsMessage::properName((string) $tenant->name),
            'guardian_name' => $filled['guardian_name'],
            // Only the last digits are shown, so the full number never leaves the server here.
            'number_hint' => $number === '' ? null : '•••• '.substr($number, -4),
            'has_number' => $number !== '',
            'message' => $filled['message'],
            'stats' => GuardianSmsMessage::stats($filled['message']),
            'sent_today' => $tenant->smsLogs()->paymentFollowUpToday()->exists(),
        ];
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
                'fallback'     => false,
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
                'message' => 'Unable to generate a summary. '.$exception->getMessage(),
            ], 502);
        }
    }
}
