<?php

namespace App\Domain\Maintenance;

enum MaintenanceResult: string
{
    case Operational = 'operational';
    case FollowUpRequired = 'follow_up_required';
    case FaultPersists = 'fault_persists';
}
