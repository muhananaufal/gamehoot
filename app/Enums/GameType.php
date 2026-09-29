<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * F13: one engine per game type.
 */
enum GameType: string
{
    case Pentahoot = 'pentahoot';
    case TebakKata = 'tebak_kata';
    case TebakGambar = 'tebak_gambar';
}
