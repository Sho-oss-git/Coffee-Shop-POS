<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $employeeQuery = User::query()
            ->whereIn('role', ['manager', 'cashier']); // employees only — never list admins here

        $employees = (clone $employeeQuery)
            ->with('latestBreakLog')
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('role')->toString(), function ($query, $role) {
                if ($role !== 'all') {
                    $query->where('role', $role);
                }
            })
            ->when($request->string('status')->toString(), function ($query, $status) {
                if ($status !== 'all') {
                    $query->where('status', $status);
                }
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'break_started_at' => $user->latestBreakLog?->break_started_at?->format('h:i A'),
                'break_ended_at' => $user->latestBreakLog?->break_ended_at?->format('h:i A'),
            ]);

        return Inertia::render('users/Index', [
            'users' => $employees,
            'filters' => $request->only(['search', 'role', 'status']),
            'stats' => [
                'total' => (clone $employeeQuery)->count(),
                'working' => (clone $employeeQuery)->where('status', 'working')->count(),
                'on_break' => (clone $employeeQuery)->where('status', 'break')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/Create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('user-management.index')
            ->with('success', 'Employee account created successfully.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/Edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('user-management.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->role === UserRole::Admin) {
            return back()->with('error', 'Admin accounts cannot be deleted from this page.');
        }

        $user->delete();

        return back()->with('success', 'Employee deleted successfully.');
    }

    /**
     * Full activity timeline for one employee — login, break start/end, logout.
     * Powers the "View Records" modal in User Management.
     */
    public function activityLogs(User $user): JsonResponse
    {
        $logs = $user->employeeLogs()
            ->latest('logged_at')
            ->limit(100)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'logged_at' => $log->logged_at->format('M d, Y - h:i A'),
            ]);

        return response()->json([
            'employee' => ['id' => $user->id, 'name' => $user->name],
            'logs' => $logs,
        ]);
    }
}