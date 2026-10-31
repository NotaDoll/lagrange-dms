<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use App\Models\Bed;
use App\Models\Tenant;
use App\Services\RoomManagementService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(private readonly RoomManagementService $roomService)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $floor = trim((string) $request->query('floor', ''));
        $availability = $request->query('availability', '');

        $query = Room::query()->with(['beds.currentAssignment.tenant.user']);

        if ($search !== '') {
            $query->where(function ($roomQuery) use ($search) {
                $roomQuery->where('room_number', 'like', "%{$search}%")
                    ->orWhereHas('beds.currentAssignment.tenant.user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($floor !== '') {
            $query->where('floor', $floor);
        }

        match ($availability) {
            'full' => $query->whereHas('beds.currentAssignment')
                ->whereDoesntHave('beds', fn ($beds) => $beds->doesntHave('currentAssignment')),
            'partial' => $query->whereHas('beds.currentAssignment')
                ->whereHas('beds', fn ($beds) => $beds->where('status', 'available')->doesntHave('currentAssignment')),
            'vacant' => $query->whereHas('beds', fn ($beds) => $beds->where('status', 'available'))
                ->whereDoesntHave('beds', fn ($beds) => $beds->where('status', '!=', 'available')->orWhereHas('currentAssignment')),
            'empty' => $query->doesntHave('beds'),
            'maintenance' => $query->whereHas('beds', fn ($beds) => $beds->where('status', 'maintenance'))
                ->whereDoesntHave('beds', fn ($beds) => $beds->where('status', '!=', 'maintenance')),
            default => $availability = '',
        };

        $rooms = $query->orderBy('room_number')->paginate(12)->withQueryString();

        $totalRooms = Room::count();
        $totalBeds = \App\Models\Bed::count();
        $occupiedBeds = \App\Models\RoomAssignment::where('status', 'active')->count();
        $vacantBeds = \App\Models\Bed::where('status', 'available')->whereDoesntHave('currentAssignment')->count();
        $rate = $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100, 1) : 0;
        $floors = Room::query()->select('floor')->distinct()->orderBy('floor')->pluck('floor');

        $availableTenants = Tenant::with('user')
            ->where('status', 'active')
            ->whereDoesntHave('currentAssignment')
            ->join('users', 'tenants.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('tenants.*')
            ->get();

        return view('proprietor.rooms.index', compact(
            'rooms', 'availableTenants', 'search', 'floor', 'availability',
            'totalRooms', 'totalBeds', 'occupiedBeds', 'vacantBeds', 'rate', 'floors'
        ));
    }

    public function create()
    {
        return view('proprietor.rooms.create');
    }

    public function store(StoreRoomRequest $request)
    {
        $this->roomService->create($request->validated());

        return redirect()
            ->route('proprietor.rooms.index')
            ->with('success', 'Room created successfully.');
    }

    public function edit(Room $room)
    {
        return view('proprietor.rooms.edit', compact('room'));
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $this->roomService->update($room, $request->validated());

        return redirect()
            ->route('proprietor.rooms.index')
            ->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room)
    {
        $this->roomService->delete($room);

        return redirect()
            ->route('proprietor.rooms.index')
            ->with('success', 'Room deleted successfully.');
    }

    public function updateBedStatus(Request $request, Bed $bed)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:available,maintenance'],
        ]);

        $this->roomService->updateBedStatus($bed, $validated['status']);

        return redirect()
            ->route('proprietor.rooms.index')
            ->with('success', 'Bed status updated successfully.');
    }
}
