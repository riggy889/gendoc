<?php

namespace App\Documents\Enums;

enum DateFormat: string
{
    case DayMonthYear = 'd/m/Y';
    case MonthDayYear = 'm/d/Y';
    case Long = 'j M Y';

    /**
     * How a sample date looks in this format, for the date format dropdown.
     */
    public function example(): string
    {
        return (new \DateTimeImmutable('2026-03-31'))->format($this->value);
    }
}
