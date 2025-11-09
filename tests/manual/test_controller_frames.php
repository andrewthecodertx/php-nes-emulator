<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Input\Controller;

echo "=== Testing Controller Input Over Multiple Frames ===\n\n";

$romPath = __DIR__ . '/../../roms/supermario.nes';

if (!file_exists($romPath)) {
    echo "Error: supermario.nes not found\n";
    exit(1);
}

$nes = NES::fromROM($romPath);
$bus = $nes->getBus();
$controller = $bus->getController1();

// Run 10 frames
for ($i = 0; $i < 10; $i++) {
    $nes->runFrame();
}

// Force rendering
$bus->write(0x2001, 0x1E);

// Press START and hold for 30 frames
echo "Pressing START button and holding for 30 frames...\n";
$controller->setButtonStates(Controller::BUTTON_START);

for ($frame = 1; $frame <= 30; $frame++) {
    $nes->runFrame();

    // Check palette every 5 frames
    if ($frame % 5 == 0) {
        $ppu = $nes->getPPU();
        $reflection = new ReflectionClass($ppu);
        $paletteRamProp = $reflection->getProperty('paletteRam');
        $paletteRamProp->setAccessible(true);
        $paletteRam = $paletteRamProp->getValue($ppu);

        $uniqueColors = count(array_unique(array_slice($paletteRam, 0, 32)));
        $nonZero = count(array_filter(array_slice($paletteRam, 0, 32), fn($x) => $x !== 0));

        echo "Frame $frame: Unique palette entries: $uniqueColors, Non-zero: $nonZero\n";

        if ($uniqueColors > 1) {
            echo "  First 16 palette bytes: ";
            for ($i = 0; $i < 16; $i++) {
                echo sprintf("%02X ", $paletteRam[$i]);
            }
            echo "\n";
        }
    }
}

echo "\n";
