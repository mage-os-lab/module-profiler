<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Plugin\App;

use Magento\Framework\App\Response\Http as HttpResponse;
use MageOS\Profiler\Model\Profiler\Driver\Timeline;

/**
 * Names the report of the current request in the response.
 *
 * A client that switched profiling on with the X-Mage-Profiler header or cookie can then open the
 * report it produced, without searching the index for it.
 */
class ReportHeader
{
    public const HEADER = 'X-Mage-Profiler-Report';

    /**
     * @param HttpResponse $subject
     * @return void
     */
    public function beforeSendResponse(HttpResponse $subject): void
    {
        $file = Timeline::getReportFile();
        if ($file !== null) {
            $subject->setHeader(self::HEADER, $file, true);
        }
    }
}
