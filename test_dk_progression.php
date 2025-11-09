<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Donkey Kong Progression Analysis ===\n\n";

$nes = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');

// Initialize as backend does
for ($i = 0; $i < 10; $i++) {
    $nes->runFrame();
}

$bus = $nes->getBus();
$bus->write(0x2001, 0x1E); // forceRendering
$nes->runFrame();

echo "After initialization (11 frames):\n";
$cpu = $nes->getCPU();
echo "  PC: 0x" . dechex($cpu->pc) . "\n";
echo "  SP: 0x" . dechex($cpu->sp) . "\n";
echo "  A: 0x" . dechex($cpu->accumulator) . "\n";

$frameBuffer = $nes->getFrameBuffer();
$uniqueColors = [];
foreach ($frameBuffer as $pixel) {
    $color = implode(',', $pixel);
    $uniqueColors[$color] = true;
}
echo "  Colors: " . count($uniqueColors) . "\n\n";

// Sample some memory locations that might be game state
echo "Game state memory:\n";
echo "  \$0010: 0x" . dechex($bus->read(0x0010)) . "\n";
echo "  \$0011: 0x" . dechex($bus->read(0x0011)) . "\n";
echo "  \$0012: 0x" . dechex($bus->read(0x0012)) . "\n";
echo "  \$0013: 0x" . dechex($bus->read(0x0013)) . "\n";
echo "  \$0014: 0x" . dechex($bus->read(0x0014)) . "\n";
echo "  \$0015: 0x" . dechex($bus->read(0x0015)) . "\n\n";

// Now run 10 more frames and check if PC is looping
echo "Running 10 more frames...\n";
$pcHistory = [];
for ($i = 0; $i < 10; $i++) {
    $pcBefore = $cpu->pc;
    $nes->runFrame();
    $pcAfter = $cpu->pc;
    $pcHistory[] = sprintf("Frame %d: 0x%04X -> 0x%04X", $i + 12, $pcBefore, $pcAfter);
}

echo "PC trace:\n";
foreach ($pcHistory as $line) {
    echo "  $line\n";
}
echo "\n";

$frameBuffer = $nes->getFrameBuffer();
$uniqueColors = [];
foreach ($frameBuffer as $pixel) {
    $color = implode(',', $pixel);
    $uniqueColors[$color] = true;
}
echo "After 21 frames:\n";
echo "  PC: 0x" . dechex($cpu->pc) . "\n";
echo "  Colors: " . count($uniqueColors) . "\n";

if (count($uniqueColors) == 5) {
    echo "\n✗ Still stuck at 5 colors - screen not changing\n";
} else {
    echo "\n✓ Screen changed! Now " . count($uniqueColors) . " colors\n";
}
