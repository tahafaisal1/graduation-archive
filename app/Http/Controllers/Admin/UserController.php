<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::with(['roles', 'department'])
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->input('role'), fn ($q, $role) => $q->role($role))
            ->when($request->input('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $paginated = $query->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => [
                'data'  => $paginated->items(),
                'links' => $paginated->linkCollection()->toArray(),
                'meta'  => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                ],
            ],
            'roles'       => Role::orderBy('name')->pluck('name'),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters'     => $request->only(['search', 'role', 'department_id', 'is_active']),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'password'        => null,
            'employee_number' => $validated['employee_number'] ?? null,
            'department_id'   => $validated['department_id'] ?? null,
            'is_active'       => false,
        ]);

        $user->assignRole($validated['role']);

        $this->sendInvitation($user, $request->user());

        return redirect()->route('admin.users.index')
            ->with('success', 'تم إنشاء المستخدم وإرسال دعوة إنشاء الحساب إلى بريده الإلكتروني');
    }

    public function resendInvitation(Request $request, User $user)
    {
        if ($user->password !== null) {
            return back()->withErrors(['invitation' => 'هذا الحساب مُفعّل بالفعل ولا يحتاج إلى دعوة']);
        }

        $this->sendInvitation($user, $request->user());

        return back()->with('success', 'تم إرسال دعوة جديدة إلى ' . $user->email);
    }

    protected function sendInvitation(User $user, User $inviter): void
    {
        $plain = \App\Models\StaffInvitation::issueFor($user);
        $url = \App\Models\StaffInvitation::signedUrlFor($user, $plain);

        \Illuminate\Support\Facades\Mail::to($user->email)
            ->send(new \App\Mail\StaffInvitationMail($user, $inviter, $url));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $updateData = [
            'name'                => $validated['name'],
            'email'               => $validated['email'],
            'employee_number'     => $validated['employee_number'] ?? null,
            'department_id'       => $validated['department_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = $validated['password'];
        }

        $user->update($updateData);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', 'تم تحديث بيانات المستخدم بنجاح');
    }

    public function toggleActive(User $user)
    {
        $newActive = !$user->is_active;
        $user->update(['is_active' => $newActive]);

        return redirect()->back()
            ->with('success', $newActive ? 'تم تفعيل المستخدم' : 'تم إيقاف المستخدم');
    }

    public function destroy(User $user)
    {
        if ($user->hasRole('super_admin') && User::role('super_admin')->count() === 1) {
            return redirect()->route('admin.users.index')
                ->with('error', 'لا يمكن حذف المسؤول الأخير في النظام');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'تم حذف المستخدم بنجاح');
    }
}
