<?php

namespace App\Modules\Ecommerce\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for updating an existing store
 */
class UpdateStoreDto
{
    public function __construct(
        #[Assert\Length(min: 2, max: 255)]
        public ?string $storeName = null,

        #[Assert\Choice(choices: ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'])]
        public ?string $currency = null,

        #[Assert\Timezone]
        public ?string $timezone = null
    ) {}
}
