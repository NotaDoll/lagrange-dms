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

    public function sendSms(Tenant $tenant, PaymentRiskService $risk, SmsService $sms)
    {
        abort_unless($tenant->status === 'active' && $risk->evaluate($tenant)['risk_level'] === 'high', 422, 'SMS follow-up is available only for active high-risk tenants.');
        if (! filled($tenant->user?->contact_number)) {
            return response()->json(['message' => 'This tenant has no contact number. Update their profile first.'], 422);
        }
        $lock = Cache::lock('dashboard.sms.'.$tenant->id, 30);
        if (! $lock->get()) {
            return response()->json(['message' => 'A follow-up is already being sent. Please wait.'], 409);
        }
        try {
            if ($tenant->smsLogs()->where('status', 'sent')->where('recipient_type', 'tenant')->whereDate('sent_at', today())->where(fn ($query) => $query->where('message', 'like', 'Payment follow-up:%')->orWhere('message', 'like', 'Your dormitory account has been flagged high risk%'))->exists()) {
                return response()->json(['message' => 'SMS already sent today.', 'sent' => true]);
            }
            $sent = $sms->send($tenant->user->contact_number, 'Payment follow-up: Your dormitory account is flagged high risk due to repeated late payments. Please contact the proprietor to settle outstanding balances.', $tenant);

            return response()->json(['message' => $sent ? 'SMS sent to the tenant.' : 'SMS could not be sent. Check your SMS configuration and try again.', 'sent' => $sent], $sent ? 200 : 502);
        } finally {
            $lock->release();
        }
    }

    public function generateSummary(Request $request, ComplaintSummarizationService $summarizer)
    {
        $complaints = Complaint::whereIn('status', ['submitted', 'in_progress'])->latest()->get(['category', 'description', 'status', 'created_at']);
        if ($complaints->isEmpty()) {
            return response()->json(['message' => 'There are no open complaints to summarize.'], 422);
        }
        try {
            $summary = $summarizer->summarize($complaints, ['generated_by' => $request->user()->id, 'structured' => true, 'status' => 'open']);

            return response()->json(['message' => 'Summary generated and saved.', 'id' => $summary->id]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to generate a summary. Check the AI provider configuration or connection, then try again. Your previous summary is still available.'], 502);
        }
    }
}
