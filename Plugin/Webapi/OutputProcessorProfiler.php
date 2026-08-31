<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Plugin\Webapi;

use Magento\Framework\Webapi\ServiceOutputProcessor;
use MageOS\Profiler\Model\Instrumentation\Guard;
use MageOS\Profiler\Model\Instrumentation\Settings;
use MageOS\Profiler\Model\Instrumentation\Timer;
use MageOS\Profiler\Model\Instrumentation\TimerId;

/**
 * Times REST output serialization as "WEBAPI:output (ProductRepositoryInterface::getList)".
 *
 * Consistently one of the most expensive parts of a REST response and one of the least obvious: the
 * processor reflects over every returned DTO to convert it to an array. On list endpoints it frequently
 * costs more than the query that produced the data.
 */
class OutputProcessorProfiler
{
    private const PREFIX = 'WEBAPI';

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
     * @param Guard $guard
     * @param Timer $timer
     * @param TimerId $timerId
     */
    public function __construct(Guard $guard, Timer $timer, TimerId $timerId)
    {
        $this->guard   = $guard;
        $this->timer   = $timer;
        $this->timerId = $timerId;
    }

    /**
     * @param ServiceOutputProcessor $subject
     * @param callable $proceed
     * @param mixed $data
     * @param string $serviceClassName
     * @param string $serviceMethodName
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundProcess(
        ServiceOutputProcessor $subject,
        callable $proceed,
        $data,
        $serviceClassName,
        $serviceMethodName
    ) {
        if (!$this->guard->isActive(Settings::AREA_WEBAPI)) {
            return $proceed($data, $serviceClassName, $serviceMethodName);
        }

        $detail = $this->timerId->shortClass((string)$serviceClassName, 1) . '::' . $serviceMethodName;

        return $this->timer->measure(
            $this->timerId->build(self::PREFIX, 'output', $detail),
            static function () use ($proceed, $data, $serviceClassName, $serviceMethodName) {
                return $proceed($data, $serviceClassName, $serviceMethodName);
            }
        );
    }
}
