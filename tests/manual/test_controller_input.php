<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Input\Controller;

echo "=== Testing Controller Input ===\n\n";

$romPath = __DIR__ . '/../../roms/supermario.nes';

if (!file_exists($romPath)) {
    echo "Error: supermario.nes not found\n";
    exit(1);
}

$nes = NES::fromROM($romPath);

// Run 10 frames
for ($i = 0; $i < 10; $i++) {
    $nes->runFrame();
}

// Force rendering
$bus = $nes->getBus();
$bus->write(0x2001, 0x1E);

echo "Setting START button (0x08)...\n";
$controller = $bus->getController1();
$controller->setButtonStates(Controller::BUTTON_START);

echo "Button states set: 0x" . sprintf("%02X", $controller->getButtonStates()) . "\n";
echo "START pressed: " . ($controller->isButtonPressed(Controller::BUTTON_START) ? 'YES' : 'NO') . "\n\n";

// Run 1 frame with button pressed
echo "Running 1 frame with START pressed...\n";
$nes->runFrame();

// Check palette
$ppu = $nes->getPPU();
$reflection = new ReflectionClass($ppu);
$paletteRamProp = $reflection->getProperty('paletteRam');
$paletteRamProp->setAccessible(true);
$paletteRam = $paletteRamProp->getValue($ppu);

$uniqueColors = array_unique(array_slice($paletteRam, 0, 32));
echo "Unique palette entries: " . count($uniqueColors) . "\n";
echo "First 16 palette bytes: ";
for ($i = 0; $i < 16; $i++) {
    echo sprintf("%02X ", $paletteRam[$i]);
}
echo "\n\n";

// Check frame buffer
$frame = $ppu->getFrameBuffer();
$colorCounts = [];
foreach ($frame as $pixel) {
    $key = implode(',', $pixel);
    $colorCounts[$key] = ($colorCounts[$key] ?? 0) + 1;
}

echo "Unique colors in frame: " . count($colorCounts) . "\n";
if (count($colorCounts) <= 10) {
    echo "Colors found:\n";
    arsort($colorCounts);
    $count = 0;
    foreach ($colorCounts as $colorKey => $pixels) {
        list($r, $g, $b) = explode(',', $colorKey);
        printf("  RGB(%3d,%3d,%3d): %6d pixels\n", $r, $g, $b, $pixels);
        if (++$count >= 5) break;
    }
}

echo "\n";
