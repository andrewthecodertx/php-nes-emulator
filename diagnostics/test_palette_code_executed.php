<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 10 frames
for ($f = 1; $f <= 10; $f++) {
    $nes->runFrame();
}

$instructions = $monitor->getInstructions();

echo "Checking if palette init code at \$8ECD is executed...\n\n";

$executed = false;
foreach ($instructions as $inst) {
    if ($inst['pc'] === 0x8ECD) {
        $executed = true;
        break;
    }
}

if ($executed) {
    echo "YES - Palette init code at \$8ECD WAS executed!\n";
    echo "The problem must be elsewhere (PPU register writes not working?)\n";
} else {
    echo "NO - Palette init code at \$8ECD was NEVER executed!\n";
    echo "The game is not reaching the palette initialization routine.\n";
    echo "This explains why the palette remains all zeros.\n";
}

echo "\nDone!\n";
