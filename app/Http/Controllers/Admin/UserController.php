<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * حسابات الموظفين (مش العملاء): الدور، الفرع، وهل يشوف كل الفروع.
 * الحماية: المؤسس (أول حساب) محدش يعدّله غيره، ومحدش يوقف أو يمسح نفسه.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $role = $request->query('role');

        $users = User::query()->internal()->with(['roles', 'branch'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")))
            ->when($role, fn ($w, $v) => $w->role($v))
            ->when(Access::branchIds(), fn ($w, $ids) => $w->whereIn('branch_id', $ids))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.users.index', [
            'users'   => $users,
            'roles'   => Role::orderBy('id')->withCount('users')->get(),
            'founder' => self::founderId(),
            'counts'  => ['all' => User::internal()->count(), 'active' => User::internal()->where('is_active', true)->count()],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', $this->formData(null));
    }

    public function edit(int $user): View
    {
        $u = User::internal()->findOrFail($user);
        $this->guard($u);

        return view('admin.users.form', $this->formData($u));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $user = User::create($data['attrs'] + ['password' => $data['password']]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('status', 'تم إضافة '.$user->name.' ✅');
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $u = User::internal()->findOrFail($user);
        $this->guard($u);
        $data = $this->validated($request, $u);

        // محدش يقلّل صلاحيات نفسه بالغلط
        if ($u->id === $request->user()->id) {
            unset($data['attrs']['is_active'], $data['attrs']['role']);
            $data['role'] = null;
        }
        $u->fill($data['attrs']);
        if ($data['password']) {
            $u->password = $data['password'];
        }
        $u->save();
        if ($data['role']) {
            $u->syncRoles([$data['role']]);
        }

        return redirect()->route('admin.users.index')->with('status', 'تم حفظ بيانات '.$u->name);
    }

    public function toggle(Request $request, int $user): RedirectResponse
    {
        $u = User::internal()->findOrFail($user);
        $this->guard($u);
        abort_if($u->id === $request->user()->id, 403, 'مينفعش توقف حسابك.');
        $u->forceFill(['is_active' => ! $u->is_active])->save();
        if (! $u->is_active) {
            DB::table('sessions')->where('user_id', $u->id)->delete(); // خروج فوري من كل الأجهزة
        }

        return back()->with('status', $u->is_active ? 'تم تفعيل الحساب' : 'تم إيقاف الحساب وخروجه من كل الأجهزة');
    }

    public function destroy(Request $request, int $user): RedirectResponse
    {
        $u = User::internal()->findOrFail($user);
        $this->guard($u);
        abort_if($u->id === $request->user()->id, 403, 'مينفعش تمسح حسابك.');

        try {
            $u->syncRoles([]);
            $u->delete();
        } catch (\Illuminate\Database\QueryException) {
            // مربوط بعقود وحركات: الأمان إننا نوقفه بدل ما نمسحه
            $u->forceFill(['is_active' => false])->save();

            return back()->with('error', 'الحساب مربوط بعمليات مسجلة، فاتوقف بدل ما يتمسح (عشان السجلات تفضل سليمة).');
        }

        return back()->with('status', 'تم مسح الحساب');
    }

    /** المؤسس = أول حساب مدير اتعمل */
    public static function founderId(): ?int
    {
        return User::query()->where('role', 'admin')->orderBy('id')->value('id');
    }

    private function guard(User $u): void
    {
        $me = auth()->user();
        abort_if($u->id === self::founderId() && $me->id !== $u->id, 403, 'حساب المؤسس محمي — محدش يعدّله غيره.');
        abort_if(Access::isSuper($u) && ! Access::isSuper($me), 403, 'مينفعش تعدّل حساب مدير عام.');
        $ids = Access::branchIds($me);
        abort_if($ids !== null && ! in_array((int) $u->branch_id, $ids, true), 403, 'الموظف ده في فرع تاني.');
    }

    private function formData(?User $u): array
    {
        $roles = Role::orderBy('id')->get();
        if (! Access::isSuper(auth()->user())) {
            $roles = $roles->where('name', '!=', Access::SUPER_ROLE)->values();
        }

        return [
            'user'     => $u,
            'roles'    => $roles,
            'branches' => DB::table('branches')->orderBy('id')->pluckLocal('name', 'id'),
            'current'  => $u?->roles->first()?->name,
            'isSelf'   => $u && $u->id === auth()->id(),
        ];
    }

    private function validated(Request $request, ?User $u): array
    {
        $allowedRoles = Role::pluck('name')->all();
        if (! Access::isSuper($request->user())) {
            $allowedRoles = array_values(array_diff($allowedRoles, [Access::SUPER_ROLE]));
        }

        $v = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'email'        => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($u?->id)],
            'phone'        => ['nullable', 'string', 'max:30'],
            'password'     => [$u ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role'         => [$u && $u->id === $request->user()->id ? 'nullable' : 'required', Rule::in($allowedRoles)],
            'branch_id'    => ['nullable', 'required_unless:all_branches,1', 'exists:branches,id'],
            'all_branches' => ['nullable', 'boolean'],
            'is_active'    => ['nullable', 'boolean'],
        ], [
            'required'  => 'الحقل ده مطلوب.',
            'email.unique' => 'الإيميل ده مستخدم في حساب تاني.',
            'password.confirmed' => 'تأكيد كلمة السر مش مطابق.',
            'password.min' => 'كلمة السر لازم 8 حروف على الأقل.',
            'branch_id.required_unless' => 'اختار فرع الموظف، أو علّم "يشوف كل الفروع".',
            'role.in'   => 'الدور ده مش متاح.',
        ]);

        $isSuper = ($v['role'] ?? null) === Access::SUPER_ROLE;
        // اللي مقفول على فرعه ميقدرش يدّي حد صلاحية كل الفروع
        $allBranches = $isSuper || ($request->boolean('all_branches') && Access::branchIds($request->user()) === null);

        return [
            'attrs' => [
                'name'         => strip_tags($v['name']),
                'email'        => strtolower($v['email']),
                'phone'        => $v['phone'] ?? null,
                'branch_id'    => $v['branch_id'] ?? null,
                'all_branches' => $allBranches,
                'is_active'    => $request->boolean('is_active', true),
            ] + (($v['role'] ?? null) ? ['role' => $isSuper ? 'admin' : 'staff'] : []),
            'password' => $v['password'] ?? null,
            'role'     => $v['role'] ?? null,
        ];
    }
}
