# serving-cost

Serving cost math for self-hosted LLM inference. A throughput headline is
not a cost number until it is divided by the hardware that produced it and
multiplied by the price of that hardware. This library carries the small,
repeatable arithmetic an inference audit needs: normalizing a multi-station
demo to per-GPU throughput, pricing a million tokens at a given GPU rate,
costing the parity premium of splitting one model across machines, and
budgeting the cold start a demo never shows.

```php
use PoketDev\ServingCost\ServingCost;
use function PoketDev\ServingCost\dollars_per_million_tokens;

$sc = new ServingCost(2149.0); // monthly price per GPU

// The audited 607.8 tok/s two-station headline, normalized per GPU:
$sc->perGpuThroughput(607.8, 2);          // 303.9 tok/s per station
$sc->perGpuThroughput(303.9, 1, 4);       // ~76 tok/s per GPU on a 4-GPU station

// Dollars per million tokens at that GPU rate:
dollars_per_million_tokens(2.49, 75.975); // $9.10 / M tokens

// The parity premium of the second machine at matched throughput:
$sc->parityPremium(607.8, 2);             // $17,192/mo, one extra station

// Cold start budget: $0.33 per minute of cluster bring-up.
$sc->coldStartCostPerMinute(2.49);
```

- `perGpuThroughput`: the only throughput figure comparable across
  deployments. A headline measured across two stations divides by stations
  first, then by the accelerators one station contributes.
- `dollarsPerMillionTokens`: converts a GPU rate into the unit buyers
  actually compare against API pricing.
- `parityPremium`: prices the second machine. Tensor parallelism moves
  activations sideways twice per layer (two all-reduce collectives per
  forward pass), so the matched-throughput second station is redundancy
  plus interconnect, and the redundancy has a monthly price.
- `coldStartCostPerMinute`: multi-node bring-up runs a Ray cluster,
  initializes NCCL communicators, and ships weights across machines.
  Minutes, not seconds, and the meter runs the whole time.

## Running the tests

    composer install
    ./vendor/bin/phpunit

MIT. Maintained by [Poket Dev](https://pastagi.com).
