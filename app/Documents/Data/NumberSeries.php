<?php

namespace App\Documents\Data;

use App\Documents\Enums\DocumentType;

/**
 * One document type's numbering: its prefix and the highest number downloaded so far.
 *
 * The browser keeps these in localStorage. The highest number only ever moves forward, and only
 * for numbers in this series' "{prefix}{digits}" format.
 */
final readonly class NumberSeries
{
    public const MAX_PREFIX_LENGTH = 20;

    private const MIN_DIGITS = 4;

    /** Longer runs of digits than this can't be counted safely as integers. */
    private const MAX_DIGITS = 15;

    public function __construct(
        public string $prefix,
        public int $highest = 0,
    ) {}

    public static function default(DocumentType $type): self
    {
        return new self($type->defaultNumberPrefix());
    }

    /**
     * Build from browser data, falling back to the defaults for anything invalid.
     */
    public static function fromArray(DocumentType $type, mixed $data): self
    {
        if (! is_array($data)) {
            return self::default($type);
        }

        $prefix = is_string($data['prefix'] ?? null) ? mb_substr(ltrim($data['prefix']), 0, self::MAX_PREFIX_LENGTH) : $type->defaultNumberPrefix();
        $highest = filter_var($data['highest'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10 ** self::MAX_DIGITS - 2]]);

        return new self($prefix, $highest === false ? 0 : $highest);
    }

    /**
     * The suggested number for the next document, e.g. "INV-0007".
     */
    public function next(): string
    {
        return $this->prefix.str_pad((string) ($this->highest + 1), self::MIN_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The sequence number within this series, or null if the number isn't in "{prefix}{digits}" format.
     */
    public function sequenceOf(string $number): ?int
    {
        $pattern = '/^'.preg_quote($this->prefix, '/').'(\d{1,'.self::MAX_DIGITS.'})$/';

        return preg_match($pattern, $number, $match) ? (int) $match[1] : null;
    }

    /**
     * The series after downloading a document with this number.
     */
    public function record(string $number): self
    {
        $sequence = $this->sequenceOf($number);

        return $sequence === null ? $this : new self($this->prefix, max($this->highest, $sequence));
    }

    public function withPrefix(string $prefix): self
    {
        return new self(mb_substr(ltrim($prefix), 0, self::MAX_PREFIX_LENGTH), $this->highest);
    }

    /**
     * @return array{prefix: string, highest: int}
     */
    public function toArray(): array
    {
        return ['prefix' => $this->prefix, 'highest' => $this->highest];
    }
}
