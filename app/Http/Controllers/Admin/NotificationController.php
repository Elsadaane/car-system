<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Alerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** الجرس اللي فوق: التنبيهات المحسوبة لكل موظف حسب صلاحياته وفرعه */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $alerts = Alerts::for($request->user());

        return view($request->boolean('partial') ? 'admin.notifications.list' : 'admin.notifications.index', ['alerts' => $alerts]);
    }

    /** فتح الجرس = شاف اللي فيه (يرجع يظهر لو الرقم اتغيّر) */
    public function seen(Request $request): JsonResponse
    {
        if ($request->boolean('refresh')) {
            Alerts::forget($request->user());
        }
        Alerts::markSeen($request->user());

        return response()->json(['ok' => true]);
    }
}
