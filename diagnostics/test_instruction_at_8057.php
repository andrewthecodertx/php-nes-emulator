<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Instructions around $8057:\n\n";

for ($addr = 0x8050; $addr <= 0x8060; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("$%04X: %02X", $addr, $byte);
    
    if ($addr === 0x8057) {
        echo " <-- HOTSPOT (38.5% of execution)";
    }
    
    echo "\n";
}

// Let's also check what the code flow looks like
echo "\nLet me trace execution around $8057:\n";

$nes2 = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes2->getCPU();

use andrewthecoder\MOS6502\CPUMonitor;

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run until we hit $8057
for ($i = 0; $i < 200; $i++) {
    if ($cpu->pc === 0x8057) {
        echo "\nReached $8057 after $i instructions\n";
        
        // Execute 20 more instructions to see the loop
        echo "Next 20 instructions:\n";
        for ($j = 0; $j < 20; $j++) {
            $pc = $cpu->pc;
            $cpu->step();
            
            $instructions = $monitor->getInstructions();
            $lastInst = end($instructions);
            
            printf("  $%04X: %-3s\n", $pc, $lastInst['opcode']);
        }
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
