<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Endpoint;
use App\Entity\MonitoringResult;
use App\Repository\AlertRepository;
use App\Repository\MonitoringResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AlertEvaluationService
{
    public function __construct(
        private AlertRepository $alertRepository,
        private MonitoringResultRepository $resultRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ?NotificationService $notificationService = null
    ) {}

    public function evaluateAlertsForEndpoint(Endpoint $endpoint, MonitoringResult $result): array
    {
        $alerts = $this->alertRepository->findActiveByEndpoint($endpoint);
        $triggeredAlerts = [];

        foreach ($alerts as $alert) {
            if ($this->shouldTrigger($alert, $endpoint, $result)) {
                $triggeredAlerts[] = $alert;
                $this->triggerAlert($alert, $endpoint, $result);
            }
        }

        return $triggeredAlerts;
    }

    public function shouldTrigger(Alert $alert, Endpoint $endpoint, MonitoringResult $result): bool
    {
        return match ($alert->getAlertType()) {
            Alert::TYPE_RESPONSE_TIME => $this->evaluateResponseTime($alert, $result),
            Alert::TYPE_STATUS_CODE => $this->evaluateStatusCode($alert, $result),
            Alert::TYPE_AVAILABILITY => $this->evaluateAvailability($alert, $endpoint),
            default => false,
        };
    }

    private function evaluateResponseTime(Alert $alert, MonitoringResult $result): bool
    {
        $threshold = $alert->getThreshold();
        
        if (!isset($threshold['max_response_time'])) {
            return false;
        }

        $maxTime = (int) $threshold['max_response_time'];
        $responseTime = $result->getResponseTime();

        if ($responseTime === null) {
            return false;
        }

        $shouldTrigger = $responseTime > $maxTime;

        $this->logger->info('Response time alert evaluation', [
            'alert_id' => $alert->getId(),
            'response_time' => $responseTime,
            'threshold' => $maxTime,
            'triggered' => $shouldTrigger
        ]);

        return $shouldTrigger;
    }

    private function evaluateStatusCode(Alert $alert, MonitoringResult $result): bool
    {
        $threshold = $alert->getThreshold();
        $statusCode = $result->getStatusCode();

        if ($statusCode === null) {
            if (isset($threshold['alert_on_null']) && $threshold['alert_on_null'] === true) {
                $this->logger->warning('Status code alert triggered: no response', [
                    'alert_id' => $alert->getId()
                ]);
                return true;
            }
            return false;
        }

        if (isset($threshold['expected_codes'])) {
            $expectedCodes = (array) $threshold['expected_codes'];
            $shouldTrigger = !in_array($statusCode, $expectedCodes, true);

            $this->logger->info('Status code alert evaluation', [
                'alert_id' => $alert->getId(),
                'status_code' => $statusCode,
                'expected_codes' => $expectedCodes,
                'triggered' => $shouldTrigger
            ]);

            return $shouldTrigger;
        }

        if (isset($threshold['min_code']) && isset($threshold['max_code'])) {
            $minCode = (int) $threshold['min_code'];
            $maxCode = (int) $threshold['max_code'];
            $shouldTrigger = $statusCode < $minCode || $statusCode > $maxCode;

            $this->logger->info('Status code range alert evaluation', [
                'alert_id' => $alert->getId(),
                'status_code' => $statusCode,
                'min_code' => $minCode,
                'max_code' => $maxCode,
                'triggered' => $shouldTrigger
            ]);

            return $shouldTrigger;
        }

        return false;
    }

    private function evaluateAvailability(Alert $alert, Endpoint $endpoint): bool
    {
        $threshold = $alert->getThreshold();
        
        if (!isset($threshold['min_uptime_percentage']) || !isset($threshold['period_hours'])) {
            return false;
        }

        $minUptime = (float) $threshold['min_uptime_percentage'];
        $periodHours = (int) $threshold['period_hours'];

        $currentUptime = $this->resultRepository->getUptime($endpoint, $periodHours);

        $shouldTrigger = $currentUptime < $minUptime;

        $this->logger->info('Availability alert evaluation', [
            'alert_id' => $alert->getId(),
            'current_uptime' => $currentUptime,
            'min_uptime' => $minUptime,
            'period_hours' => $periodHours,
            'triggered' => $shouldTrigger
        ]);

        return $shouldTrigger;
    }

    private function triggerAlert(Alert $alert, Endpoint $endpoint, MonitoringResult $result): void
    {
        $alert->setLastTriggeredAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->warning('Alert triggered', [
            'alert_id' => $alert->getId(),
            'alert_type' => $alert->getAlertType(),
            'endpoint_id' => $alert->getEndpointId(),
            'channels' => $alert->getNotificationChannels()
        ]);

        if ($this->notificationService) {
            $this->notificationService->sendAlertNotification($alert, $endpoint, $result);
        }
    }

    public function evaluateAllAlerts(): array
    {
        $alerts = $this->alertRepository->findActiveAlerts();
        $triggeredAlerts = [];

        foreach ($alerts as $alert) {
            if ($alert->getAlertType() === Alert::TYPE_AVAILABILITY) {
                $endpoint = $this->entityManager->find(Endpoint::class, $alert->getEndpointId());
                if ($endpoint) {
                    $latestResult = $this->resultRepository->getLatestResult($endpoint);
                    if ($latestResult && $this->shouldTrigger($alert, $endpoint, $latestResult)) {
                        $triggeredAlerts[] = $alert;
                        $this->triggerAlert($alert, $endpoint, $latestResult);
                    }
                }
            }
        }

        return $triggeredAlerts;
    }
}
