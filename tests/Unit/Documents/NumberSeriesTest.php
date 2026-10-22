<?php

use App\Documents\Data\NumberSeries;
use App\Documents\Enums\DocumentType;

it('suggests the next number, zero-padded to 4 digits', function (int $highest, string $expected) {
    expect((new NumberSeries('INV-', $highest))->next())->toBe($expected);
})->with([
    [0, 'INV-0001'],
    [41, 'INV-0042'],
    [9999, 'INV-10000'],
]);

it('starts each type with its default prefix', function (DocumentType $type, string $expected) {
    expect(NumberSeries::default($type)->next())->toBe($expected);
})->with([
    [DocumentType::Quote, 'QUO-0001'],
    [DocumentType::Invoice, 'INV-0001'],
    [DocumentType::CreditNote, 'CN-0001'],
]);

it('only moves forward when a matching number is downloaded', function () {
    $series = new NumberSeries('INV-', 5);

    expect($series->record('INV-0009')->highest)->toBe(9)
        ->and($series->record('INV-0003')->highest)->toBe(5)
        ->and($series->record('2026/Smith/1')->highest)->toBe(5)
        ->and($series->record('QUO-0050')->highest)->toBe(5)
        ->and($series->record('INV-12a')->highest)->toBe(5);
});

it('reads the sequence from numbers in its format', function () {
    $series = new NumberSeries('A/B.');

    expect($series->sequenceOf('A/B.0012'))->toBe(12)
        ->and($series->sequenceOf('AxB.0012'))->toBeNull()
        ->and($series->sequenceOf('A/B.'))->toBeNull()
        ->and((new NumberSeries(''))->sequenceOf('0007'))->toBe(7);
});

it('repairs invalid stored values', function (mixed $data, string $prefix, int $highest) {
    $series = NumberSeries::fromArray(DocumentType::Invoice, $data);

    expect($series->prefix)->toBe($prefix)
        ->and($series->highest)->toBe($highest);
})->with([
    'not an array' => ['junk', 'INV-', 0],
    'missing fields' => [[], 'INV-', 0],
    'negative counter' => [['prefix' => 'X-', 'highest' => -4], 'X-', 0],
    'counter as a string' => [['prefix' => 'X-', 'highest' => '12'], 'X-', 12],
    'overlong prefix' => [['prefix' => str_repeat('P', 30), 'highest' => 1], str_repeat('P', 20), 1],
    'huge counter' => [['prefix' => 'X-', 'highest' => PHP_INT_MAX], 'X-', 0],
]);
