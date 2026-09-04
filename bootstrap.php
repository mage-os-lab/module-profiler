<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
/*
 * Included by Composer's `files` autoload, before the ObjectManager exists: the only moment early enough
 * to catch the whole request. The activation itself lives in Model\Activation, so a FrankenPHP worker
 * can run it again for every request.
 */
if (defined('BP')) {
    \MageOS\Profiler\Model\Activation::arm();
}
