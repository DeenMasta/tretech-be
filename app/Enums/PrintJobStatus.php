<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    case Queued  = 'queued';
    case Printed = 'printed';
    case Failed  = 'failed';
}
