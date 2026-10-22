<?php

namespace App\Documents\Enums;

enum DateFormat: string
{
    case DayMonthYear = 'd/m/Y';
    case MonthDayYear = 'm/d/Y';
    case Long = 'j M Y';
}
