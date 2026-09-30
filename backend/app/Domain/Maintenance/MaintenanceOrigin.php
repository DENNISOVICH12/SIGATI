<?php

namespace App\Domain\Maintenance;

enum MaintenanceOrigin: string
{
    case Manual = 'manual';
    case Ticket = 'ticket';
    case PreventivePlan = 'preventive_plan';
}
