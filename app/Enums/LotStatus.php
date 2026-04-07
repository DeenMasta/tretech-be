<?php

namespace App\Enums;

class LotStatus
{
    const AVAILABLE           = 'available';
    const SUPPLIED             = 'supplied';
    const USED                 = 'used';
    const DISPOSED             = 'disposed';
    const HOLDING              = 'holding';
    const RETURNED_TO_SUPPLIER = 'returned_to_supplier';

    const ALL = [
        self::AVAILABLE,
        self::SUPPLIED,
        self::USED,
        self::DISPOSED,
        self::HOLDING,
        self::RETURNED_TO_SUPPLIER,
    ];
}
