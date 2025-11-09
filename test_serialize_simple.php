<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Simple Serialization Test ===\n\n";

$nes = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');
$nes->runFrame();

// Test individual components
echo "1. Testing CPU...\n";
try {
    $cpu = $nes->getCPU();
    $serialized = serialize($cpu);
    echo "   ✓ CPU serialized OK (" . strlen($serialized) . " bytes)\n\n";
} catch (Exception $e) {
    echo "   ✗ CPU serialize failed: " . $e->getMessage() . "\n\n";
}

echo "2. Testing Bus...\n";
try {
    $bus = $nes->getBus();
    serialize($bus);
    echo "   ✓ Bus serialized OK\n\n";
} catch (Exception $e) {
    echo "   ✗ Bus serialize failed: " . $e->getMessage() . "\n\n";
}

echo "3. Testing PPU...\n";
try {
    $ppu = $nes->getPPU();
    serialize($ppu);
    echo "   ✓ PPU serialized OK\n\n";
} catch (Exception $e) {
    echo "   ✗ PPU serialize failed: " . $e->getMessage() . "\n\n";
}

echo "4. Testing StatusRegister...\n";
try {
    $status = $nes->getCPU()->status;
    serialize($status);
    echo "   ✓ Status serialized OK\n\n";
} catch (Exception $e) {
    echo "   ✗ Status serialize failed: " . $e->getMessage() . "\n\n";
}
