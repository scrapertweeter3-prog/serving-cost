<?php

namespace PoketDev\ServingCost;

/**
 * Serving cost math for self-hosted LLM inference.
 *
 * A throughput headline is not a cost number until it is divided by the
 * hardware that produced it and multiplied by the price of that hardware.
 * This class carries the small, repeatable arithmetic: per-GPU throughput
 * from a multi-station demo, dollars per million tokens at a given GPU
 * rate, the parity premium of running one model across two machines, and
 * the budget line a slow cold start quietly adds.
 */
final class ServingCost
{
    public function __construct(
        private readonly float $monthlyPricePerGpu,
        private readonly int $gpusPerStation = 8
    ) {
        if ($monthlyPricePerGpu < 0) {
            throw new \InvalidArgumentException('GPU price cannot be negative.');
        }
        if ($gpusPerStation < 1) {
            throw new \InvalidArgumentException('A station needs at least one accelerator.');
        }
    }

    /**
     * Normalize a whole-deployment throughput to one accelerator.
     *
     * A two-station demo headline (607.8 tok/s in the audit that motivated
     * this helper) is 303.9 tok/s per station, and 303.9 divided by the
     * accelerators one station contributes. Only the per-GPU number is
     * comparable across deployments.
     */
    public function perGpuThroughput(float $totalTokensPerSecond, int $stations = 1): float
    {
        if ($totalTokensPerSecond <= 0) {
            throw new \InvalidArgumentException('Throughput must be positive.');
        }
        if ($stations < 1) {
            throw new \InvalidArgumentException('At least one station.');
        }
        return $totalTokensPerSecond / ($stations * $this->gpusPerStation);
    }

    /**
     * Dollars per million tokens at a given per-GPU rate.
     *
     * $2.49/GPU-hour at 76 tok/s per GPU prices a million tokens at
     * 2.49 * 1e6 / (76 * 3600) = $9.10.
     */
    public function dollarsPerMillionTokens(float $tokensPerSecondPerGpu, ?float $hourlyPricePerGpu = null): float
    {
        if ($tokensPerSecondPerGpu <= 0) {
            throw new \InvalidArgumentException('Per-GPU throughput must be positive.');
        }
        $price = $hourlyPricePerGpu ?? $this->monthlyPricePerGpu / 730.0;
        if ($price <= 0) {
            throw new \InvalidArgumentException('GPU price must be positive.');
        }
        return $price * 1_000_000 / ($tokensPerSecondPerGpu * 3600.0);
    }

    /**
     * The parity premium: what one model split across two machines costs
     * over the single-machine baseline for the same per-GPU throughput.
     *
     * Tensor parallelism moves every layer's activations sideways across
     * the wire twice per layer (two all-reduce collectives per forward
     * pass), so the matched-throughput second station is pure redundancy
     * plus interconnect: stations/1 minus one, priced on the fleet.
     */
    public function parityPremium(int $stations, float $allReduceThroughputTax = 0.0): float
    {
        if ($stations < 1) {
            throw new \InvalidArgumentException('At least one station.');
        }
        if ($allReduceThroughputTax < 0 || $allReduceThroughputTax >= 1) {
            throw new \InvalidArgumentException('Throughput tax is a fraction in [0, 1).');
        }
        $effectiveGpus = $this->gpusPerStation * $stations * (1.0 - $allReduceThroughputTax);
        if ($effectiveGpus <= $this->gpusPerStation) {
            return 0.0;
        }
        return ($effectiveGpus - $this->gpusPerStation) * $this->monthlyPricePerGpu;
    }

    /**
     * Budget the cold start.
     *
     * A multi-node bring-up runs a Ray cluster, initializes NCCL
     * communicators, and ships weights across machines: minutes, not
     * seconds. At $2.49/GPU-hour an eight-GPU station burns 33 cents per
     * cold minute before the first token.
     */
    public function coldStartCostPerMinute(?float $hourlyPricePerGpu = null): float
    {
        $price = $hourlyPricePerGpu ?? $this->monthlyPricePerGpu / 730.0;
        if ($price <= 0) {
            throw new \InvalidArgumentException('GPU price must be positive.');
        }
        return $price / 60.0 * $this->gpusPerStation;
    }

    /** Rentable GPU count behind one station. */
    public function gpusPerStation(): int
    {
        return $this->gpusPerStation;
    }

    /** Month length used for hourly conversion, in hours (730 = cloud norm). */
    public const MONTH_HOURS = 730;
}
