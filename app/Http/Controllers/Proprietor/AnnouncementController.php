<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Services\NotificationService;

class AnnouncementController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        return redirect()->route('proprietor.announcements-faqs.index', [
            ...$request->query(),
            'tab' => 'announcements',
        ]);
    }

    public function create()
    {
        return view('proprietor.announcements.create');
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $announcement = Announcement::create([
            'created_by' => auth()->id(),
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
        ]);

        Tenant::with('user')
            ->get()
            ->each(function (Tenant $tenant) use ($announcement) {
                $this->notificationService->notify(
                    $tenant->user,
                    'announcement',
                    sprintf('New announcement published: %s', $announcement->title)
                );
            });

        return redirect()
            ->route('proprietor.announcements-faqs.index', ['tab' => 'announcements'])
            ->with('success', 'Announcement created successfully.');
    }

    public function edit(Announcement $announcement)
    {
        return view('proprietor.announcements.edit', compact('announcement'));
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement)
    {
        $announcement->update($request->validated());

        return redirect()
            ->route('proprietor.announcements-faqs.index', ['tab' => 'announcements'])
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()
            ->route('proprietor.announcements-faqs.index', ['tab' => 'announcements'])
            ->with('success', 'Announcement deleted successfully.');
    }
}
