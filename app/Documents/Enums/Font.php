<?php

namespace App\Documents\Enums;

enum Font: string
{
    case Inter = 'inter';
    case Roboto = 'roboto';
    case Lato = 'lato';
    case SourceSerif4 = 'source-serif-4';
    case Merriweather = 'merriweather';
    case PlayfairDisplay = 'playfair-display';

    public function label(): string
    {
        return match ($this) {
            self::Inter => 'Inter',
            self::Roboto => 'Roboto',
            self::Lato => 'Lato',
            self::SourceSerif4 => 'Source Serif 4',
            self::Merriweather => 'Merriweather',
            self::PlayfairDisplay => 'Playfair Display',
        };
    }
}
