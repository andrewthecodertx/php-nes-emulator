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
for ($frame = 0; $frame < 10; $frame++) {
    $nes->runFrame();
}

$instructions = $monitor->getInstructions();

// Count PC frequency
$pcCounts = [];
foreach ($instructions as $inst) {
    $pc = $inst['pc'];
    $pcCounts[$pc] = ($pcCounts[$pc] ?? 0) + 1;
}

arsort($pcCounts);

echo "Super Mario - Top 20 most executed addresses:\n\n";

$count = 0;
foreach ($pcCounts as $pc => $execCount) {
    printf("  $%04X: %5d times (%.1f%%)\n", 
        $pc, $execCount, ($execCount / count($instructions)) * 100);
    
    if (++$count >= 20) break;
}

// Check if there's a tight loop
echo "\nLooking for tight loops (consecutive PCs executed many times):\n";
foreach ($pcCounts as $pc => $execCount) {
    if ($execCount > 1000) {
        $nextPC = $pc + 1;
        $nextCount = $pcCounts[$nextPC] ?? 0;
        
        if (abs($execCount - $nextCount) < 100) {
            printf("  Potential loop: $%04X (%d) -> $%04X (%d)\n", 
                $pc, $execCount, $nextPC, $nextCount);
        }
    }
}

echo "\nDone!\n";
