<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Testing CPU Serialization ===\n\n";

// Create a NES instance and run a few frames
echo "1. Creating NES instance...\n";
$nes = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');

echo "2. Running 5 frames...\n";
for ($i = 0; $i < 5; $i++) {
    $nes->runFrame();
}

$cpu = $nes->getCPU();
echo "3. CPU state before serialization:\n";
echo "   PC: 0x" . dechex($cpu->pc) . "\n";
echo "   SP: 0x" . dechex($cpu->sp) . "\n";
echo "   A: 0x" . dechex($cpu->accumulator) . "\n";
echo "   X: 0x" . dechex($cpu->registerX) . "\n";
echo "   Y: 0x" . dechex($cpu->registerY) . "\n";
echo "   Cycles: " . $cpu->cycles . "\n\n";

// Try to serialize the CPU
echo "4. Serializing CPU...\n";
try {
    $serialized = serialize($cpu);
    echo "   ✓ CPU serialized successfully (" . strlen($serialized) . " bytes)\n\n";

    // Unserialize
    echo "5. Unserializing CPU...\n";
    $cpu2 = unserialize($serialized);
    echo "   ✓ CPU unserialized successfully\n\n";

    // Verify state
    echo "6. CPU state after unserialization:\n";
    echo "   PC: 0x" . dechex($cpu2->pc) . "\n";
    echo "   SP: 0x" . dechex($cpu2->sp) . "\n";
    echo "   A: 0x" . dechex($cpu2->accumulator) . "\n";
    echo "   X: 0x" . dechex($cpu2->registerX) . "\n";
    echo "   Y: 0x" . dechex($cpu2->registerY) . "\n";
    echo "   Cycles: " . $cpu2->cycles . "\n\n";

    // Verify they match
    if ($cpu->pc === $cpu2->pc &&
        $cpu->sp === $cpu2->sp &&
        $cpu->accumulator === $cpu2->accumulator &&
        $cpu->registerX === $cpu2->registerX &&
        $cpu->registerY === $cpu2->registerY) {
        echo "✓ CPU state preserved correctly!\n\n";
    } else {
        echo "✗ CPU state mismatch!\n\n";
    }

} catch (Exception $e) {
    echo "   ✗ Serialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Now try serializing the entire NES instance
echo "7. Serializing entire NES instance...\n";
try {
    $nesData = serialize($nes);
    echo "   ✓ NES serialized successfully (" . strlen($nesData) . " bytes)\n\n";

    echo "8. Unserializing NES instance...\n";
    $nes2 = unserialize($nesData);
    echo "   ✓ NES unserialized successfully\n\n";

    // Run a frame on the unserialized instance
    echo "9. Running frame on unserialized NES...\n";
    $nes2->runFrame();
    echo "   ✓ Frame executed successfully\n\n";

    echo "✓✓✓ Full serialization test passed! ✓✓✓\n";

} catch (Exception $e) {
    echo "   ✗ NES serialization failed: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
