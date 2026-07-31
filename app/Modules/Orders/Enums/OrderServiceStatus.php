<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

enum OrderServiceStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case READY_TO_PLAN = 'ready_to_plan';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case INVOICED = 'invoiced';
}
