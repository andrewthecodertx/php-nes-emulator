<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run one frame and check when $8057 first appears
$nes->runFrame();

$instructions = $monitor->getInstructions();

$firstHit = null;
foreach ($instructions as $i => $inst) {
    if ($inst['pc'] === 0x8057) {
        $firstHit = $i;
        break;
    }
}

if ($firstHit !== null) {
    printf("First hit of $8057 at instruction #%d\n\n", $firstHit);
    
    echo "20 instructions before first hit:\n";
    $start = max(0, $firstHit - 20);
    
    for ($i = $start; $i <= min($firstHit + 5, count($instructions) - 1); $i++) {
        $inst = $instructions[$i];
        printf("%5d: PC=$%04X  op=$%02X  %-3s", 
            $i, $inst['pc'], $inst['instruction'], $inst['opcode']);
        
        if ($i === $firstHit) {
            echo " <-- FIRST HIT OF INFINITE LOOP";
        }
        
        echo "\n";
    }
} else {
    echo "$8057 not found in first frame\n";
}

echo "\nDone!\n";
