<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Detailed Profiling ===\n\n";

$nes = NES::fromROM(__DIR__ . '/../../roms/supermario.nes');

// Profile different aspects
$iterations = 100;

// 1. Memory reads (very common operation)
$bus = $nes->getBus();
$start = microtime(true);
for ($i = 0; $i < $iterations * 1000; $i++) {
    $bus->read(0x8000 + ($i % 100));
}
$readTime = microtime(true) - $start;
printf("Bus reads x%d: %.4fs (%.2f ns each)\n", $iterations * 1000, $readTime, ($readTime / ($iterations * 1000)) * 1000000000);

// 2. Memory writes
$start = microtime(true);
for ($i = 0; $i < $iterations * 1000; $i++) {
    $bus->write(0x0000 + ($i % 0x800), $i & 0xFF);
}
$writeTime = microtime(true) - $start;
printf("Bus writes x%d: %.4fs (%.2f ns each)\n", $iterations * 1000, $writeTime, ($writeTime / ($iterations * 1000)) * 1000000000);

// 3. CPU step (one cycle)
$cpu = $nes->getCPU();
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $cpu->step();
}
$cpuTime = microtime(true) - $start;
printf("CPU step x%d: %.4fs (%.2f µs each)\n", $iterations, $cpuTime, ($cpuTime / $iterations) * 1000000);

// 4. PPU clock
$ppu = $nes->getPPU();
$start = microtime(true);
for ($i = 0; $i < $iterations * 100; $i++) {
    $ppu->clock();
}
$ppuTime = microtime(true) - $start;
printf("PPU clock x%d: %.4fs (%.2f µs each)\n", $iterations * 100, $ppuTime, ($ppuTime / ($iterations * 100)) * 1000000);

// 5. Full system clock (1 PPU + CPU every 3rd)
$start = microtime(true);
for ($i = 0; $i < $iterations * 10; $i++) {
    $nes->clock();
}
$systemTime = microtime(true) - $start;
printf("System clock x%d: %.4fs (%.2f µs each)\n", $iterations * 10, $systemTime, ($systemTime / ($iterations * 10)) * 1000000);

echo "\n=== Bottleneck Analysis ===\n";

// A full frame is approximately:
// - 89342 PPU cycles (341 * 262 scanlines)
// - 29780 CPU cycles (89342 / 3)

$ppuCyclesPerFrame = 89342;
$cpuCyclesPerFrame = 29780;

$estimatedPpuTime = ($ppuTime / ($iterations * 100)) * $ppuCyclesPerFrame;
$estimatedCpuTime = ($cpuTime / $iterations) * $cpuCyclesPerFrame;
$estimatedSystemTime = ($systemTime / ($iterations * 10)) * $ppuCyclesPerFrame;

printf("\nEstimated time per frame (based on micro-benchmarks):\n");
printf("  PPU alone: %.2fms\n", $estimatedPpuTime * 1000);
printf("  CPU alone: %.2fms\n", $estimatedCpuTime * 1000);
printf("  System clock: %.2fms\n", $estimatedSystemTime * 1000);

// Actual frame time
$start = microtime(true);
$nes->runFrame();
$actualFrameTime = microtime(true) - $start;
printf("  Actual frame: %.2fms\n", $actualFrameTime * 1000);

printf("\nOverhead: %.2fms (%.1f%% of total)\n",
    ($actualFrameTime - $estimatedSystemTime) * 1000,
    (($actualFrameTime - $estimatedSystemTime) / $actualFrameTime) * 100
);

echo "\n=== Recommendations ===\n";
echo "1. Optimize hot paths (PPU clock, CPU step, memory access)\n";
echo "2. Reduce function call overhead\n";
echo "3. Cache frequently accessed values\n";
echo "4. Consider using typed arrays or SplFixedArray\n";
echo "5. Profile with Xdebug or Blackfire for detailed analysis\n";
