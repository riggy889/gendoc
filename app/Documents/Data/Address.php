<?php

namespace App\Documents\Data;

final readonly class Address
{
    public function __construct(
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $postcode = null,
        public ?string $country = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line1: Text::orNull($data['address_line_1'] ?? null),
            line2: Text::orNull($data['address_line_2'] ?? null),
            city: Text::orNull($data['city'] ?? null),
            region: Text::orNull($data['region'] ?? null),
            postcode: Text::orNull($data['postcode'] ?? null),
            country: Text::orNull($data['country'] ?? null),
        );
    }

    /**
     * The non-empty lines of the address, in display order.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $cityLine = implode(', ', array_filter([$this->city, $this->region]));

        return array_values(array_filter(
            [$this->line1, $this->line2, $cityLine, $this->postcode, $this->country],
            fn (?string $line): bool => $line !== null && $line !== '',
        ));
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'address_line_1' => $this->line1,
            'address_line_2' => $this->line2,
            'city' => $this->city,
            'region' => $this->region,
            'postcode' => $this->postcode,
            'country' => $this->country,
        ];
    }
}
