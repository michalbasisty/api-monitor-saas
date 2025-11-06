<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private HttpClientInterface $httpClient
    ) {}

    public function sendAlertNotification(Alert $alert, string $message): void
    {
        $user = $this->entityManager->getRepository(User::class)->find($alert->getUserId());
        if (!$user) {
            return;
        }

        $channels = $alert->getNotificationChannels();
        if (!$channels) {
            $channels = ['email']; // Default to email
        }

        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    $this->sendEmailNotification($user, $alert, $message);
                    break;
                case 'slack':
                    $this->sendSlackNotification($user, $alert, $message);
                    break;
                case 'webhook':
                    $this->sendWebhookNotification($user, $alert, $message);
                    break;
            }
        }

        // Update last_triggered_at
        $alert->setLastTriggeredAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function sendEmailNotification(User $user, Alert $alert, string $message): void
    {
        $endpoint = $this->entityManager->getRepository(\App\Entity\Endpoint::class)->find($alert->getEndpointId());
        $endpointUrl = $endpoint ? $endpoint->getUrl() : 'Unknown';

        $email = (new Email())
            ->from('noreply@apimonitor.com')
            ->to($user->getEmail())
            ->subject('API Monitor Alert')
            ->html(sprintf(
                '<p><strong>Alert triggered for endpoint:</strong> %s</p><p>%s</p><p>Alert type: %s</p>',
                $endpointUrl,
                $message,
                $alert->getAlertType()
            ));

        $this->mailer->send($email);
    }

    private function sendSlackNotification(User $user, Alert $alert, string $message): void
    {
        // Assuming user has a slack_webhook_url field, or use a default
        // For simplicity, use a placeholder
        $webhookUrl = getenv('SLACK_WEBHOOK_URL');
        if (!$webhookUrl) {
            return;
        }

        $endpoint = $this->entityManager->getRepository(\App\Entity\Endpoint::class)->find($alert->getEndpointId());
        $endpointUrl = $endpoint ? $endpoint->getUrl() : 'Unknown';

        $payload = [
            'text' => sprintf("Alert: %s\nEndpoint: %s\n%s", $alert->getAlertType(), $endpointUrl, $message)
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload
            ]);
        } catch (\Exception $e) {
            // Log error
        }
    }

    private function sendWebhookNotification(User $user, Alert $alert, string $message): void
    {
        // Assuming user has a webhook_url field
        // For simplicity, placeholder
        $webhookUrl = getenv('WEBHOOK_URL');
        if (!$webhookUrl) {
            return;
        }

        $endpoint = $this->entityManager->getRepository(\App\Entity\Endpoint::class)->find($alert->getEndpointId());

        $payload = [
            'alert_id' => $alert->getId(),
            'alert_type' => $alert->getAlertType(),
            'endpoint_id' => $alert->getEndpointId(),
            'endpoint_url' => $endpoint ? $endpoint->getUrl() : null,
            'message' => $message,
            'triggered_at' => (new \DateTimeImmutable())->format('c')
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload
            ]);
        } catch (\Exception $e) {
            // Log error
        }
    }
}
