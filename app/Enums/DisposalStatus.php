<?php

namespace App\Enums;

class DisposalStatus
{
    const DRAFT     = 'draft';
    const COMPLETED = 'completed';

    const ALL = [
        self::DRAFT,
        self::COMPLETED,
    ];
}
