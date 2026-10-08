<?php

namespace App\Enums;

enum StockAuditStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
