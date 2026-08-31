<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'MageOS_Profiler',
    __DIR__
);

/**
 * Pre-ObjectManager profiler activation hook.
 *
 * This file is executed from vendor/autoload.php (app/etc/NonComposerComponentRegistration.php),
 * which app/bootstrap.php requires *before* its own Magento\Framework\Profiler::applyConfig() block.
 * That is the only point where a third-party profiler output type can be registered - see bootstrap.php.
 */
//phpcs:ignore Magento2.Security.IncludeFile
require_once __DIR__ . '/bootstrap.php';
