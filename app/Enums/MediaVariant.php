<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * G11, E8, E14: stored image sizes.
 */
enum MediaVariant: string
{
    case Original = 'original';
    case W1920 = 'w1920';
    case W720 = 'w720';
}
