<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use App\Models\Tenant;
use App\Services\RoomManagementService;

class RoomController extends Controller
{
    public function __construct(private readonly RoomManagementService $roomService)
    {
    }

    public function index()
    {
        $rooms = Room::with(['beds.currentAssignment.tenant.user'])
            ->orderBy('room_number')
            ->get();

        $availableTenants = Tenant::with('user')
            ->where('status', 'active')
            ->whereDoesntHave('currentAssignment')
            ->join('users', 'tenants.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('tenants.*')
            ->get();

        return view('proprietor.rooms.index', compact('rooms', 'availableTenants'));
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
        $room->delete();

        return redirect()
            ->route('proprietor.rooms.index')
            ->with('success', 'Room deleted successfully.');
    }
}
