<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** تقارير فحص العربيات: فحص استلام / قبل البيع / عند التسليم — بقايمة أجزاء وتتطبع */
class InspectionController extends Controller
{
    public const TYPES = ['receiving' => 'فحص استلام', 'pre_sale' => 'فحص قبل البيع', 'delivery' => 'فحص عند التسليم', 'periodic' => 'فحص دوري'];

    public const STATES = ['ok' => ['سليم', 'bg-emerald-100 text-emerald-700'], 'attention' => ['محتاج متابعة', 'bg-amber-100 text-amber-800'], 'damaged' => ['فيه عطل', 'bg-rose-100 text-rose-700']];

    public const PARTS = [
        'المحرك', 'ناقل الحركة (الجير)', 'الفرامل', 'نظام التعليق (المساعدين)', 'الدركسون (التوجيه)',
        'الإطارات', 'البطارية', 'التكييف', 'الكهرباء والإضاءة', 'الطبلون والعدادات',
        'الزجاج والمرايات', 'الهيكل الخارجي (صدمات / خدوش)', 'الصبغ', 'الشاصي', 'الكراسي والفرش الداخلي',
        'الزيوت والسوائل', 'العادم (الشكمان)', 'المساحات', 'الإطار الاحتياطي والعدة', 'المفتاح والريموت',
    ];

    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : null;

        return view('admin.inspections.index', [
            'rows' => DB::table('vehicle_inspections')
                ->join('vehicles', 'vehicles.id', '=', 'vehicle_inspections.vehicle_id')
                ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
                ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
                ->whereNull('vehicles.deleted_at')
                ->tap(fn ($q) => \App\Support\Access::scope($q, 'vehicles.branch_id'))
                ->when($type, fn ($q, $v) => $q->where('vehicle_inspections.type', $v))
                ->select('vehicle_inspections.*', 'vehicles.vehicle_code', 'vehicles.chassis_number', 'vehicles.manufacturing_year',
                    \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
                ->latest('vehicle_inspections.inspected_at')->latest('vehicle_inspections.id')
                ->paginate(25)->withQueryString(),
            'type' => $type,
        ]);
    }

    public function create(int $vehicle): View
    {
        return view('admin.inspections.form', [
            'vehicle' => $this->vehicle($vehicle),
            'type'    => array_key_exists(request('type'), self::TYPES) ? request('type') : 'receiving',
        ]);
    }

    public function store(Request $request, int $vehicle): RedirectResponse
    {
        $v = $this->vehicle($vehicle);
        $data = $request->validate([
            'type'           => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'inspected_at'   => ['required', 'date', 'before_or_equal:today'],
            'inspector'      => ['nullable', 'string', 'max:150'],
            'mileage'        => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'items'          => ['required', 'array'],
            'items.*.part'   => ['required', 'string', 'max:100'],
            'items.*.status' => ['required', 'in:ok,attention,damaged'],
            'items.*.note'   => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:3000'],
        ]);

        $items = array_values(array_map(fn ($i) => ['part' => $i['part'], 'status' => $i['status'], 'note' => $i['note'] ?? null], $data['items']));
        $statuses = array_column($items, 'status');
        $result = in_array('damaged', $statuses, true) ? 'damaged' : (in_array('attention', $statuses, true) ? 'attention' : 'ok');

        $id = DB::table('vehicle_inspections')->insertGetId([
            'vehicle_id'   => $v->id,
            'type'         => $data['type'],
            'inspected_at' => $data['inspected_at'],
            'inspector'    => $data['inspector'] ?? null,
            'mileage'      => $data['mileage'] ?? null,
            'items'        => json_encode($items, JSON_UNESCAPED_UNICODE),
            'result'       => $result,
            'notes'        => $data['notes'] ?? null,
            'user_id'      => $request->user()->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // العداد: لو الفحص أحدث وأعلى من المسجل على العربية، نحدّثه
        if (! empty($data['mileage']) && (int) $data['mileage'] > (int) $v->mileage) {
            DB::table('vehicles')->where('id', $v->id)->update(['mileage' => (int) $data['mileage'], 'updated_at' => now()]);
        }

        return redirect()->route('admin.inspections.show', $id)->with('status', 'اتحفظ تقرير الفحص');
    }

    public function show(int $inspection): View
    {
        $i = DB::table('vehicle_inspections')->where('vehicle_inspections.id', $inspection)
            ->leftJoin('users', 'users.id', '=', 'vehicle_inspections.user_id')
            ->select('vehicle_inspections.*', 'users.name as user_name')->first() ?? abort(404);
        $i->items = json_decode($i->items ?? '[]', true) ?: [];

        return view('admin.inspections.show', ['inspection' => $i, 'vehicle' => $this->vehicle($i->vehicle_id), 'company' => \App\Support\Company::info()]);
    }

    public function destroy(int $inspection): RedirectResponse
    {
        $vehicle = DB::table('vehicle_inspections')->where('id', $inspection)->value('vehicle_id') ?? abort(404);
        DB::table('vehicle_inspections')->where('id', $inspection)->delete();

        return redirect()->route('admin.vehicles.show', $vehicle)->with('status', 'اتمسح تقرير الفحص');
    }

    private function vehicle(int $id): object
    {
        $v = DB::table('vehicles')->where('vehicles.id', $id)->whereNull('vehicles.deleted_at')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->select('vehicles.*', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->first() ?? abort(404);
        $ids = \App\Support\Access::branchIds();
        abort_if($ids !== null && ! in_array((int) $v->branch_id, $ids, true), 403);

        return $v;
    }
}
