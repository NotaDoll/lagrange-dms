<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomAssignmentRequest;
use App\Models\RoomAssignment;
use App\Services\RoomAssignmentService;

class RoomAssignmentController extends Controller
{
    public function __construct(private readonly RoomAssignmentService $assignmentService)
    {
    }

    public function store(StoreRoomAssignmentRequest $request)
    {
        $this->assignmentService->assign(
            (int) $request->validated('tenant_id'),
            (int) $request->validated('bed_id')
        );

        return redirect()
            ->route('proprietor.rooms.index')
            ->with('success', 'Tenant assigned successfully.');
    }

    public function update(RoomAssignment $roomAssignment)
    {
        $this->assignmentService->end($roomAssignment);

        return redirect()
            ->route('proprietor.rooms.index')
            ->with('success', 'Assignment ended successfully.');
    }
}
