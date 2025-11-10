<?php

namespace App\Modules\Ecommerce\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating a new store
 */
class CreateStoreDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        public string $storeName = '',

        #[Assert\NotBlank]
        #[Assert\Url]
        public string $storeUrl = '',

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['shopify', 'woocommerce', 'magento', 'custom'])]
        public string $platform = '',

        #[Assert\Choice(choices: ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'])]
        public string $currency = 'USD',

        #[Assert\Timezone]
        public ?string $timezone = null
    ) {}
}
