<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case READY = 'ready';
    case PARTIALLY_PLANNED = 'partially_planned';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case PARTIALLY_INVOICED = 'partially_invoiced';
    case INVOICED = 'invoiced';
}
