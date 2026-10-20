<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Plugin\App;

use Magento\Framework\App\State\ReloadProcessorInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Profiler;
use MageOS\Profiler\Model\Activation;
use MageOS\Profiler\Model\Instrumentation\TimerId;

/**
 * Per-request activation in a FrankenPHP worker.
 *
 * The arming at worker boot saw no request, and a report has to close with the response instead of
 * with the process. After the response the worker reloads the application state and resets the
 * ObjectManager; that work is recorded under a `reset_state` root with one `RELOAD:` row per reload
 * processor, and the report is written when this plugin is reset, which the WeakMapSorter order puts
 * last. Outside a worker the hooks do nothing.
 */
class WorkerRequest implements ResetAfterRequestInterface
{
    private const PREFIX = 'RELOAD';

    /**
     * @var TimerId
     */
    private $timerId;

    /**
     * @var bool
     */
    private $resetting = false;

    /**
     * @param TimerId $timerId
     */
    public function __construct(TimerId $timerId)
    {
        $this->timerId = $timerId;
    }

    /**
     * @param AppInterface $subject
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeLaunch(AppInterface $subject): void
    {
        if (!Activation::isWorker() || Profiler::isEnabled()) {
            return;
        }

        Activation::arm();

        /*
         * The worker started the `magento` root timer before the profiler was armed, so that start was
         * ignored; its stop after the response would throw. Started here, the root covers the launch.
         */
        if (Profiler::isEnabled()) {
            Profiler::start('magento');
        }
    }

    /**
     * The outermost call is the composite: it opens the `reset_state` root, which stays open through the
     * ObjectManager reset that follows. Each processor inside it is a row of its own, by full class name:
     * several modules ship a ReloadConfig or a ReloadProcessor.
     *
     * @param ReloadProcessorInterface $subject
     * @param callable $proceed
     * @return void
     */
    public function aroundReloadState(ReloadProcessorInterface $subject, callable $proceed): void
    {
        if (!Activation::isWorker() || !Profiler::isEnabled()) {
            $proceed();
            return;
        }

        if (!$this->resetting) {
            $this->resetting = true;
            Profiler::start('reset_state');
            $proceed();
            return;
        }

        $id = $this->timerId->build(self::PREFIX, $this->timerId->shortClass(get_class($subject), PHP_INT_MAX));
        Profiler::start($id);
        try {
            $proceed();
        } finally {
            Profiler::stop($id);
        }
    }

    /**
     * @return void
     */
    public function _resetState(): void
    {
        if (!Activation::isWorker()) {
            return;
        }

        if ($this->resetting) {
            Profiler::stop('reset_state');
            $this->resetting = false;
        }
        Activation::disarm();
    }
}
