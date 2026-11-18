#!/usr/bin/env python3
"""All-reduce tax arithmetic for multi-node tensor-parallel serving.

The Python sibling of the PHP helpers in this repo. The PHP side normalizes
a throughput headline and prices hardware; this module prices the network:
how big one tensor-parallel all-reduce is, how much wire a decode step
moves, and how long it takes on the links you actually have.

Message size per all-reduce: batch_size * hidden_size * bytes_per_element.
Per-GPU ring-all-reduce traffic: 2(N-1)/N * message_size (full message at
N=2). Two collectives per layer in tensor parallelism.
"""
import argparse

def message_kib(batch: int, hidden: int, bytes_per_element: int) -> float:
    """One all-reduce payload in KiB."""
    return batch * hidden * bytes_per_element / 1024

def step_kib(layers: int, batch: int, hidden: int, bytes_per_element: int,
             collectives_per_layer: int = 2) -> float:
    """Total all-reduce wire per decode step, per machine, in KiB."""
    return message_kib(batch, hidden, bytes_per_element) * collectives_per_layer * layers

def link_ms(kib: float, gb_per_s: float) -> float:
    """Time to move kib across a link rated gb_per_s, in milliseconds."""
    return kib * 1024 / (gb_per_s * 1e9) * 1000

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--batch", type=int, default=32)
    ap.add_argument("--hidden", type=int, default=8192)
    ap.add_argument("--bytes", dest="bpe", type=int, default=2, help="2=BF16, 1=FP8")
    ap.add_argument("--layers", type=int, default=60)
    ap.add_argument("--nvlink-gbs", type=float, default=900.0)
    ap.add_argument("--eth-gbs", type=float, default=25.0, help="200GbE, per direction")
    a = ap.parse_args()

    msg = message_kib(a.batch, a.hidden, a.bpe)
    step = step_kib(a.layers, a.batch, a.hidden, a.bpe)
    nv_ms = link_ms(step, a.nvlink_gbs)
    eth_ms = link_ms(step, a.eth_gbs)

    print(f"all-reduce message:  {msg:.0f} KiB (batch {a.batch} x hidden {a.hidden} x {a.bpe}B)")
    print(f"per decode step:     {step / 1024:.0f} MiB across {a.layers} layers")
    print(f"NVLink {a.nvlink_gbs:.0f} GB/s:   {nv_ms * 1000:.0f} us")
    print(f"Ethernet {a.eth_gbs:.0f} GB/s: {eth_ms:.1f} ms")
    print(f"tax ratio:           {eth_ms / nv_ms:.0f}x in-box")

if __name__ == "__main__":
    main()
