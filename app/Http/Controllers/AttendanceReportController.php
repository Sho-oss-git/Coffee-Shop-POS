<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceReportController extends Controller
{
    public function index(Request $request, AttendanceCalculator $calculator): Response
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date')->toString())->startOfDay()
            : now()->startOfWeek();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->string('end_date')->toString())->startOfDay()
            : now()->endOfWeek()->startOfDay()->min(Carbon::today());

        $employees = User::query()
            ->whereIn('role', ['manager', 'cashier'])
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedEmployeeId = $request->integer('employee_id') ?: $employees->first()?->id;
        $selectedEmployee = $employees->firstWhere('id', $selectedEmployeeId);

        $rows = [];

        if ($selectedEmployee) {
            $selectedEmployee->load('schedules');

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $rows[] = $calculator->calculateForDate($selectedEmployee, $date->copy());
            }
        }

        return Inertia::render('attendance-report/Index', [
            'employees' => $employees,
            'selectedEmployeeId' => $selectedEmployeeId,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'rows' => $rows,
        ]);
    }
}