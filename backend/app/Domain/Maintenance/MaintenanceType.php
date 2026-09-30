<?php

namespace App\Domain\Maintenance;

enum MaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Additional = 'additional';
}
