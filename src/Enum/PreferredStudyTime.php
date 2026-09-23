<?php

namespace App\Enum;

enum PreferredStudyTime: string
{
    case Sabah = 'Sabah';
    case OgledenSonra = 'Öğleden sonra';
    case Aksam = 'Akşam';
    case Gece = 'Gece';
    case Degisken = 'Değişken';
}
