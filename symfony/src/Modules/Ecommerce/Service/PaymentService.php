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
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($key, 'aes-256-cbc', $this->getEncryptionKey(), 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decryptApiKey(string $encryptedKey): string
    {
        $data = base64_decode($encryptedKey);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->getEncryptionKey(), 0, $iv);
    }

    private function getEncryptionKey(): string
    {
        $key = getenv('PAYMENT_ENCRYPTION_KEY');
        if (!$key) {
            // Fallback for development - in production this should be set via environment
            $key = 'default-payment-encryption-key-32-chars';
        }
        return substr(hash('sha256', $key), 0, 32);
    }
}
