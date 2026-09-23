<?php

namespace App\Enum;

enum ObservedTimeWindow: string
{
    case Sabah06_12 = '06:00-12:00';
    case Ogle12_18 = '12:00-18:00';
    case Aksam18_24 = '18:00-24:00';
    case Gece00_06 = '00:00-06:00';
}
