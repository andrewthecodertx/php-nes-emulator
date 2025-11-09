<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run until we hit $8057
for ($i = 0; $i < 1000; $i++) {
    if ($cpu->pc === 0x8057) {
        echo "Reached infinite loop at $8057 after $i instructions\n\n";
        
        $instructions = $monitor->getInstructions();
        
        // Show the last 30 instructions before hitting the loop
        echo "Last 30 instructions before infinite loop:\n";
        $start = max(0, count($instructions) - 30);
        
        for ($j = $start; $j < count($instructions); $j++) {
            $inst = $instructions[$j];
            printf("%4d: PC=$%04X  op=$%02X  %-3s", 
                $j, $inst['pc'], $inst['instruction'], $inst['opcode']);
            
            if ($inst['pc'] === 0x8057) {
                echo " <-- INFINITE LOOP STARTS HERE";
            }
            
            echo "\n";
        }
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
