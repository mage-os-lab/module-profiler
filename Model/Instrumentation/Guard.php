<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Model\Instrumentation;

use Magento\Framework\Profiler;
use MageOS\Profiler\Model\Instrumentation\Settings as InstrumentationSettings;

/**
 * Gate + re-entrancy guard shared by every instrumentation plugin.
 *
 * Re-entrancy matters where a hook can nest inside itself: a cache read triggered while serving a cache
 * read, an HTTP call made from inside an HTTP client. Timing the inner call would double-count it and,
 * worse, unbalance the timer stack if the inner call throws.
 */
class Guard
{
    /**
     * @var InstrumentationSettings
     */
    private $settings;

    /**
     * @var array<string, bool>
     */
    private $inside = [];

    /**
     * @param InstrumentationSettings $settings
     */
    public function __construct(InstrumentationSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Whether this area should record right now.
     *
     * @param string $area One of Settings::AREA_*
     * @return bool
     */
    public function isActive(string $area): bool
    {
        return Profiler::isEnabled() && $this->settings->isAreaEnabled($area);
    }

    /**
     * Whether the profiler is recording at all, with no area gate.
     *
     * For instrumentation that is part of the module's baseline rather than an opt-out area: a single
     * timer per request, cheap enough that a switch to turn it off would be a worse trade than the
     * cost of leaving it on.
     *
     * @return bool
     */
    public function isProfiling(): bool
    {
        return Profiler::isEnabled();
    }

    /**
     * Claim the area. Returns false when already inside it - the caller must then skip timing.
     *
     * @param string $area
     * @return bool
     */
    public function enter(string $area): bool
    {
        if (!empty($this->inside[$area])) {
            return false;
        }

        $this->inside[$area] = true;

        return true;
    }

    /**
     * Release the area. Safe to call unconditionally from a finally block.
     *
     * @param string $area
     * @return void
     */
    public function leave(string $area): void
    {
        unset($this->inside[$area]);
    }
}
