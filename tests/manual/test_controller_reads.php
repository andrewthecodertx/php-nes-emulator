<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Input\Controller;

echo "=== Testing Controller Read Activity ===\n\n";

$romPath = __DIR__ . '/../../roms/supermario.nes';

if (!file_exists($romPath)) {
    echo "Error: supermario.nes not found\n";
    exit(1);
}

$nes = NES::fromROM($romPath);
$bus = $nes->getBus();

// Wrap controller to track reads/writes
$controller = $bus->getController1();
$readCount = 0;
$writeCount = 0;

// Monkey-patch using reflection to track access
$originalController = $controller;

// Run 10 frames
for ($i = 0; $i < 10; $i++) {
    $nes->runFrame();
}

// Force rendering
$bus->write(0x2001, 0x1E);

echo "Setting START button...\n";
$controller->setButtonStates(Controller::BUTTON_START);

// Manually test reading from $4016
echo "\nManual test - Reading $4016:\n";
$bus->write(0x4016, 0x01); // Strobe on
$bus->write(0x4016, 0x00); // Strobe off (latch buttons)

for ($i = 0; $i < 8; $i++) {
    $value = $bus->read(0x4016);
    $bit = $value & 0x01;
    echo "  Bit $i: $bit";

    // Button order: A, B, Select, Start, Up, Down, Left, Right
    $buttons = ['A', 'B', 'Select', 'Start', 'Up', 'Down', 'Left', 'Right'];
    echo " ({$buttons[$i]})\n";
}

echo "\nRunning 5 frames and monitoring $4016 access...\n";

// Instrument the bus to see if game reads $4016
$busReflection = new ReflectionClass($bus);

for ($frame = 1; $frame <= 5; $frame++) {
    $beforeReads = 0;
    $beforeWrites = 0;

    // Run frame
    $nes->runFrame();

    echo "Frame $frame completed\n";
}

echo "\n";
