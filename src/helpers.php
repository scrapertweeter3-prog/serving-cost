<?php

namespace PoketDev\ServingCost;

/**
 * Procedural helpers mirroring ServingCost's core arithmetic.
 */

function per_gpu_throughput(float $totalTokensPerSecond, int $stations, int $gpusPerStation = 8): float
{
    if ($totalTokensPerSecond <= 0 || $stations < 1 || $gpusPerStation < 1) {
        throw new \InvalidArgumentException('Positive throughput and station counts required.');
    }
    return $totalTokensPerSecond / ($stations * $gpusPerStation);
}

function dollars_per_million_tokens(float $hourlyPricePerGpu, float $tokensPerSecondPerGpu): float
{
    if ($hourlyPricePerGpu <= 0 || $tokensPerSecondPerGpu <= 0) {
        throw new \InvalidArgumentException('Price and throughput must be positive.');
    }
    return $hourlyPricePerGpu * 1_000_000 / ($tokensPerSecondPerGpu * 3600.0);
}

function cold_start_cost_per_minute(float $hourlyPricePerGpu, int $gpusPerStation = 8): float
{
    if ($hourlyPricePerGpu <= 0 || $gpusPerStation < 1) {
        throw new \InvalidArgumentException('Price and station size must be positive.');
    }
    return $hourlyPricePerGpu / 60.0 * $gpusPerStation;
}
