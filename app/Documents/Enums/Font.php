<?php

namespace App\Documents\Enums;

/**
 * The document fonts, self-hosted from @fontsource packages.
 *
 * resources/css/fonts.css imports exactly the stylesheets listed here, so the editor and the PDF
 * load the same files under the same family names.
 */
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
     * The family name declared by the @fontsource stylesheets. Lato has no variable version, so it
     * comes as separate 400 and 700 weights.
     */
    public function family(): string
    {
        return $this === self::Lato ? 'Lato' : "{$this->label()} Variable";
    }

    public function isSerif(): bool
    {
        return in_array($this, [self::SourceSerif4, self::Merriweather, self::PlayfairDisplay], true);
    }

    /**
     * The CSS font-family stack, with a generic fallback.
     */
    public function cssFamily(): string
    {
        $fallback = $this->isSerif() ? 'Georgia, serif' : 'Arial, sans-serif';

        return "'{$this->family()}', {$fallback}";
    }

    /**
     * The @fontsource stylesheets that declare this font, relative to node_modules.
     *
     * @return list<string>
     */
    public function stylesheets(): array
    {
        return match ($this) {
            self::Lato => ['@fontsource/lato/400.css', '@fontsource/lato/700.css'],
            default => ["@fontsource-variable/{$this->value}/wght.css"],
        };
    }
}
