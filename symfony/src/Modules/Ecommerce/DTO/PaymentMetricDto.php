<?php

namespace App\Modules\Ecommerce\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for payment metrics
 */
class PaymentMetricDto
{
    public function __construct(
        #[Assert\Uuid]
        public string $storeId = '',

        #[Assert\Uuid]
        public string $gatewayId = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        public string $transactionId = '',

        #[Assert\Positive]
        public float $amount = 0,

        #[Assert\Choice(choices: ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'])]
        public string $currency = 'USD',

        #[Assert\Choice(choices: ['authorized', 'declined', 'refunded', 'pending'])]
        public string $status = 'pending',

        #[Assert\PositiveOrZero]
        public int $authorizationTimeMs = 0,

        #[Assert\PositiveOrZero]
        public int $settlementTimeHours = 48
    ) {}
}
