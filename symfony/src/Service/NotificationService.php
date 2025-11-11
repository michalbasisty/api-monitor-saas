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
        $webhookUrl = $user->getSlackWebhookUrl();
        if (!$webhookUrl) {
            // If user hasn't configured Slack, try global config
            $webhookUrl = (string) getenv('SLACK_WEBHOOK_URL');
            if (!$webhookUrl) {
                // User has not configured Slack - skip silently for now, consider logging
                return;
            }
        }

        $endpoint = $this->entityManager->getRepository(\App\Entity\Endpoint::class)->find($alert->getEndpointId());
        $endpointUrl = $endpoint ? $endpoint->getUrl() : 'Unknown';

        $payload = [
            'text' => sprintf("Alert: %s\nEndpoint: %s\n%s", $alert->getAlertType(), $endpointUrl, $message),
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'API Monitor Alert'
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf("*Alert Type:*\n%s", $alert->getAlertType())
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf("*Endpoint:*\n%s", $endpointUrl)
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $message
                    ]
                ]
            ]
        ];

        try {
            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
                'timeout' => 5
            ]);
            
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                // Log warning but don't throw
                \error_log(sprintf("Slack notification returned status %d for alert %s", $response->getStatusCode(), $alert->getId()));
            }
        } catch (\Exception $e) {
            \error_log(sprintf("Failed to send Slack notification: %s", $e->getMessage()));
        }
    }

    private function sendWebhookNotification(User $user, Alert $alert, string $message): void
    {
        $webhookUrl = $user->getWebhookUrl();
        if (!$webhookUrl) {
            // If user hasn't configured webhook, try global config
            $webhookUrl = (string) getenv('WEBHOOK_URL');
            if (!$webhookUrl) {
                // User has not configured webhook - skip
                return;
            }
        }

        // Validate webhook URL format
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            \error_log(sprintf("Invalid webhook URL for user %s: %s", $user->getId(), $webhookUrl));
            return;
        }

        $endpoint = $this->entityManager->getRepository(\App\Entity\Endpoint::class)->find($alert->getEndpointId());

        $payload = [
            'alert_id' => $alert->getId(),
            'alert_type' => $alert->getAlertType(),
            'endpoint_id' => $alert->getEndpointId(),
            'endpoint_url' => $endpoint ? $endpoint->getUrl() : null,
            'message' => $message,
            'triggered_at' => (new \DateTimeImmutable())->format('c'),
            'user_id' => $user->getId()
        ];

        try {
            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
                'timeout' => 5,
                'headers' => [
                    'User-Agent' => 'API-Monitor-Webhook/1.0',
                    'X-Webhook-Signature' => $this->generateWebhookSignature($payload, $user->getId())
                ]
            ]);
            
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                \error_log(sprintf("Webhook notification returned status %d for alert %s", $response->getStatusCode(), $alert->getId()));
            }
        } catch (\Exception $e) {
            \error_log(sprintf("Failed to send webhook notification: %s", $e->getMessage()));
        }
    }

    private function generateWebhookSignature(array $payload, string $userId): string
    {
        $secret = (string) getenv('WEBHOOK_SECRET');
        if (!$secret) {
            return '';
        }
        $data = json_encode($payload) . $userId . $secret;
        return hash('sha256', $data);
    }
}
