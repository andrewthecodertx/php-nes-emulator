<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run first 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes->runFrame();
}

// Frame 4
$monitor->clearLog();
$nes->runFrame();

$instructions = $monitor->getInstructions();

echo "Searching for execution of \$8046 in frame 4...\n\n";

$found = false;
foreach ($instructions as $i => $inst) {
    if ($inst['pc'] === 0x8046) {
        $found = true;
        echo "Found \$8046 at instruction #$i\n\n";
        
        echo "20 instructions before:\n";
        for ($j = max(0, $i - 20); $j < $i; $j++) {
            $prev = $instructions[$j];
            printf("  %5d: \$%04X %-3s\n", $j, $prev['pc'], $prev['opcode']);
        }
        
        printf(">>> %5d: \$%04X JSR <<<\n", $i, $inst['pc']);
        
        echo "\n5 instructions after:\n";
        for ($j = $i + 1; $j <= min($i + 5, count($instructions) - 1); $j++) {
            $next = $instructions[$j];
            printf("  %5d: \$%04X %-3s\n", $j, $next['pc'], $next['opcode']);
        }
        
        break;
    }
}

if (!$found) {
    echo "\$8046 was NOT executed in frame 4.\n";
    echo "This means the JSR that causes the problem isn't at \$8046.\n";
    echo "Let me search for who pushes \$8048 to the stack...\n";
}

echo "\nDone!\n";
