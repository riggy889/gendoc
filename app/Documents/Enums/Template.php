<?php

namespace App\Documents\Enums;

enum Template: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Minimal = 'minimal';
    case Bold = 'bold';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
