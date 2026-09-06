<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUtilityBillRequest;
use App\Http\Requests\UpdateUtilityBillRequest;
use App\Models\Room;
use App\Models\UtilityBill;
use App\Services\UtilityBillLedgerService;
use Illuminate\Http\Request;

class UtilityBillController extends Controller
{
    public function __construct(private readonly UtilityBillLedgerService $ledger)
    {
    }

    public function index(Request $request)
    {
        $roomId = $request->query('room_id');
        $status = $request->query('status');

        $bills = UtilityBill::with('room')
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('due_date')
            ->paginate(15)
            ->withQueryString();

        $rooms = Room::orderBy('room_number')->get();

        return view('proprietor.utility-bills.index', compact('bills', 'rooms', 'roomId', 'status'));
    }

    public function create()
    {
        $rooms = Room::orderBy('room_number')->get();

        return view('proprietor.utility-bills.create', compact('rooms'));
    }

    public function store(StoreUtilityBillRequest $request)
    {
        $this->ledger->create($request->validated());

        return redirect()
            ->route('proprietor.utility-bills.index')
            ->with('success', 'Utility bill recorded successfully.');
    }

    public function edit(UtilityBill $utilityBill)
    {
        $rooms = Room::orderBy('room_number')->get();

        return view('proprietor.utility-bills.edit', compact('utilityBill', 'rooms'));
    }

    public function update(UpdateUtilityBillRequest $request, UtilityBill $utilityBill)
    {
        $this->ledger->update($utilityBill, $request->validated());

        return redirect()
            ->route('proprietor.utility-bills.index')
            ->with('success', 'Utility bill updated successfully.');
    }

    public function destroy(UtilityBill $utilityBill)
    {
        $utilityBill->delete();

        return redirect()
            ->route('proprietor.utility-bills.index')
            ->with('success', 'Utility bill deleted.');
    }
}