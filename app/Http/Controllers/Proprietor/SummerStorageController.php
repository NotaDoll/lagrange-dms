<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMonthMode;
use App\Services\SummerStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SummerStorageController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate($this->dateRules());
        $year = (int) ($validated['year'] ?? Carbon::parse(Tenant::localDate())->year);
        $months = $this->months($year, $validated['months'] ?? [6, 7]);
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->with(['user', 'monthModes' => fn ($query) => $query
            ->whereDate('month', '>=', $months[0])->whereDate('month', '<=', end($months))])
            ->get()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);

        return view('proprietor.tenants.summer-storage', ['tenants' => $tenants, 'year' => $year, 'months' => collect($months)]);
    }

    public function store(Request $request, SummerStorageService $storage)
    {
        $editing = $request->input('action') === 'amount';
        $validated = $request->validate([
            ...$this->dateRules(true),
            'action' => ['required', Rule::in([TenantMonthMode::REGULAR, TenantMonthMode::STORAGE, 'amount'])],
            'tenants' => ['exclude_if:action,amount', 'required', 'array', 'min:1'],
            'tenants.*' => ['integer', 'distinct'],
            'tenant_id' => ['exclude_unless:action,amount', 'required', 'integer'],
            'amount_month' => ['exclude_unless:action,amount', 'required', 'integer', 'between:1,12'],
            'amount' => ['exclude_unless:action,amount', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ]);

        $year = (int) $validated['year'];
        $months = $this->months($year, $editing ? [$validated['amount_month']] : $validated['months']);
        $mode = $editing ? TenantMonthMode::STORAGE : $validated['action'];
        $result = $storage->update(
            $editing ? [$validated['tenant_id']] : $validated['tenants'],
            $months,
            $mode,
            isset($validated['amount']) ? (string) $validated['amount'] : null,
            $editing,
        );

        return redirect()->route('proprietor.summer-storage.index', [
            'year' => $year, 'months' => $validated['months'],
        ])->with('storage_result', [
            ...$result,
            'months' => implode(', ', array_map(fn (Carbon $month) => $month->format('F Y'), $months)),
        ]);
    }

    private function dateRules(bool $required = false): array
    {
        return [
            'year' => [$required ? 'required' : 'sometimes', 'integer', 'between:1900,9999'],
            'months' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:12'],
            'months.*' => ['integer', 'between:1,12', 'distinct'],
        ];
    }

    private function months(int $year, array $numbers): array
    {
        sort($numbers, SORT_NUMERIC);

        return array_map(fn ($number) => Carbon::create($year, (int) $number, 1, 0, 0, 0), $numbers);
    }
}
