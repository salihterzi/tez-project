<?php

namespace App\Enum;

/**
 * Öğrenci cinsiyeti. Doctrine'da `enumType: self::class` ile eşlenir; veritabanında
 * `K` / `E` değerleriyle bir `string` (varchar) kolon olarak saklanır.
 */
enum Cinsiyet: string
{
    case Kadin = 'K';
    case Erkek = 'E';
}
