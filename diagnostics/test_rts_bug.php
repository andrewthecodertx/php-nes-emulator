<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();
$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes->runFrame();
}

// Frame 4 - trace the RTS at $8230
$monitor->clearLog();

for ($i = 0; $i < 10000; $i++) {
    if ($cpu->pc === 0x8230) {
        echo "Found RTS at \$8230\n\n";
        
        $instructions = $monitor->getInstructions();
        $idx = count($instructions);
        
        echo "Previous 20 instructions:\n";
        for ($j = max(0, $idx - 20); $j < $idx; $j++) {
            $inst = $instructions[$j];
            printf("%5d: \$%04X %-3s", $j, $inst['pc'], $inst['opcode']);
            
            if ($inst['opcode'] === 'JSR') {
                echo " *** JSR";
            }
            if ($inst['opcode'] === 'RTS') {
                echo " *** RTS";
            }
            
            echo "\n";
        }
        
        // Now step once to execute the RTS
        $cpu->step();
        
        printf("\nAfter RTS: PC = \$%04X\n", $cpu->pc);
        printf("Expected: Should return to address after the JSR that called this\n");
        printf("Actual: Returns to \$%04X which leads to crash at \$8057\n\n", $cpu->pc);
        
        // Find the most recent JSR before this RTS
        echo "Looking for the JSR that should match this RTS:\n";
        for ($k = $idx - 1; $k >= max(0, $idx - 50); $k--) {
            $inst = $instructions[$k];
            if ($inst['opcode'] === 'JSR') {
                printf("Most recent JSR: \$%04X\n", $inst['pc']);
                printf("Should return to: \$%04X\n", $inst['pc'] + 3);
                break;
            }
        }
        
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
