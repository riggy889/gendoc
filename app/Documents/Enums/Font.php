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

    /**
     * The CSS font-family stack, with a generic fallback.
     */
    public function cssFamily(): string
    {
        $fallback = match ($this) {
            self::SourceSerif4, self::Merriweather, self::PlayfairDisplay => 'Georgia, serif',
            default => 'Arial, sans-serif',
        };

        return "'{$this->label()}', {$fallback}";
    }
}
