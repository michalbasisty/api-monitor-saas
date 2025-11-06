<?php

namespace App\EventSubscriber;

use App\Service\MetricsPublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class MetricsSubscriber implements EventSubscriberInterface
{
    private MetricsPublisher $metricsPublisher;
    
    public function __construct(MetricsPublisher $metricsPublisher)
    {
        $this->metricsPublisher = $metricsPublisher;
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }
    
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $startTime = $request->server->get('REQUEST_TIME_FLOAT');
        $endTime = microtime(true);
        
        $metric = [
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'status_code' => $response->getStatusCode(),
            'response_time' => round(($endTime - $startTime) * 1000), // ms
            'timestamp' => time()
        ];
        
        $this->metricsPublisher->publishMetric($metric);
    }
}