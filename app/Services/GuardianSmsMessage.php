<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * The payment reminder sent to a tenant's guardian. The proprietor edits the wording in Settings;
 * the bracketed placeholders are filled in per tenant when the message is previewed or sent.
 */
class GuardianSmsMessage
{
    /** Worded to fit one text (160 characters) with typical names; see forTenant() for long names. */
    public const DEFAULT_TEMPLATE = "Dear [Guardian Name], [Tenant Name] has an overdue dormitory balance of PHP [Amount]. Kindly settle it soon. Thank you.\n- La Grange Dormitory Management";

    /**
     * Placeholder => what it becomes (shown to the proprietor in Settings). [Due Date] still works
     * in a saved custom message but isn't offered, since reminders no longer name a date.
     */
    public const PLACEHOLDERS = [
        '[Guardian Name]' => "The guardian's name, or \"Parent/Guardian\" if none is saved",
        '[Tenant Name]' => "The tenant's full name",
        '[Amount]' => 'Total unpaid rent and charges that are past due, e.g. 3,000.00',
    ];

    public const MAX_LENGTH = 480;

    /** Every character the GSM-7 alphabet sends at 1 unit; anything else forces Unicode (70 per text). */
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** GSM-7 characters that take 2 units each. */
    private const GSM_EXTENDED = '^{}\\[~]|€';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly TenantBalanceService $balances,
    ) {}

    public function template(): string
    {
        return filled($saved = $this->settings->get('sms', 'guardian_template')) ? $saved : self::DEFAULT_TEMPLATE;
    }

    /**
     * The message for one tenant, with placeholders filled in. If the full names would push it
     * past one text, the names are shortened step by step ("Juan Miguel Antonio Dela Cruz" →
     * "Juan Dela Cruz" → "Juan") until it fits, so each guardian costs a single text.
     *
     * @return array{message: string, guardian_name: string, amount: float, due_date: ?Carbon}
     */
    public function forTenant(Tenant $tenant, ?string $template = null): array
    {
        [$amount, $due] = $this->amountAndDueDate($tenant);
        $guardian = filled($tenant->guardian_name) ? self::properName($tenant->guardian_name) : 'Parent/Guardian';
        $tenantName = self::properName((string) $tenant->name);
        $isNamed = filled($tenant->guardian_name);

        $attempts = [
            [$guardian, $tenantName],
            [$guardian, self::shortName($tenantName)],
            [$isNamed ? self::shortName($guardian) : $guardian, self::shortName($tenantName)],
            [$isNamed ? self::firstName($guardian) : $guardian, self::firstName($tenantName)],
        ];

        foreach ($attempts as [$guardianText, $tenantText]) {
            $message = $this->fill($template ?? $this->template(), [
                '[Guardian Name]' => $guardianText,
                '[Tenant Name]' => $tenantText,
                '[Amount]' => number_format($amount, 2),
                '[Due Date]' => $due?->format('F j, Y') ?? 'the due date',
            ]);
            if (self::stats($message)['texts'] === 1) {
                break;
            }
        }

        return [
            'message' => $message,
            'guardian_name' => $guardian,
            'amount' => $amount,
            'due_date' => $due,
        ];
    }

    /** Words that start a Filipino surname, so "Juan Miguel Dela Cruz" shortens to "Juan Dela Cruz", not "Juan Cruz". */
    private const SURNAME_PARTICLES = ['de', 'dela', 'del', 'delos', 'della', 'di', 'la', 'las', 'los', 'san', 'santa', 'santo', 'sta', 'sta.', 'sto', 'sto.', 'van', 'von'];

    /** First name plus surname: "Barbie Angel Joy Royo" → "Barbie Royo". */
    public static function shortName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name));
        if (count($words) <= 2) {
            return implode(' ', $words);
        }
        foreach (array_slice($words, 1, -1, true) as $index => $word) {
            if (in_array(mb_strtolower($word), self::SURNAME_PARTICLES, true)) {
                return $words[0].' '.implode(' ', array_slice($words, $index));
            }
        }

        return $words[0].' '.end($words);
    }

    public static function firstName(string $name): string
    {
        return preg_split('/\s+/u', trim($name))[0];
    }

    /** Fill placeholders with sample values, for the Settings preview. */
    public function sample(?string $template = null): string
    {
        return $this->fill($template ?? $this->template(), [
            '[Guardian Name]' => 'Maria Dela Cruz',
            '[Tenant Name]' => 'Juan Dela Cruz',
            '[Amount]' => '3,000.00',
            '[Due Date]' => 'October 12, 2026',
        ]);
    }

    /**
     * How the message will be billed: characters, number of texts, and whether a character
     * (such as ₱ or a curly quote) forces Unicode, which fits 70 characters per text instead of 160.
     *
     * @return array{characters: int, texts: int, unicode: bool, unicode_characters: list<string>}
     */
    public static function stats(string $message): array
    {
        $chars = mb_str_split($message);
        $unicode = array_values(array_unique(array_filter($chars, fn ($c) => ! str_contains(self::GSM_BASIC, $c) && ! str_contains(self::GSM_EXTENDED, $c))));

        if ($unicode !== []) {
            $length = count($chars);

            return ['characters' => $length, 'texts' => $length <= 70 ? 1 : (int) ceil($length / 67), 'unicode' => true, 'unicode_characters' => $unicode];
        }

        $units = array_sum(array_map(fn ($c) => str_contains(self::GSM_EXTENDED, $c) ? 2 : 1, $chars));

        return ['characters' => $units, 'texts' => $units <= 160 ? 1 : (int) ceil($units / 153), 'unicode' => false, 'unicode_characters' => []];
    }

    /**
     * Names imported in ALL CAPS ("JUAN DELA CRUZ") read like shouting in a text, so they become
     * "Juan Dela Cruz". A name typed with its own capitals (e.g. "Ana de la Peña") is kept as is.
     */
    public static function properName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        return $name === mb_strtoupper($name) ? mb_convert_case(mb_strtolower($name), MB_CASE_TITLE) : $name;
    }

    private function fill(string $template, array $values): string
    {
        // Accept the curly-apostrophe spellings people often type, e.g. "[Guardian’s Name]".
        $template = str_ireplace(["[Guardian’s Name]", "[Guardian's Name]", "[Tenant’s Name]", "[Tenant's Name]"], ['[Guardian Name]', '[Guardian Name]', '[Tenant Name]', '[Tenant Name]'], $template);

        return str_ireplace(array_keys($values), array_values($values), $template);
    }

    /** @return array{0: float, 1: ?Carbon} total unpaid so far, and the oldest unpaid due date */
    private function amountAndDueDate(Tenant $tenant): array
    {
        $today = Tenant::localDate();
        $unpaid = $this->balances->billingEntries($tenant, $this->balances->ledgerStart($tenant), $today)
            ->filter(fn (Payment $entry) => ! $entry->hasRecordedPayment() && $entry->due_date->lte($today));

        if ($unpaid->isNotEmpty()) {
            return [round((float) $unpaid->sum('amount'), 2), $unpaid->min('due_date')];
        }

        // Nothing overdue: remind about this month's rent instead.
        $balance = $this->balances->monthBalance($tenant, Carbon::parse($today));

        return [(float) $balance['remaining'], $balance['charge']?->due_date];
    }
}
