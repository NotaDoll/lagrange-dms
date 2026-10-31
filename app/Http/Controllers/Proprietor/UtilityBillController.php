<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUtilityBillRequest;
use App\Http\Requests\UpdateUtilityBillRequest;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\UtilityBill;
use App\Services\UtilityBillLedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UtilityBillController extends Controller
{
    public function __construct(private readonly UtilityBillLedgerService $ledger) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'overdue'])],
            'bill_type' => ['nullable', Rule::in(['electricity', 'water', 'internet', 'other'])],
        ]);
        $roomId = $filters['room_id'] ?? null;
        $status = $filters['status'] ?? null;
        $billType = $filters['bill_type'] ?? null;
        $bills = UtilityBill::with(['room', 'payments.tenant.user'])
            ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($billType, fn ($query) => $query->where('bill_type', $billType))
            ->latest('due_date')->paginate(15)->withQueryString();
        $rooms = Room::orderBy('room_number')->get();

        return view('proprietor.utility-bills.index', compact('bills', 'rooms', 'roomId', 'status', 'billType'));
    }

    public function create()
    {
        return view('proprietor.utility-bills.create', $this->formData());
    }

    public function store(StoreUtilityBillRequest $request)
    {
        $this->ledger->create($request->validated());

        return redirect()->route('proprietor.utility-bills.index')->with('success', 'Utility bill recorded. Tenant shares were added to their balances.');
    }

    public function edit(UtilityBill $utilityBill)
    {
        $utilityBill->load('payments.tenant.user');

        return view('proprietor.utility-bills.edit', ['utilityBill' => $utilityBill, ...$this->formData()]);
    }

    public function update(UpdateUtilityBillRequest $request, UtilityBill $utilityBill)
    {
        $this->ledger->update($utilityBill, $request->validated());

        return redirect()->route('proprietor.utility-bills.index')->with('success', 'Utility bill and tenant balances updated.');
    }

    public function destroy(UtilityBill $utilityBill)
    {
        $this->ledger->delete($utilityBill);

        return redirect()->route('proprietor.utility-bills.index')->with('success', 'Utility bill and its unpaid tenant shares removed.');
    }

    private function formData(): array
    {
        $rooms = Room::orderBy('room_number')->get();
        $roomAssignments = RoomAssignment::with(['tenant.user', 'bed'])->get()->map(fn ($assignment) => [
            'room_id' => $assignment->bed->room_id,
            'tenant_id' => $assignment->tenant_id,
            'name' => $assignment->tenant->user->name,
            'start' => $assignment->start_date->toDateString(),
            'end' => $assignment->end_date?->toDateString(),
        ]);

        return compact('rooms', 'roomAssignments');
    }
}
