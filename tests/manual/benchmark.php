<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== NES Emulator Performance Benchmark ===\n\n";

$roms = [
    'donkeykong.nes' => 'Donkey Kong',
    'supermario.nes' => 'Super Mario Bros',
];

foreach ($roms as $romFile => $name) {
    $path = __DIR__ . '/../../roms/' . $romFile;

    if (!file_exists($path)) {
        echo "⊘ $name - File not found\n";
        continue;
    }

    echo "Testing: $name\n";

    $nes = NES::fromROM($path);

    // Warmup
    $nes->runFrame();

    // Benchmark running 10 frames
    $frames = 10;
    $start = microtime(true);

    for ($i = 0; $i < $frames; $i++) {
        $nes->runFrame();
    }

    $elapsed = microtime(true) - $start;
    $avgPerFrame = $elapsed / $frames;
    $fps = 1.0 / $avgPerFrame;

    printf("  %d frames in %.2f seconds\n", $frames, $elapsed);
    printf("  Average: %.3f seconds per frame\n", $avgPerFrame);
    printf("  Speed: %.2f FPS (target: 60 FPS)\n", $fps);
    printf("  Efficiency: %.1f%% of real-time\n\n", ($fps / 60.0) * 100);
}

echo "=== Profiling Single Frame ===\n\n";

// More detailed profiling
$nes = NES::fromROM(__DIR__ . '/../../roms/supermario.nes');

// Enable Xdebug profiling if available
if (function_exists('xdebug_start_trace')) {
    echo "Xdebug available - use XDEBUG_PROFILE=1 for detailed profiling\n\n";
}

// Manual timing of key operations
$iterations = 1000;

// Test 1: PPU clock cycles
$ppu = $nes->getPPU();
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $ppu->clock();
}
$ppuTime = microtime(true) - $start;
printf("PPU clock() x%d: %.4f seconds (%.2f µs each)\n", $iterations, $ppuTime, ($ppuTime / $iterations) * 1000000);

// Test 2: Full frame
$start = microtime(true);
$nes->runFrame();
$frameTime = microtime(true) - $start;
printf("Full frame: %.4f seconds\n", $frameTime);

// Calculate cycles per frame
$cyclesPerFrame = 29780; // NTSC: ~29780 CPU cycles per frame
$cpuCyclesPerSecond = $cyclesPerFrame / $frameTime;
printf("Effective CPU speed: %.2f MHz (target: 1.79 MHz)\n", $cpuCyclesPerSecond / 1000000);

echo "\n=== Analysis ===\n";
echo "Target: 60 FPS = 16.67ms per frame\n";
printf("Current: %.2f FPS = %.2fms per frame\n", 1.0 / $frameTime, $frameTime * 1000);
printf("Slowdown factor: %.1fx\n", $frameTime / (1.0 / 60.0));
