<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        abort_if(! $customer, 403);

        $notifications = $customer->receivedNotifications()
            ->recent()
            ->paginate(20);

        return view('customer.notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_if(
            $notification->recipient_id !== $request->user()->customer_id
            || $notification->recipient_type !== \App\Models\Customer::class,
            403
        );

        $notification->markAsRead();

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->customer
            ->receivedNotifications()
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('status', 'تم تعليم كل الإشعارات كمقروءة.');
    }
}
