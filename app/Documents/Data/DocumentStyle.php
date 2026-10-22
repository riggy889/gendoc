<?php

namespace App\Documents\Data;

use App\Documents\Enums\DateFormat;
use App\Documents\Enums\Font;
use App\Documents\Enums\Template;
use App\Documents\Support\AccentColour;

final readonly class DocumentStyle
{
    public const DEFAULT_ACCENT_COLOR = '#2563eb';

    public function __construct(
        public Template $template = Template::Classic,
        public string $accentColor = self::DEFAULT_ACCENT_COLOR,
        public Font $font = Font::Inter,
        public DateFormat $dateFormat = DateFormat::DayMonthYear,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            template: Template::from((string) $data['template']),
            accentColor: strtolower((string) $data['accent_color']),
            font: Font::from((string) $data['font']),
            dateFormat: DateFormat::from((string) $data['date_format']),
        );
    }

    /**
     * The CSS custom properties the document stylesheet reads, as an inline style attribute value.
     */
    public function cssVariables(): string
    {
        $variables = [
            '--accent' => $this->accentColor,
            '--accent-text' => AccentColour::asText($this->accentColor),
            '--accent-ink' => AccentColour::onAccent($this->accentColor),
            '--doc-font' => $this->font->cssFamily(),
        ];

        return implode(' ', array_map(fn (string $name, string $value) => "{$name}: {$value};", array_keys($variables), $variables));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'template' => $this->template->value,
            'accent_color' => $this->accentColor,
            'font' => $this->font->value,
            'date_format' => $this->dateFormat->value,
        ];
    }
}
