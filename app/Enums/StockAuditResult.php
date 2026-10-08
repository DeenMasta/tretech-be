<?php

namespace App\Enums;

enum StockAuditResult: string
{
    case Pending = 'pending';
    case Tally = 'tally';
    case Missing = 'missing';
    case Short = 'short';
    case Over = 'over';
    case Unexpected = 'unexpected';
    case MovementConflict = 'movement_conflict';
}
