<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
namespace MageOS\Profiler\Model;

/**
 * Interface representing configuration retrieval methods.
 */
interface ConfigInterface
{
    /**
     * Get configuration boolean value
     *
     * @param string $xmlPath
     * @param int $storeId
     * @return bool
     */
    public function getConfigFlag($xmlPath, $storeId = null);

    /**
     * Get configuration value
     *
     * @param string $xmlPath
     * @param int $storeId
     * @return string
     */
    public function getConfigValue($xmlPath, $storeId = null);
}
