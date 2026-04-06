<?php

namespace App\Enums;

class CaptureMethod
{
    const SCAN   = 'scan';
    const MANUAL = 'manual';

    const ALL = [
        self::SCAN,
        self::MANUAL,
    ];
}
