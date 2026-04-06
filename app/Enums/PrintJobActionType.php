<?php

namespace App\Enums;

enum PrintJobActionType: string
{
    case Print   = 'print';
    case Reprint = 'reprint';
}
