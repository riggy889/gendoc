<?php

namespace App\Documents\Data;

final readonly class CustomerDetails
{
    public function __construct(
        public ?string $companyName = null,
        public ?string $contactName = null,
        public Address $address = new Address,
        public ?string $email = null,
        public ?string $taxNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            companyName: Text::orNull($data['company_name'] ?? null),
            contactName: Text::orNull($data['contact_name'] ?? null),
            address: Address::fromArray($data),
            email: Text::orNull($data['email'] ?? null),
            taxNumber: Text::orNull($data['tax_number'] ?? null),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'contact_name' => $this->contactName,
            ...$this->address->toArray(),
            'email' => $this->email,
            'tax_number' => $this->taxNumber,
        ];
    }
}
