<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Test\Unit\Plugin\Checkout;

use Magento\Framework\Profiler;
use Magento\Framework\Profiler\DriverInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping;
use MageOS\Profiler\Model\Instrumentation\Guard;
use MageOS\Profiler\Model\Instrumentation\Settings;
use MageOS\Profiler\Model\Instrumentation\Timer;
use MageOS\Profiler\Model\Instrumentation\TimerId;
use MageOS\Profiler\Plugin\Checkout\ShippingProfiler;
use PHPUnit\Framework\TestCase;

class ShippingProfilerTest extends TestCase
{
    /**
     * @var string[]
     */
    private $startedIds = [];

    protected function setUp(): void
    {
        $this->startedIds = [];
        Profiler::reset();
        putenv('MAGE_PROFILER_CHECKOUT');
    }

    protected function tearDown(): void
    {
        Profiler::reset();
        putenv('MAGE_PROFILER_CHECKOUT');
    }

    /**
     * @return void
     */
    public function testTheWholePassIsTimed(): void
    {
        $this->registerDriver();

        $result = $this->createPlugin()->aroundCollectRates(
            $this->createMock(Shipping::class),
            static function () {
                return 'rates';
            },
            $this->createMock(RateRequest::class)
        );

        $this->assertSame('rates', $result);
        $this->assertSame(['SHIPPING:collectRates'], $this->startedIds);
    }

    /**
     * The carrier code is the row that matters. HTTP: already reports the outbound call, but by host,
     * and a rate aggregator answers for several carriers on one host.
     *
     * @return void
     */
    public function testTheCarrierCodeIsTheDetail(): void
    {
        $this->registerDriver();

        $this->createPlugin()->aroundCollectCarrierRates(
            $this->createMock(Shipping::class),
            static function () {
                return null;
            },
            'ups',
            $this->createMock(RateRequest::class)
        );

        $this->assertSame(['SHIPPING:collectCarrierRates (ups)'], $this->startedIds);
    }

    /**
     * @return void
     */
    public function testOffModeRecordsNothing(): void
    {
        putenv('MAGE_PROFILER_CHECKOUT=0');
        $this->registerDriver();

        $this->createPlugin()->aroundCollectRates(
            $this->createMock(Shipping::class),
            static function () {
                return null;
            },
            $this->createMock(RateRequest::class)
        );

        $this->assertSame([], $this->startedIds);
    }

    /**
     * An unreachable carrier throwing is the case this timer exists for, so it must still close.
     *
     * @return void
     */
    public function testTimerIsClosedWhenACarrierThrows(): void
    {
        $this->registerDriver();

        $plugin  = $this->createPlugin();
        $subject = $this->createMock(Shipping::class);

        try {
            $plugin->aroundCollectCarrierRates($subject, [$this, 'throwBoom'], 'dhl', null);
            $this->fail('Expected the exception to propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $plugin->aroundCollectCarrierRates($subject, static function () {
            return null;
        }, 'ups', null);

        $this->assertSame(
            ['SHIPPING:collectCarrierRates (dhl)', 'SHIPPING:collectCarrierRates (ups)'],
            $this->startedIds
        );
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    public function throwBoom(): void
    {
        throw new \RuntimeException('boom');
    }

    /**
     * @return ShippingProfiler
     */
    private function createPlugin(): ShippingProfiler
    {
        $settings = new Settings();

        return new ShippingProfiler(new Guard($settings), new Timer(), new TimerId($settings));
    }

    /**
     * @return void
     */
    private function registerDriver(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('start')
            ->willReturnCallback(function ($timerId): void {
                $this->startedIds[] = $timerId;
            });

        Profiler::add($driver);
    }
}
