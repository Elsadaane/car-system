<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        Access::sync(); // أي صلاحية جديدة في الكود بتظهر هنا لوحدها

        return view('admin.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('id')->get(),
            'total' => count(Access::names()),
        ]);
    }

    public function create(Request $request): View
    {
        $copy = $request->integer('copy') ? Role::find($request->integer('copy')) : null;

        return view('admin.roles.form', ['role' => null, 'checked' => $copy ? $copy->permissions->pluck('name')->all() : [], 'copyFrom' => $copy?->name]);
    }

    public function edit(int $role): View
    {
        $r = Role::findOrFail($role);

        return view('admin.roles.form', ['role' => $r, 'checked' => $r->permissions->pluck('name')->all(), 'copyFrom' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')->with('status', 'تم إضافة دور "'.$role->name.'"');
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        $r = Role::findOrFail($role);
        abort_if($r->name === Access::SUPER_ROLE, 403, 'المدير العام عنده كل الصلاحيات دايماً.');
        $data = $this->validated($request, $r);
        $r->update(['name' => $data['name']]);
        $r->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')->with('status', 'تم حفظ صلاحيات "'.$r->name.'"');
    }

    public function destroy(int $role): RedirectResponse
    {
        $r = Role::withCount('users')->findOrFail($role);
        abort_if($r->name === Access::SUPER_ROLE, 403);
        if ($r->users_count) {
            return back()->with('error', 'فيه '.$r->users_count.' موظف على الدور ده — انقلهم لدور تاني الأول.');
        }
        $r->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'تم مسح الدور');
    }

    private function validated(Request $request, ?Role $r): array
    {
        $v = $request->validate([
            'name'          => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($r?->id)],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Access::names())],
        ], ['name.required' => 'اكتب اسم الدور.', 'name.unique' => 'فيه دور بنفس الاسم.']);

        // اللي مش مدير عام ميقدرش يدّي صلاحية هو نفسه معندوش
        $perms = $v['permissions'] ?? [];
        if (! Access::isSuper($request->user())) {
            $perms = array_values(array_filter($perms, fn ($p) => Access::can($p)));
        }
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return ['name' => strip_tags($v['name']), 'permissions' => $perms];
    }
}
