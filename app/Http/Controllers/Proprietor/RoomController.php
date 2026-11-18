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
        $availability = $request->query('availability', '');
        $roomTypes = Room::availableTypes();
        $type = $request->query('type');
        $type = is_string($type) ? Room::normalizeType($type) : null;

        $query = Room::query()->with(['beds.room', 'beds.currentAssignment.tenant.user']);

        if ($search !== '') {
            $query->where(function ($roomQuery) use ($search) {
                $roomQuery->where('room_number', 'like', "%{$search}%")
                    ->orWhereHas('beds.currentAssignment.tenant.user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($type, $roomTypes, true)) {
            $roomIds = Room::all()->filter(fn (Room $room) => $room->catalogType() === $type)->modelKeys();
            $query->whereKey($roomIds);
        } else {
            $type = null;
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

        $rooms = $query->orderBy('room_number')->paginate(10)->appends($request->only(['search', 'type', 'availability']));

        $totalRooms = Room::count();
        $totalBeds = \App\Models\Bed::count();
        $occupiedBeds = \App\Models\RoomAssignment::where('status', 'active')->count();
        $vacantBeds = \App\Models\Bed::where('status', 'available')->whereDoesntHave('currentAssignment')->count();
        $rate = $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100, 1) : 0;

        $availableTenants = Tenant::with('user')
            ->where('status', 'active')
            ->whereDoesntHave('currentAssignment')
            ->get()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('proprietor.rooms.index', compact(
            'rooms', 'availableTenants', 'search', 'availability', 'type', 'roomTypes',
            'totalRooms', 'totalBeds', 'occupiedBeds', 'vacantBeds', 'rate'
        ));
    }

    public function create()
    {
        return view('proprietor.rooms.create', [
            'roomTypes' => Room::availableTypes(),
            'addType' => StoreRoomRequest::ADD_TYPE,
        ]);
    }

    public function store(StoreRoomRequest $request)
    {
        $this->roomService->create($request->roomAttributes());

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
