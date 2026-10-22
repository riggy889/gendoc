<?php

namespace App\Documents\Data;

use App\Documents\Enums\LogoAlign;
use App\Documents\Logo\LogoDataUri;

final readonly class BusinessDetails
{
    public const DEFAULT_LOGO_WIDTH = 120;

    public function __construct(
        public ?string $companyName = null,
        public ?string $logo = null,
        public int $logoWidth = self::DEFAULT_LOGO_WIDTH,
        public LogoAlign $logoAlign = LogoAlign::Left,
        public Address $address = new Address,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?string $taxNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $logo = Text::orNull($data['logo'] ?? null);

        return new self(
            companyName: Text::orNull($data['company_name'] ?? null),
            logo: $logo === null ? null : LogoDataUri::sanitise($logo),
            logoWidth: (int) ($data['logo_width'] ?? self::DEFAULT_LOGO_WIDTH),
            logoAlign: LogoAlign::tryFrom((string) ($data['logo_align'] ?? '')) ?? LogoAlign::Left,
            address: Address::fromArray($data),
            email: Text::orNull($data['email'] ?? null),
            phone: Text::orNull($data['phone'] ?? null),
            website: Text::orNull($data['website'] ?? null),
            taxNumber: Text::orNull($data['tax_number'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'logo' => $this->logo,
            'logo_width' => $this->logoWidth,
            'logo_align' => $this->logoAlign->value,
            ...$this->address->toArray(),
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'tax_number' => $this->taxNumber,
        ];
    }
}
