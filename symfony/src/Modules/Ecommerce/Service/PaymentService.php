<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function addPaymentGateway(Store $store, array $data): PaymentGateway
    {
        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName($data['gateway_name']);
        $gateway->setApiKeyEncrypted($this->encryptApiKey($data['api_key']));
        $gateway->setWebhookUrl($data['webhook_url'] ?? null);
        $gateway->setIsPrimary($data['is_primary'] ?? false);

        $this->em->persist($gateway);
        $this->em->flush();

        return $gateway;
    }

    public function getPaymentGateways(Store $store): array
    {
        return $this->em->getRepository(PaymentGateway::class)
            ->findBy(['store' => $store, 'enabled' => true]);
    }

    public function getPrimaryGateway(Store $store): ?PaymentGateway
    {
        return $this->em->getRepository(PaymentGateway::class)
            ->findOneBy(['store' => $store, 'isPrimary' => true, 'enabled' => true]);
    }

    private function encryptApiKey(string $key): string
    {
        // TODO: Implement proper encryption
        return base64_encode($key);
    }

    public function decryptApiKey(string $encryptedKey): string
    {
        // TODO: Implement proper decryption
        return base64_decode($encryptedKey);
    }
}
