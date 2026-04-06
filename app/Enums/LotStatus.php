<?php

namespace App\Enums;

class LotStatus
{
    const AVAILABLE = 'available';
    const SUPPLIED  = 'supplied';
    const USED      = 'used';
    const DISPOSED  = 'disposed';
    const HOLDING   = 'holding';

    const ALL = [
        self::AVAILABLE,
        self::SUPPLIED,
        self::USED,
        self::DISPOSED,
        self::HOLDING,
    ];
}
