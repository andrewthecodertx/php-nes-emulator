<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Frame Speed Test ===\n\n";

$nes = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');

// Test how fast we can run frames
echo "Running 10 frames...\n";
$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    $nes->runFrame();
}
$elapsed = microtime(true) - $start;

echo "10 frames took: " . round($elapsed, 2) . " seconds\n";
echo "That's " . round(10 / $elapsed, 1) . " FPS\n";
echo "Per frame: " . round($elapsed / 10 * 1000, 1) . " ms\n";

if ($elapsed > 10) {
    echo "\n✗ VERY SLOW! Should be ~1-2 seconds for 10 frames\n";
} else {
    echo "\n✓ Normal speed\n";
}
