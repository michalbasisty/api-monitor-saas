<?php

namespace App\Middleware;

use App\Kernel\ModuleRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ModuleAccessMiddleware implements HttpKernelInterface
{
    private HttpKernelInterface $kernel;
    private ModuleRegistry $moduleRegistry;

    public function __construct(HttpKernelInterface $kernel, ModuleRegistry $moduleRegistry)
    {
        $this->kernel = $kernel;
        $this->moduleRegistry = $moduleRegistry;
    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true): Response
    {
        // Check if request is to a module endpoint
        if ($this->isModuleRoute($request)) {
            $moduleName = $this->extractModuleName($request);
            $user = $request->getUser();

            if (!$user) {
                return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
            }

            $enabledModules = $this->moduleRegistry->getEnabledModules($user);

            if (!isset($enabledModules[$moduleName])) {
                return new Response(
                    json_encode(['error' => "Module {$moduleName} not available"]),
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        return $this->kernel->handle($request, $type, $catch);
    }

    private function isModuleRoute(Request $request): bool
    {
        return str_contains($request->getPathInfo(), '/api/') &&
               !str_contains($request->getPathInfo(), '/api/auth') &&
               !str_contains($request->getPathInfo(), '/api/admin');
    }

    private function extractModuleName(Request $request): string
    {
        $parts = explode('/', trim($request->getPathInfo(), '/'));
        return $parts[1] ?? 'base';
    }
}
