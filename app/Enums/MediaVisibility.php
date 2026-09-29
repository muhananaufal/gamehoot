<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * E9: answer images stay private until Reveal.
 */
enum MediaVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
