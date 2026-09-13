<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Model\Cache;

/*
 * Parent of ProfiledRedis: \Redis when ext-redis is loaded, an empty class otherwise.
 *
 * A class that extends \Redis directly cannot be loaded without the extension, and setup:di:compile
 * loads every class of a module, so the module would not compile on a host without phpredis.
 * ProfiledRedisInstaller checks class_exists('Redis') before it constructs a ProfiledRedis, so the
 * stand-in is never used at runtime.
 */
if (class_exists('Redis', false)) {
    class ProfiledRedisBase extends \Redis
    {
    }
} else {
    class ProfiledRedisBase
    {
    }
}
