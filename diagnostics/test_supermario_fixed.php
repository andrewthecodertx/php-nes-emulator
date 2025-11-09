<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run frame by frame
for ($frame = 1; $frame <= 10; $frame++) {
    $monitor->clearLog();
    
    $nes->runFrame();
    
    $instructions = $monitor->getInstructions();
    
    // Check if $8057 appears (infinite loop)
    $count8057 = 0;
    foreach ($instructions as $inst) {
        if ($inst['pc'] === 0x8057) {
            $count8057++;
        }
    }
    
    printf("Frame %2d: %5d instructions, $8057 hit %5d times", 
        $frame, count($instructions), $count8057);
    
    if ($count8057 > 0) {
        printf(" (%.1f%%)", ($count8057 / count($instructions)) * 100);
        
        if ($count8057 > 100) {
            echo " <-- STUCK IN INFINITE LOOP";
        }
    }
    
    echo "\n";
}

echo "\nDone!\n";
