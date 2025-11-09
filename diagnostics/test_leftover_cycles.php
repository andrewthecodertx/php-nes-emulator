<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

// Check if hasPendingCycles method exists
if (!method_exists($cpu, 'hasPendingCycles')) {
    echo "ERROR: hasPendingCycles() method not found in CPU!\n";
    echo "The fix wasn't properly applied.\n";
    exit(1);
}

echo "hasPendingCycles() method EXISTS ✓\n\n";

// Run a few frames and check cycles after each
for ($frame = 1; $frame <= 5; $frame++) {
    $cyclesBefore = $cpu->hasPendingCycles();
    
    $nes->runFrame();
    
    $cyclesAfter = $cpu->hasPendingCycles();
    
    printf("Frame %d: Cycles before=%s  after=%s\n",
        $frame,
        $cyclesBefore ? 'YES' : 'NO',
        $cyclesAfter ? 'YES' : 'NO'
    );
}

echo "\nDone!\n";
