<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Storefront;
use App\Support\Module;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| المتجر الإلكتروني
|--------------------------------------------------------------------------
*/

if (Module::enabled('storefront')) {

    // كل صفحات الموقع — بتتسجل مرتين: عربي على الروابط العادية، وإنجليزي تحت /en
    $storefront = function (bool $withSeo) {
        Route::get('/', [Storefront\HomeController::class, 'index'])->name('home');

        Route::get('/cars', [Storefront\VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/cars/{vehicle}', [Storefront\VehicleController::class, 'show'])->name('vehicles.show');
        Route::post('/cars/{vehicle}/inquiry', [Storefront\InquiryController::class, 'store'])
            ->middleware('throttle:10,1')->name('inquiries.store');

        Route::get('/offers', [Storefront\VehicleController::class, 'offers'])->name('offers');
        Route::get('/compare', [Storefront\VehicleController::class, 'compare'])->name('compare');
        Route::get('/favorites', [Storefront\VehicleController::class, 'favorites'])->name('favorites');

        Route::get('/contact', [Storefront\PageController::class, 'contact'])->name('contact');
        Route::get('/sell-your-car', [Storefront\PageController::class, 'sell'])->name('sell');
        Route::get('/finance', [Storefront\PageController::class, 'finance'])->name('finance');
        Route::get('/about', [Storefront\PageController::class, 'about'])->name('about');
        Route::get('/terms', [Storefront\PageController::class, 'terms'])->name('terms');
        Route::get('/privacy', [Storefront\PageController::class, 'privacy'])->name('privacy');

        Route::post('/leads', [Storefront\InquiryController::class, 'lead'])
            ->middleware('throttle:10,1')->name('leads.store');

        Route::get('/blog', [Storefront\BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/category/{category}', [Storefront\BlogController::class, 'index'])->name('blog.category');
        Route::get('/blog/{slug}', [Storefront\BlogController::class, 'show'])->name('blog.show');

        if ($withSeo) {
            Route::get('/sitemap.xml', [Storefront\SeoController::class, 'sitemap'])->name('sitemap');
            Route::get('/robots.txt', [Storefront\SeoController::class, 'robots'])->name('robots');
        }
    };

    Route::middleware('locale:ar')->name('storefront.')->group(fn () => $storefront(true));
    Route::middleware('locale:en')->prefix('en')->name('storefront.en.')->group(fn () => $storefront(false));

} else {

    Route::get('/', fn () => redirect()->route('login'))->name('storefront.home');
}

Route::redirect('/home', '/')->name('home');


/*
|--------------------------------------------------------------------------
| لوحة الإدارة
|--------------------------------------------------------------------------
| أي صفحة أدمن جديدة بتتضاف جوه المجموعة دي - قبل آخر });
*/

Route::middleware(['auth', 'role:staff,admin', 'locale:user', 'perm', 'audit'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [Admin\DashboardController::class, 'index'])
            ->name('dashboard');
        Route::post('dashboard/layout', [Admin\DashboardController::class, 'saveLayout'])->name('dashboard.layout');
        Route::get('dashboard/widget/{key}', [Admin\DashboardController::class, 'widget'])->where('key', '[a-z_]+')->name('dashboard.widget');
        Route::post('locale/{locale}', [Admin\DashboardController::class, 'locale'])->whereIn('locale', ['ar', 'en'])->name('locale.switch');
        Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/seen', [Admin\NotificationController::class, 'seen'])->name('notifications.seen');

        // ─── الاستفسارات ───
        Route::get('inquiries', [Admin\InquiryController::class, 'index'])
            ->name('inquiries.index');

        Route::patch('inquiries/{inquiry}/status', [Admin\InquiryController::class, 'updateStatus'])
            ->name('inquiries.status');
        Route::patch('inquiries/{inquiry}/move', [Admin\InquiryController::class, 'move'])->whereNumber('inquiry')->name('inquiries.move');
        Route::post('inquiries/spend', [Admin\InquiryController::class, 'storeSpend'])->name('inquiries.spend.store');
        Route::delete('inquiries/spend/{spend}', [Admin\InquiryController::class, 'destroySpend'])->whereNumber('spend')->name('inquiries.spend.destroy');

        // ─── الفروع ───
        Route::get('branches', [Admin\BranchController::class, 'index'])
            ->name('branches.index');

        Route::group([], function () {
            Route::get('branches/create', [Admin\BranchController::class, 'create'])->name('branches.create');
            Route::post('branches', [Admin\BranchController::class, 'store'])->name('branches.store');
            Route::get('branches/{branch}/edit', [Admin\BranchController::class, 'edit'])->name('branches.edit');
            Route::put('branches/{branch}', [Admin\BranchController::class, 'update'])->name('branches.update');
            Route::delete('branches/{branch}', [Admin\BranchController::class, 'destroy'])->name('branches.destroy');
            Route::patch('branches/{branch}/vehicles/{vehicle:id}/transfer', [Admin\BranchController::class, 'transferVehicle'])
                ->name('branches.vehicles.transfer');
        });

        Route::get('branches/{branch}', [Admin\BranchController::class, 'show'])
            ->name('branches.show');

        // ─── المركبات ───
        Route::get('vehicles', [Admin\VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/create', [Admin\VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('vehicles', [Admin\VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('vehicles/{vehicle:id}', [Admin\VehicleController::class, 'show'])->name('vehicles.show');
        Route::get('vehicles/{vehicle:id}/edit', [Admin\VehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('vehicles/{vehicle:id}', [Admin\VehicleController::class, 'update'])->name('vehicles.update');
        Route::patch('vehicles/{vehicle:id}/publish', [Admin\VehicleController::class, 'togglePublish'])->name('vehicles.publish');
        Route::delete('vehicles/{vehicle:id}', [Admin\VehicleController::class, 'destroy'])->name('vehicles.destroy');

        // ─── الماركات والطرازات وجهات الاستيراد ───
        Route::get('brands', [Admin\CatalogController::class, 'brands'])->name('brands.index');
        Route::post('brands', [Admin\CatalogController::class, 'storeBrand'])->name('brands.store');
        Route::put('brands/{brand}', [Admin\CatalogController::class, 'updateBrand'])->name('brands.update');
        Route::delete('brands/{brand}', [Admin\CatalogController::class, 'destroyBrand'])->name('brands.destroy');

        Route::get('car-models', [Admin\CatalogController::class, 'models'])->name('car-models.index');
        Route::post('car-models', [Admin\CatalogController::class, 'storeModel'])->name('car-models.store');
        Route::put('car-models/{carModel}', [Admin\CatalogController::class, 'updateModel'])->name('car-models.update');
        Route::delete('car-models/{carModel}', [Admin\CatalogController::class, 'destroyModel'])->name('car-models.destroy');

        Route::get('import-sources', [Admin\CatalogController::class, 'sources'])->name('import-sources.index');
        Route::post('import-sources', [Admin\CatalogController::class, 'storeSource'])->name('import-sources.store');
        Route::put('import-sources/{importSource}', [Admin\CatalogController::class, 'updateSource'])->name('import-sources.update');
        Route::delete('import-sources/{importSource}', [Admin\CatalogController::class, 'destroySource'])->name('import-sources.destroy');

        // ─── العملاء ───
        Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/create', [Admin\CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [Admin\CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/statement', [Admin\CustomerController::class, 'statement'])->name('customers.statement');
        Route::get('customers/{customer}/edit', [Admin\CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [Admin\CustomerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/account', [Admin\CustomerController::class, 'createAccount'])->name('customers.account');
        Route::post('customers/{customer}/reset-password', [Admin\CustomerController::class, 'resetPassword'])->name('customers.reset-password');
        Route::post('customers/{customer}/notes', [Admin\CustomerController::class, 'addNote'])->name('customers.notes');

        // ─── المرفقات (عملاء، موردين، عربيات) ───
        Route::post('attachments/{type}/{id}', [Admin\AttachmentController::class, 'store'])
            ->whereIn('type', ['customer', 'supplier', 'vehicle'])->whereNumber('id')->name('attachments.store');
        Route::get('attachments/{attachment}/download', [Admin\AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [Admin\AttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::delete('customers/{customer}', [Admin\CustomerController::class, 'destroy'])->name('customers.destroy');

        // ─── عقود التفويض ───
        Route::get('dealer-contracts', [Admin\DealerContractController::class, 'index'])->name('dealer-contracts.index');
        Route::get('dealer-contracts/create', [Admin\DealerContractController::class, 'create'])->name('dealer-contracts.create');
        Route::post('dealer-contracts', [Admin\DealerContractController::class, 'store'])->name('dealer-contracts.store');
        Route::get('dealer-contracts/{dealerContract}', [Admin\DealerContractController::class, 'show'])->name('dealer-contracts.show');
        Route::get('dealer-contracts/{dealerContract}/edit', [Admin\DealerContractController::class, 'edit'])->name('dealer-contracts.edit');
        Route::put('dealer-contracts/{dealerContract}', [Admin\DealerContractController::class, 'update'])->name('dealer-contracts.update');
        Route::get('dealer-contracts/{dealerContract}/print', [Admin\DealerContractController::class, 'print'])->name('dealer-contracts.print');
        Route::post('dealer-contracts/{dealerContract}/sign/{party}', [Admin\DealerContractController::class, 'sign'])
            ->whereIn('party', ['owner', 'showroom'])->name('dealer-contracts.sign');
        Route::patch('dealer-contracts/{dealerContract}/cancel', [Admin\DealerContractController::class, 'cancel'])->name('dealer-contracts.cancel');
        Route::patch('dealer-contracts/{dealerContract}/price', [Admin\DealerContractController::class, 'changePrice'])->name('dealer-contracts.price');
        Route::patch('dealer-contracts/{dealerContract}/price-requests/{priceRequest}/cancel', [Admin\DealerContractController::class, 'cancelPriceRequest'])
            ->whereNumber('priceRequest')->name('dealer-contracts.price-requests.cancel');
        Route::get('dealer-payments/{payment}/receipt', [Admin\DealerPaymentController::class, 'receipt'])->name('dealer-payments.receipt');

        // ─── الشيكات المؤجلة ───
        Route::get('cheques', [Admin\ChequeController::class, 'index'])->name('cheques.index');
        Route::post('sales-contracts/{salesContract}/cheques', [Admin\ChequeController::class, 'store'])->name('sales-contracts.cheques.store');
        Route::patch('cheques/{cheque}/action', [Admin\ChequeController::class, 'action'])->whereNumber('cheque')->name('cheques.action');
        Route::delete('cheques/{cheque}', [Admin\ChequeController::class, 'destroy'])->whereNumber('cheque')->name('cheques.destroy');

        // ─── الشركاء في العربيات ───
        Route::get('partners', [Admin\PartnerController::class, 'index'])->name('partners.index');
        Route::post('partners', [Admin\PartnerController::class, 'store'])->name('partners.store');
        Route::get('partners/{partner}', [Admin\PartnerController::class, 'show'])->whereNumber('partner')->name('partners.show');
        Route::put('partners/{partner}', [Admin\PartnerController::class, 'update'])->whereNumber('partner')->name('partners.update');
        Route::delete('partners/{partner}', [Admin\PartnerController::class, 'destroy'])->whereNumber('partner')->name('partners.destroy');
        Route::post('partners/{partner}/transactions', [Admin\PartnerController::class, 'storeTransaction'])->whereNumber('partner')->name('partners.transactions.store');
        Route::delete('partners/{partner}/transactions/{tx}', [Admin\PartnerController::class, 'destroyTransaction'])->whereNumber(['partner', 'tx'])->name('partners.transactions.destroy');
        Route::post('vehicles/{vehicle}/partners', [Admin\PartnerController::class, 'attach'])->whereNumber('vehicle')->name('vehicles.partners.store');
        Route::delete('vehicles/{vehicle}/partners/{partner}', [Admin\PartnerController::class, 'detach'])->whereNumber(['vehicle', 'partner'])->name('vehicles.partners.destroy');

        // ─── المصاريف المشتركة ───
        Route::get('shared-expenses', [Admin\SharedExpenseController::class, 'index'])->name('shared-expenses.index');
        Route::get('shared-expenses/create', [Admin\SharedExpenseController::class, 'create'])->name('shared-expenses.create');
        Route::post('shared-expenses', [Admin\SharedExpenseController::class, 'store'])->name('shared-expenses.store');
        Route::get('shared-expenses/{sharedExpense}', [Admin\SharedExpenseController::class, 'show'])->whereNumber('sharedExpense')->name('shared-expenses.show');
        Route::delete('shared-expenses/{sharedExpense}', [Admin\SharedExpenseController::class, 'destroy'])->whereNumber('sharedExpense')->name('shared-expenses.destroy');

        // ─── الفحوصات ───
        Route::get('inspections', [Admin\InspectionController::class, 'index'])->name('inspections.index');
        Route::get('vehicles/{vehicle}/inspections/create', [Admin\InspectionController::class, 'create'])->whereNumber('vehicle')->name('inspections.create');
        Route::post('vehicles/{vehicle}/inspections', [Admin\InspectionController::class, 'store'])->whereNumber('vehicle')->name('inspections.store');
        Route::get('inspections/{inspection}', [Admin\InspectionController::class, 'show'])->whereNumber('inspection')->name('inspections.show');
        Route::delete('inspections/{inspection}', [Admin\InspectionController::class, 'destroy'])->whereNumber('inspection')->name('inspections.destroy');

        // ─── عمولات المندوبين ───
        Route::get('reports/commissions', [Admin\CommissionController::class, 'index'])->name('reports.commissions');

        Route::group([], function () {
            Route::delete('dealer-contracts/{dealerContract}', [Admin\DealerContractController::class, 'destroy'])->name('dealer-contracts.destroy');
            Route::post('dealer-contracts/{dealerContract}/payments', [Admin\DealerContractController::class, 'storePayment'])->name('dealer-contracts.payments.store');
            Route::get('dealer-payments', [Admin\DealerPaymentController::class, 'index'])->name('dealer-payments.index');
        });

        // ─── عقود البيع ───
        Route::get('sales-contracts', [Admin\SalesContractController::class, 'index'])->name('sales-contracts.index');
        Route::get('sales-contracts/create', [Admin\SalesContractController::class, 'create'])->name('sales-contracts.create');
        Route::post('sales-contracts', [Admin\SalesContractController::class, 'store'])->name('sales-contracts.store');
        Route::post('sales-contracts/quick-customer', [Admin\SalesContractController::class, 'quickCustomer'])->name('sales-contracts.quick-customer');
        Route::get('sales-contracts/{salesContract}', [Admin\SalesContractController::class, 'show'])->name('sales-contracts.show');
        Route::get('sales-contracts/{salesContract}/edit', [Admin\SalesContractController::class, 'edit'])->name('sales-contracts.edit');
        Route::put('sales-contracts/{salesContract}', [Admin\SalesContractController::class, 'update'])->name('sales-contracts.update');
        Route::get('sales-contracts/{salesContract}/print', [Admin\SalesContractController::class, 'print'])->name('sales-contracts.print');
        Route::post('sales-contracts/{salesContract}/payments', [Admin\SalesContractController::class, 'storePayment'])->name('sales-contracts.payments.store');
        Route::post('sales-contracts/{salesContract}/sign/{party}', [Admin\SalesContractController::class, 'sign'])
            ->whereIn('party', ['customer', 'seller'])->name('sales-contracts.sign');
        Route::patch('sales-contracts/{salesContract}/transfer', [Admin\SalesContractController::class, 'transfer'])->name('sales-contracts.transfer');
        Route::post('sales-contracts/{salesContract}/deliver', [Admin\SalesContractController::class, 'deliver'])->name('sales-contracts.deliver');
        Route::post('sales-contracts/{salesContract}/cancel', [Admin\SalesContractController::class, 'requestCancel'])->name('sales-contracts.cancel');
        Route::get('sale-payments', [Admin\SalePaymentController::class, 'index'])->name('sale-payments.index');
        Route::get('sale-payments/{payment}/receipt', [Admin\SalePaymentController::class, 'receipt'])->name('sale-payments.receipt');
        Route::get('installments', [Admin\InstallmentController::class, 'index'])->name('installments.index');

        Route::group([], function () {
            Route::delete('sales-contracts/{salesContract}', [Admin\SalesContractController::class, 'destroy'])->name('sales-contracts.destroy');
            Route::patch('sales-contracts/{salesContract}/cancellations/{cancellation}/{decision}', [Admin\SalesContractController::class, 'decideCancel'])
                ->whereNumber('cancellation')->whereIn('decision', ['approve', 'reject'])->name('sales-contracts.cancellations.decide');
            Route::put('sale-payments/{payment}', [Admin\SalePaymentController::class, 'update'])->name('sale-payments.update');
            Route::delete('sale-payments/{payment}', [Admin\SalePaymentController::class, 'destroy'])->name('sale-payments.destroy');
        });

        // ─── التسليم والإلغاء والتمويل ───
        Route::get('deliveries', [Admin\DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/{delivery}/print', [Admin\DeliveryController::class, 'print'])->whereNumber('delivery')->name('deliveries.print');
        Route::get('cancellations', [Admin\CancellationController::class, 'index'])->name('cancellations.index');
        Route::get('finance-requests', [Admin\FinanceRequestController::class, 'index'])->name('finance-requests.index');
        Route::post('sales-contracts/{salesContract}/finance', [Admin\FinanceRequestController::class, 'store'])->name('finance-requests.store');
        Route::get('finance-companies', [Admin\FinanceCompanyController::class, 'index'])->name('finance-companies.index');

        Route::group([], function () {
            Route::delete('deliveries/{delivery}', [Admin\DeliveryController::class, 'destroy'])->whereNumber('delivery')->name('deliveries.destroy');
            Route::patch('finance-requests/{financeRequest}/{decision}', [Admin\FinanceRequestController::class, 'decide'])
                ->whereNumber('financeRequest')->whereIn('decision', ['approve', 'reject', 'cancel'])->name('finance-requests.decide');
            Route::post('finance-requests/{financeRequest}/receive', [Admin\FinanceRequestController::class, 'receive'])->whereNumber('financeRequest')->name('finance-requests.receive');
            Route::post('finance-companies', [Admin\FinanceCompanyController::class, 'store'])->name('finance-companies.store');
            Route::put('finance-companies/{company}', [Admin\FinanceCompanyController::class, 'update'])->whereNumber('company')->name('finance-companies.update');
            Route::delete('finance-companies/{company}', [Admin\FinanceCompanyController::class, 'destroy'])->whereNumber('company')->name('finance-companies.destroy');

            Route::get('payment-methods', [Admin\PaymentMethodController::class, 'index'])->name('payment-methods.index');
            Route::post('payment-methods', [Admin\PaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::put('payment-methods/{paymentMethod}', [Admin\PaymentMethodController::class, 'update'])->name('payment-methods.update');
            Route::delete('payment-methods/{paymentMethod}', [Admin\PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
        });

        // ─── الحسابات: الخزائن، المصروفات، الإيرادات، الأصول، الإعدادات (للأدمن) ───
        Route::group([], function () {
            Route::get('accounts', [Admin\FinancialAccountController::class, 'index'])->name('accounts.index');
            Route::post('accounts', [Admin\FinancialAccountController::class, 'store'])->name('accounts.store');
            Route::get('accounts/{account}', [Admin\FinancialAccountController::class, 'show'])->whereNumber('account')->name('accounts.show');
            Route::put('accounts/{account}', [Admin\FinancialAccountController::class, 'update'])->whereNumber('account')->name('accounts.update');
            Route::delete('accounts/{account}', [Admin\FinancialAccountController::class, 'destroy'])->whereNumber('account')->name('accounts.destroy');
            Route::post('accounts/transfers', [Admin\FinancialAccountController::class, 'transfer'])->name('accounts.transfers.store');
            Route::delete('accounts/transfers/{transfer}', [Admin\FinancialAccountController::class, 'destroyTransfer'])->whereNumber('transfer')->name('accounts.transfers.destroy');
            Route::patch('accounts/link-methods', [Admin\FinancialAccountController::class, 'linkMethods'])->name('accounts.link-methods');

            Route::get('finance-categories/{kind}', [Admin\FinanceCategoryController::class, 'index'])->whereIn('kind', ['expense', 'revenue', 'asset'])->name('finance-categories.index');
            Route::post('finance-categories/{kind}', [Admin\FinanceCategoryController::class, 'store'])->whereIn('kind', ['expense', 'revenue', 'asset'])->name('finance-categories.store');
            Route::put('finance-categories/{kind}/{category}', [Admin\FinanceCategoryController::class, 'update'])->whereIn('kind', ['expense', 'revenue', 'asset'])->whereNumber('category')->name('finance-categories.update');
            Route::delete('finance-categories/{kind}/{category}', [Admin\FinanceCategoryController::class, 'destroy'])->whereIn('kind', ['expense', 'revenue', 'asset'])->whereNumber('category')->name('finance-categories.destroy');

            Route::get('expenses', [Admin\CashEntryController::class, 'index'])->defaults('kind', 'expense')->name('expenses.index');
            Route::post('expenses', [Admin\CashEntryController::class, 'store'])->defaults('kind', 'expense')->name('expenses.store');
            Route::get('revenues', [Admin\CashEntryController::class, 'index'])->defaults('kind', 'revenue')->name('revenues.index');
            Route::post('revenues', [Admin\CashEntryController::class, 'store'])->defaults('kind', 'revenue')->name('revenues.store');
            Route::put('cash-entries/{entry}', [Admin\CashEntryController::class, 'update'])->whereNumber('entry')->name('cash-entries.update');
            Route::delete('cash-entries/{entry}', [Admin\CashEntryController::class, 'destroy'])->whereNumber('entry')->name('cash-entries.destroy');
            Route::get('cash-entries/{entry}/receipt', [Admin\CashEntryController::class, 'receipt'])->whereNumber('entry')->name('cash-entries.receipt');

            Route::get('assets', [Admin\AssetController::class, 'index'])->name('assets.index');
            Route::post('assets', [Admin\AssetController::class, 'store'])->name('assets.store');
            Route::put('assets/{asset}', [Admin\AssetController::class, 'update'])->whereNumber('asset')->name('assets.update');
            Route::patch('assets/{asset}/dispose', [Admin\AssetController::class, 'dispose'])->whereNumber('asset')->name('assets.dispose');
            Route::delete('assets/{asset}', [Admin\AssetController::class, 'destroy'])->whereNumber('asset')->name('assets.destroy');
            Route::post('assets/{asset}/payoff', [Admin\AssetController::class, 'payoff'])->whereNumber('asset')->name('assets.payoff');
            Route::get('asset-installments', [Admin\AssetController::class, 'installments'])->name('asset-installments.index');
            Route::patch('asset-installments/{installment}/pay', [Admin\AssetController::class, 'payInstallment'])->whereNumber('installment')->name('asset-installments.pay');
            Route::patch('asset-installments/{installment}/unpay', [Admin\AssetController::class, 'unpayInstallment'])->whereNumber('installment')->name('asset-installments.unpay');

            Route::prefix('reports')->name('reports.')->group(function () {
                Route::get('/', [Admin\ReportController::class, 'overview'])->name('overview');
                Route::get('branches', [Admin\ReportController::class, 'branches'])->name('branches');
                Route::patch('branches/targets', [Admin\ReportController::class, 'saveTargets'])->name('targets');
                Route::get('sales', [Admin\ReportController::class, 'sales'])->name('sales');
                Route::get('profits', [Admin\ReportController::class, 'profits'])->name('profits');
                Route::get('inventory', [Admin\ReportController::class, 'inventory'])->name('inventory');
                Route::get('receivables', [Admin\ReportController::class, 'receivables'])->name('receivables');
                Route::get('pnl', [Admin\ReportController::class, 'pnl'])->name('pnl');
            });

            // ─── الموارد البشرية ───
            Route::prefix('hr')->name('hr.')->group(function () {
                Route::get('employees', [Admin\Hr\EmployeeController::class, 'index'])->name('employees.index');
                Route::get('employees/create', [Admin\Hr\EmployeeController::class, 'create'])->name('employees.create');
                Route::post('employees', [Admin\Hr\EmployeeController::class, 'store'])->name('employees.store');
                Route::get('employees/{employee}', [Admin\Hr\EmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');
                Route::get('employees/{employee}/edit', [Admin\Hr\EmployeeController::class, 'edit'])->whereNumber('employee')->name('employees.edit');
                Route::put('employees/{employee}', [Admin\Hr\EmployeeController::class, 'update'])->whereNumber('employee')->name('employees.update');
                Route::delete('employees/{employee}', [Admin\Hr\EmployeeController::class, 'destroy'])->whereNumber('employee')->name('employees.destroy');
                Route::post('employees/{employee}/documents', [Admin\Hr\EmployeeController::class, 'storeDocument'])->whereNumber('employee')->name('employees.documents.store');
                Route::delete('documents/{document}', [Admin\Hr\EmployeeController::class, 'destroyDocument'])->whereNumber('document')->name('documents.destroy');
                Route::post('employees/{employee}/end-service', [Admin\Hr\EmployeeController::class, 'endService'])->whereNumber('employee')->name('employees.end-service');
                Route::get('employees/{employee}/end-service/print', [Admin\Hr\EmployeeController::class, 'eosPrint'])->whereNumber('employee')->name('employees.eos-print');

                Route::get('attendance', [Admin\Hr\AttendanceController::class, 'index'])->name('attendance.index');
                Route::post('attendance', [Admin\Hr\AttendanceController::class, 'store'])->name('attendance.store');
                Route::get('attendance/monthly', [Admin\Hr\AttendanceController::class, 'monthly'])->name('attendance.monthly');

                Route::get('leaves', [Admin\Hr\LeaveController::class, 'index'])->name('leaves.index');
                Route::post('leaves', [Admin\Hr\LeaveController::class, 'store'])->name('leaves.store');
                Route::patch('leaves/{leave}/{decision}', [Admin\Hr\LeaveController::class, 'decide'])->whereNumber('leave')->whereIn('decision', ['approved', 'rejected'])->name('leaves.decide');
                Route::delete('leaves/{leave}', [Admin\Hr\LeaveController::class, 'destroy'])->whereNumber('leave')->name('leaves.destroy');

                Route::get('finance', [Admin\Hr\HrFinanceController::class, 'index'])->name('finance.index');
                Route::post('loans', [Admin\Hr\HrFinanceController::class, 'storeLoan'])->name('loans.store');
                Route::patch('loans/{loan}/cancel', [Admin\Hr\HrFinanceController::class, 'cancelLoan'])->whereNumber('loan')->name('loans.cancel');
                Route::post('adjustments', [Admin\Hr\HrFinanceController::class, 'storeAdjustment'])->name('adjustments.store');
                Route::delete('adjustments/{adjustment}', [Admin\Hr\HrFinanceController::class, 'destroyAdjustment'])->whereNumber('adjustment')->name('adjustments.destroy');

                Route::get('payroll', [Admin\Hr\PayrollController::class, 'index'])->name('payroll.index');
                Route::post('payroll', [Admin\Hr\PayrollController::class, 'store'])->name('payroll.store');
                Route::get('payroll/{run}', [Admin\Hr\PayrollController::class, 'show'])->whereNumber('run')->name('payroll.show');
                Route::post('payroll/{run}/generate', [Admin\Hr\PayrollController::class, 'generate'])->whereNumber('run')->name('payroll.generate');
                Route::patch('payroll/{run}/approve', [Admin\Hr\PayrollController::class, 'approve'])->whereNumber('run')->name('payroll.approve');
                Route::patch('payroll/{run}/unapprove', [Admin\Hr\PayrollController::class, 'unapprove'])->whereNumber('run')->name('payroll.unapprove');
                Route::patch('payroll/{run}/pay', [Admin\Hr\PayrollController::class, 'pay'])->whereNumber('run')->name('payroll.pay');
                Route::patch('payroll/{run}/unpay', [Admin\Hr\PayrollController::class, 'unpay'])->whereNumber('run')->name('payroll.unpay');
                Route::delete('payroll/{run}', [Admin\Hr\PayrollController::class, 'destroy'])->whereNumber('run')->name('payroll.destroy');
                Route::get('payroll/{run}/sheet', [Admin\Hr\PayrollController::class, 'sheet'])->whereNumber('run')->name('payroll.sheet');
                Route::get('payroll/{run}/bank-file', [Admin\Hr\PayrollController::class, 'bankFile'])->whereNumber('run')->name('payroll.bank-file');
                Route::put('payslips/{payroll}', [Admin\Hr\PayrollController::class, 'updateRow'])->whereNumber('payroll')->name('payroll.row');
                Route::get('payslips/{payroll}', [Admin\Hr\PayrollController::class, 'payslip'])->whereNumber('payroll')->name('payroll.payslip');

                Route::get('settings/{tab?}', [Admin\Hr\HrSettingsController::class, 'index'])->whereIn('tab', ['departments', 'shifts', 'allowances', 'leaves', 'holidays'])->name('settings.index');
                Route::post('settings/{tab}', [Admin\Hr\HrSettingsController::class, 'store'])->whereIn('tab', ['departments', 'shifts', 'allowances', 'leaves', 'holidays'])->name('settings.store');
                Route::put('settings/{tab}/{id}', [Admin\Hr\HrSettingsController::class, 'update'])->whereNumber('id')->name('settings.update');
                Route::delete('settings/{tab}/{id}', [Admin\Hr\HrSettingsController::class, 'destroy'])->whereNumber('id')->name('settings.destroy');
            });

            // ─── المستخدمين والصلاحيات ───
            Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [Admin\UserController::class, 'create'])->name('users.create');
            Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
            Route::put('users/{user}', [Admin\UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::patch('users/{user}/toggle', [Admin\UserController::class, 'toggle'])->whereNumber('user')->name('users.toggle');
            Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
            Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [Admin\RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [Admin\RoleController::class, 'edit'])->whereNumber('role')->name('roles.edit');
            Route::put('roles/{role}', [Admin\RoleController::class, 'update'])->whereNumber('role')->name('roles.update');
            Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');

            // ─── سجل النشاط والنسخ الاحتياطي ───
            Route::get('audit', [Admin\SystemController::class, 'audit'])->name('audit.index');
            Route::get('backups', [Admin\SystemController::class, 'backups'])->name('backups.index');
            Route::post('backups', [Admin\SystemController::class, 'backupNow'])->middleware('throttle:5,1')->name('backups.store');
            Route::get('backups/google/connect', [Admin\SystemController::class, 'driveConnect'])->name('backups.google.connect');
            Route::get('backups/google/callback', [Admin\SystemController::class, 'driveCallback'])->name('backups.google.callback');
            Route::delete('backups/google', [Admin\SystemController::class, 'driveDisconnect'])->name('backups.google.disconnect');
            Route::get('backups/{file}', [Admin\SystemController::class, 'download'])->name('backups.download');
            Route::delete('backups/{file}', [Admin\SystemController::class, 'deleteBackup'])->name('backups.destroy');

            // ─── استيراد وتصدير Excel ───
            Route::get('data', [Admin\DataController::class, 'index'])->name('data.index');
            Route::get('data/template/{type}', [Admin\DataController::class, 'template'])->where('type', '[a-z]+')->name('data.template');
            Route::post('data/import', [Admin\DataController::class, 'upload'])->middleware('throttle:20,1')->name('data.upload');
            Route::get('data/import/{token}', [Admin\DataController::class, 'map'])->where('token', '[A-Za-z0-9]+')->name('data.map');
            Route::post('data/import/{token}', [Admin\DataController::class, 'run'])->where('token', '[A-Za-z0-9]+')->name('data.run');
            Route::get('data/import/{token}/errors', [Admin\DataController::class, 'errors'])->where('token', '[A-Za-z0-9]+')->name('data.errors');
            Route::get('data/export/{type}', [Admin\DataController::class, 'export'])->where('type', '[a-z]+')->name('data.export');

            // ─── المدونة ───
            Route::get('blog', [Admin\BlogPostController::class, 'index'])->name('blog.index');
            Route::get('blog/create', [Admin\BlogPostController::class, 'create'])->name('blog.create');
            Route::post('blog', [Admin\BlogPostController::class, 'store'])->name('blog.store');
            Route::post('blog/upload', [Admin\BlogPostController::class, 'upload'])->middleware('throttle:30,1')->name('blog.upload');
            Route::get('blog/{post}/edit', [Admin\BlogPostController::class, 'edit'])->whereNumber('post')->name('blog.edit');
            Route::put('blog/{post}', [Admin\BlogPostController::class, 'update'])->whereNumber('post')->name('blog.update');
            Route::delete('blog/{post}', [Admin\BlogPostController::class, 'destroy'])->whereNumber('post')->name('blog.destroy');
            Route::post('blog-categories', [Admin\BlogCategoryController::class, 'store'])->name('blog-categories.store');
            Route::put('blog-categories/{category}', [Admin\BlogCategoryController::class, 'update'])->whereNumber('category')->name('blog-categories.update');
            Route::delete('blog-categories/{category}', [Admin\BlogCategoryController::class, 'destroy'])->whereNumber('category')->name('blog-categories.destroy');

            // ─── الموقع الإلكتروني ───
            Route::get('site-settings', [Admin\SiteSettingsController::class, 'index'])->name('site-settings.index');
            Route::put('site-settings', [Admin\SiteSettingsController::class, 'update'])->name('site-settings.update');
            Route::get('site-blocks/{type?}', [Admin\SiteBlockController::class, 'index'])->whereIn('type', ['banner', 'testimonial', 'faq', 'feature'])->name('site-blocks.index');
            Route::post('site-blocks/{type}', [Admin\SiteBlockController::class, 'store'])->whereIn('type', ['banner', 'testimonial', 'faq', 'feature'])->name('site-blocks.store');
            Route::post('site-blocks/{type}/reorder', [Admin\SiteBlockController::class, 'reorder'])->name('site-blocks.reorder');
            Route::put('site-blocks/{type}/{block}', [Admin\SiteBlockController::class, 'update'])->whereNumber('block')->name('site-blocks.update');
            Route::patch('site-blocks/{type}/{block}/toggle', [Admin\SiteBlockController::class, 'toggle'])->whereNumber('block')->name('site-blocks.toggle');
            Route::delete('site-blocks/{type}/{block}', [Admin\SiteBlockController::class, 'destroy'])->whereNumber('block')->name('site-blocks.destroy');

            Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.index');
            Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        });

        // ─── تجارب القيادة ───
        Route::get('test-drives', [Admin\TestDriveController::class, 'index'])->name('test-drives.index');
        Route::get('test-drives/create', [Admin\TestDriveController::class, 'create'])->name('test-drives.create');
        Route::post('test-drives', [Admin\TestDriveController::class, 'store'])->name('test-drives.store');
        Route::get('test-drives/{testDrive}', [Admin\TestDriveController::class, 'show'])->name('test-drives.show');
        Route::get('test-drives/{testDrive}/print', [Admin\TestDriveController::class, 'print'])->name('test-drives.print');
        Route::patch('test-drives/{testDrive}/complete', [Admin\TestDriveController::class, 'complete'])->name('test-drives.complete');
        Route::patch('test-drives/{testDrive}/cancel', [Admin\TestDriveController::class, 'cancel'])->name('test-drives.cancel');
        Route::post('test-drives/{testDrive}/sign/{party}', [Admin\TestDriveController::class, 'sign'])
            ->whereIn('party', ['customer', 'showroom'])->name('test-drives.sign');
        Route::delete('test-drives/{testDrive}', [Admin\TestDriveController::class, 'destroy'])->name('test-drives.destroy');

        // ─── الموردين (للأدمن بس - فيها فلوس) ───
        Route::prefix('suppliers')
            ->name('suppliers.')
            ->group(function () {
                Route::get('/', [Admin\SupplierController::class, 'index'])->name('index');
                Route::get('create', [Admin\SupplierController::class, 'create'])->name('create');
                Route::post('/', [Admin\SupplierController::class, 'store'])->name('store');
                Route::get('{supplier}', [Admin\SupplierController::class, 'show'])->name('show');
                Route::get('{supplier}/edit', [Admin\SupplierController::class, 'edit'])->name('edit');
                Route::put('{supplier}', [Admin\SupplierController::class, 'update'])->name('update');
                Route::delete('{supplier}', [Admin\SupplierController::class, 'destroy'])->name('destroy');

                Route::post('{supplier}/invoices', [Admin\SupplierAccountController::class, 'storeInvoice'])
                    ->name('invoices.store');
                Route::post('{supplier}/installments/{installment}/pay', [Admin\SupplierAccountController::class, 'payInstallment'])
                    ->name('installments.pay');
                Route::post('{supplier}/returns', [Admin\SupplierAccountController::class, 'storeReturn'])
                    ->name('returns.store');
                Route::patch('{supplier}/returns/{supplierReturn}', [Admin\SupplierAccountController::class, 'updateReturn'])
                    ->name('returns.update');
            });
    });



/*
|--------------------------------------------------------------------------
| روابط عامة موقّعة (بتتبعت للعميل/المالك واتساب)
|--------------------------------------------------------------------------
| محمية بـ signed: الرابط بيتولّد من السيستم وبينتهي بعد 7 أيام،
| وأي تعديل فيه بيبوّظه.
*/

Route::middleware(['signed', 'throttle:20,1', 'locale:ar'])->group(function () {
    // {party}: owner (المالك — الافتراضي) أو showroom (المعرض/المفوّض)
    Route::get('sign/dealer-contract/{dealerContract}/{party?}', [Admin\DealerContractController::class, 'publicSignForm'])
        ->whereIn('party', ['owner', 'showroom'])->name('public.dealer-contracts.sign');
    Route::post('sign/dealer-contract/{dealerContract}/{party?}', [Admin\DealerContractController::class, 'publicSign'])
        ->whereIn('party', ['owner', 'showroom']);
    // موافقة المالك على تغيير السعر
    Route::get('approve/dealer-price/{priceRequest}', [Admin\DealerContractController::class, 'publicPriceForm'])
        ->whereNumber('priceRequest')->name('public.dealer-price.show');
    Route::post('approve/dealer-price/{priceRequest}', [Admin\DealerContractController::class, 'publicPriceDecide'])
        ->whereNumber('priceRequest');
    Route::get('sign/sales-contract/{salesContract}', [Admin\SalesContractController::class, 'publicSignForm'])
        ->name('public.sales-contracts.sign');
    Route::post('sign/sales-contract/{salesContract}', [Admin\SalesContractController::class, 'publicSign']);
});

/*
|--------------------------------------------------------------------------
| بوابة العميل
|--------------------------------------------------------------------------
| بتتقفل من .env بالسطر:  MODULE_CUSTOMER_PORTAL=false
*/

if (Module::enabled('customer_portal')) {

    Route::view('customer/login', 'customer.auth.login')
        ->middleware('guest')
        ->name('customer.login');

    Route::middleware(['auth', 'verified', 'role:customer'])
        ->prefix('customer')
        ->name('customer.')
        ->group(function () {
            Route::view('/', 'customer.dashboard')->name('dashboard');
        });
}


/*
|--------------------------------------------------------------------------
| التوجيه بعد تسجيل الدخول
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect()->to(auth()->user()->homeRoute()))
        ->name('dashboard');
});

require __DIR__.'/settings.php';
