<?php

namespace App\EventSubscriber;

use App\Service\MetricsPublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiMetricsSubscriber implements EventSubscriberInterface
{
    private array $requestTimes = [];

    public function __construct(private readonly MetricsPublisher $metricsPublisher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999], // High priority to run early
            KernelEvents::TERMINATE => ['onKernelTerminate', -9999], // Low priority to run late
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Only collect metrics for API endpoints
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Store the start time for this request
        $this->requestTimes[$request->getRequestUri()] = microtime(true);
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $uri = $request->getRequestUri();

        // Only process API endpoints that we tracked
        if (!isset($this->requestTimes[$uri])) {
            return;
        }

        $startTime = $this->requestTimes[$uri];
        $response = $event->getResponse();

        $this->metricsPublisher->publishMetric($request, $response, $startTime);
        
        // Clean up
        unset($this->requestTimes[$uri]);
    }
}