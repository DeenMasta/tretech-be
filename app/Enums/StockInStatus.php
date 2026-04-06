<?php

namespace App\Enums;

class StockInStatus
{
    const DRAFT     = 'draft';
    const FINALIZED = 'finalized';
    const CANCELLED = 'cancelled';

    const ALL = [
        self::DRAFT,
        self::FINALIZED,
        self::CANCELLED,
    ];
}
