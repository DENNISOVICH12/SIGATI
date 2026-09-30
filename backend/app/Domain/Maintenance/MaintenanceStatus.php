<?php

namespace App\Domain\Maintenance;

enum MaintenanceStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
}
