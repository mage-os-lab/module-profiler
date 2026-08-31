<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Plugin\Webapi;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Webapi\Controller\Rest\InputParamsResolver;
use Magento\Webapi\Controller\Rest\RequestProcessorInterface;
use MageOS\Profiler\Model\Instrumentation\Guard;
use MageOS\Profiler\Model\Instrumentation\Settings;
use MageOS\Profiler\Model\Instrumentation\Timer;
use MageOS\Profiler\Model\Instrumentation\TimerId;

/**
 * Times a REST request as "WEBAPI:GET /V1/products/:sku".
 *
 * Declared on RequestProcessorInterface so it covers the synchronous, async, bulk and schema processors
 * alike. Magento instruments none of the webapi stack, so without this a REST request profiles as little
 * more than `cache_frontend_create` and `store.resolve`.
 *
 * The id uses the *matched route template*, never the request path: `/V1/products/24-MB01` as a timer id
 * would produce one table row per SKU and put customer-visible identifiers in a log file.
 * InputParamsResolver::getRoute() memoises, so asking for the route here does not cost a second match.
 */
class RequestProcessorProfiler
{
    private const PREFIX = 'WEBAPI';

    /**
     * Path segments replaced by a placeholder when no route template is available: numeric ids, UUIDs,
     * and SKU-shaped tokens (anything containing a digit).
     */
    private const ID_PATTERN = '/^(\d+|[0-9a-f]{8}-[0-9a-f]{4}(?:-[0-9a-f]{4}){2}-[0-9a-f]{12}|[^\/]*\d[^\/]*)$/i';

    /**
     * Version tokens such as V1 contain a digit but are part of the route, not an identifier.
     */
    private const KEEP_PATTERN = '/^V\d+$/i';

    /**
     * @var Guard
     */
    private $guard;

    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var TimerId
     */
    private $timerId;

    /**
     * @var InputParamsResolver
     */
    private $inputParamsResolver;

    /**
     * @param Guard $guard
     * @param Timer $timer
     * @param TimerId $timerId
     * @param InputParamsResolver $inputParamsResolver
     */
    public function __construct(
        Guard $guard,
        Timer $timer,
        TimerId $timerId,
        InputParamsResolver $inputParamsResolver
    ) {
        $this->guard               = $guard;
        $this->timer               = $timer;
        $this->timerId             = $timerId;
        $this->inputParamsResolver = $inputParamsResolver;
    }

    /**
     * @param RequestProcessorInterface $subject
     * @param callable $proceed
     * @param Request $request
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundProcess(RequestProcessorInterface $subject, callable $proceed, Request $request)
    {
        if (!$this->guard->isActive(Settings::AREA_WEBAPI) || !$this->guard->enter(Settings::AREA_WEBAPI)) {
            return $proceed($request);
        }

        try {
            return $this->timer->measure($this->buildId($request), static function () use ($proceed, $request) {
                return $proceed($request);
            });
        } finally {
            $this->guard->leave(Settings::AREA_WEBAPI);
        }
    }

    /**
     * @param Request $request
     * @return string
     */
    private function buildId(Request $request): string
    {
        $method = (string)$request->getHttpMethod();

        return $this->timerId->build(self::PREFIX, $method . ' ' . $this->resolvePath($request));
    }

    /**
     * The route template when the request matched one, otherwise a normalised path.
     *
     * @param Request $request
     * @return string
     */
    private function resolvePath(Request $request): string
    {
        try {
            $route = $this->inputParamsResolver->getRoute();
            $path  = (string)$route->getRoutePath();
            if ($path !== '') {
                /* Route templates come back without a leading slash; the fallback path has one. */
                return '/' . ltrim($path, '/');
            }
            //phpcs:disable Magento2.CodeAnalysis.EmptyBlock
        } catch (\Throwable $e) {
            /* Unmatched route, schema request, or a processor that does not use the resolver. */
        }
            //phpcs:enable Magento2.CodeAnalysis.EmptyBlock

        return $this->normalizePath((string)$request->getPathInfo());
    }

    /**
     * Replace anything that looks like an identifier so unmatched paths still aggregate.
     *
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        foreach ($segments as $index => $segment) {
            if ($segment === '' || preg_match(self::KEEP_PATTERN, $segment)) {
                continue;
            }
            if (preg_match(self::ID_PATTERN, $segment)) {
                $segments[$index] = ':id';
            }
        }

        return '/' . implode('/', $segments);
    }
}
