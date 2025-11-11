<?php

namespace App\Service\Stripe;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class StripeCustomerManager
{
    public function __construct(
        private StripeClient $stripe,
        private EntityManagerInterface $entityManager
    ) {}

    public function createCustomer(User $user): array
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $user->getEmail(),
                'name' => $user->getEmail(),
                'metadata' => [
                    'user_id' => $user->getId(),
                ],
            ]);

            $user->setStripeCustomerId($customer->id);
            $this->entityManager->flush();

            return [
                'customer_id' => $customer->id,
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getCustomer(string $customerId): array
    {
        try {
            $customer = $this->stripe->customers->retrieve($customerId);
            return [
                'success' => true,
                'customer' => $customer,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function updateCustomer(string $customerId, array $data): array
    {
        try {
            $customer = $this->stripe->customers->update($customerId, $data);
            return [
                'success' => true,
                'customer' => $customer,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
