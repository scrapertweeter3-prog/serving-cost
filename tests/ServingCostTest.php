<?php

namespace PoketDev\ServingCost\Tests;

use PHPUnit\Framework\TestCase;
use PoketDev\ServingCost\ServingCost;
use function PoketDev\ServingCost\per_gpu_throughput;
use function PoketDev\ServingCost\dollars_per_million_tokens;
use function PoketDev\ServingCost\cold_start_cost_per_minute;

final class ServingCostTest extends TestCase
{
    public function test_two_station_demo_normalizes_per_gpu(): void
    {
        // The audited 607.8 tok/s two-station headline: 303.9 per station,
        // and a four-accelerator station lands "roughly 76" per GPU.
        $sc = new ServingCost(2149.0, 4);
        $this->assertSame(75.975, $sc->perGpuThroughput(607.8, 2));
    }

    public function test_per_gpu_figure_divides_by_station_accelerators(): void
    {
        // 303.9/A: four-accelerator station is ~76 tok/s per GPU.
        $this->assertSame(75.975, per_gpu_throughput(303.9, 1, 4));
        $this->assertSame(37.9875, per_gpu_throughput(303.9, 1, 8));
    }

    public function test_dollars_per_million_tokens_at_hourly_rate(): void
    {
        // $2.49/GPU-hour at 76 tok/s per GPU.
        $this->assertEqualsWithDelta(9.1039, dollars_per_million_tokens(2.49, 75.975), 0.0005);
    }

    public function test_dollars_per_million_tokens_from_monthly_price(): void
    {
        // $2149/mo on 730 hours is $2.944/GPU-hour; at 76 tok/s per GPU.
        $sc = new ServingCost(2149.0);
        $this->assertEqualsWithDelta(10.764, $sc->dollarsPerMillionTokens(75.975), 0.01);
    }

    public function test_parity_premium_prices_the_second_station(): void
    {
        $sc = new ServingCost(2149.0);
        // Two stations at parity = one extra station's fleet price.
        $this->assertSame(17192.0, $sc->parityPremium(2));
    }

    public function test_parity_premium_honors_all_reduce_tax(): void
    {
        $sc = new ServingCost(2149.0);
        // A 20% interconnect tax prices only 80% of the second fleet:
        // 16 * 0.8 = 12.8 effective GPUs, 4.8 billable over the baseline.
        $this->assertEqualsWithDelta(10315.2, $sc->parityPremium(2, 0.20), 0.01);
    }

    public function test_parity_premium_zero_for_single_station(): void
    {
        $sc = new ServingCost(2149.0);
        $this->assertSame(0.0, $sc->parityPremium(1));
    }

    public function test_cold_start_budgets_the_first_minute(): void
    {
        // $2.49/GPU-hour over eight accelerators, per minute.
        $this->assertEqualsWithDelta(0.332, cold_start_cost_per_minute(2.49, 8), 0.001);
    }

    public function test_negative_prices_reject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ServingCost(-1.0);
    }

    public function test_zero_throughput_rejects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ServingCost(2149.0))->perGpuThroughput(0.0, 2);
    }

    public function test_all_reduce_tax_bounds_reject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ServingCost(2149.0))->parityPremium(2, 1.0);
    }
}
