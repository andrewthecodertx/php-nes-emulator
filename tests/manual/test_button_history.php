<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Input\Controller;

echo "=== Testing Button History (Simulating Viewer Backend) ===\n\n";

$romPath = __DIR__ . '/../../roms/supermario.nes';

if (!file_exists($romPath)) {
    echo "Error: supermario.nes not found\n";
    exit(1);
}

// Simulate what the backend does:
// 1. Run to frame 11 (initial reset)
// 2. Step 1 frame with START button pressed
// 3. Step a few more frames with START held

$buttonHistory = [];

echo "Step 1: Initial reset (run 11 frames, no buttons)\n";
$nes = NES::fromROM($romPath);
$controller = $nes->getBus()->getController1();

for ($i = 0; $i < 11; $i++) {
    $frameButtons = $buttonHistory[$i] ?? 0x00;
    $controller->setButtonStates($frameButtons);
    $nes->runFrame();

    if ($i == 10) {
        $nes->getBus()->write(0x2001, 0x1E); // Force rendering
    }
}
echo "  Frame 11 reached\n\n";

// Now simulate pressing START and stepping 5 frames
echo "Step 2: Press START and step 5 frames\n";
$currentFrame = 11;
$startButton = Controller::BUTTON_START;

for ($frameNum = $currentFrame; $frameNum < $currentFrame + 5; $frameNum++) {
    $buttonHistory[$frameNum] = $startButton;
    echo "  Frame $frameNum: START button = 0x" . sprintf("%02X", $startButton) . "\n";
}

// Recreate NES and replay with button history
$nes = NES::fromROM($romPath);
$controller = $nes->getBus()->getController1();

for ($i = 0; $i < $currentFrame + 5; $i++) {
    $frameButtons = $buttonHistory[$i] ?? 0x00;
    $controller->setButtonStates($frameButtons);
    $nes->runFrame();

    if ($i == 10) {
        $nes->getBus()->write(0x2001, 0x1E);
    }
}

echo "\n";
echo "Step 3: Check palette after START button held for 5 frames\n";

$ppu = $nes->getPPU();
$reflection = new ReflectionClass($ppu);
$paletteRamProp = $reflection->getProperty('paletteRam');
$paletteRamProp->setAccessible(true);
$paletteRam = $paletteRamProp->getValue($ppu);

$uniqueColors = count(array_unique(array_slice($paletteRam, 0, 32)));
$nonZero = count(array_filter(array_slice($paletteRam, 0, 32), fn($x) => $x !== 0));

echo "  Unique palette entries: $uniqueColors\n";
echo "  Non-zero palette entries: $nonZero\n";

if ($nonZero > 0) {
    echo "  First 16 palette bytes: ";
    for ($i = 0; $i < 16; $i++) {
        echo sprintf("%02X ", $paletteRam[$i]);
    }
    echo "\n  ✅ SUCCESS: Palette initialized!\n";
} else {
    echo "  ❌ FAILED: Palette still all zeros\n";
}

echo "\n";
