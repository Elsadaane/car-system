<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\SalesContract;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * لوحة الموظف / الإدارة.
 *
 * ملاحظة على الفروع: الفلترة بالفرع متحطة هنا من دلوقتي.
 * النسخة اللي عندها فرع واحد بتشتغل عادي من غير ما المستخدم يحس.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $this->currentBranch($request);

        return view('staff.dashboard', [
            'vehiclesAvailable' => Vehicle::available()
                ->when($branchId, fn ($q) => $q->forBranch($branchId))->count(),

            'contractsThisMonth' => SalesContract::whereMonth('contract_date', now()->month)
                ->whereYear('contract_date', now()->year)
                ->when($branchId, fn ($q) => $q->forBranch($branchId))->count(),

            'overdueInstallments' => Installment::overdue()->count(),

            'overdueAmount' => (float) Installment::overdue()
                ->selectRaw('COALESCE(SUM(amount + late_fees - paid_amount), 0) as t')
                ->value('t'),

            'suppliersToSettle' => Supplier::settlementOverdue()->count(),

            'dueThisWeek' => Installment::upcoming(7)
                ->with('customer:id,name_ar,phone', 'salesContract:id,contract_number')
                ->orderBy('due_date')
                ->limit(10)
                ->get(),

            'latestContracts' => SalesContract::with('customer:id,name_ar', 'vehicle.brand')
                ->when($branchId, fn ($q) => $q->forBranch($branchId))
                ->latest('contract_date')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * الأدمن بيشوف كل الفروع، الموظف بيشوف فرعه بس.
     */
    protected function currentBranch(Request $request): ?int
    {
        $user = $request->user();

        return $user->role === 'admin' ? null : $user->branch_id;
    }
}
