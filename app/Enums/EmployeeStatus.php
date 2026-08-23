<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Working = 'working';
    case Break = 'break';
    case OffDuty = 'off_duty';
}