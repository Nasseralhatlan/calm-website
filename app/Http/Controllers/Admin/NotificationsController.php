<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBroadcastRequest;
use App\Models\NotificationBroadcast;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin "send updates to users" surface — compose a bilingual message, pick an
 * audience, and fan it out across SMS + push + in-app. Past broadcasts list
 * below the form for reference.
 */
class NotificationsController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(): View
    {
        return view('admin.notifications.index', [
            'broadcasts' => NotificationBroadcast::query()->with('admin:id,name,phone')->latest()->paginate(20),
        ]);
    }

    public function store(StoreBroadcastRequest $request): RedirectResponse
    {
        $broadcast = $this->notifications->broadcast(
            $request->user(),
            $request->validated()['audience'],
            $request->validated(),
        );

        return redirect()
            ->route('admin.notifications.index')
            ->with('status', __('Broadcast sent to :count user(s).', ['count' => $broadcast->recipients_count]));
    }
}
