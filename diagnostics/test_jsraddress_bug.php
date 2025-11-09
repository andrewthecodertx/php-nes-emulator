<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Code at $8050-$805F:\n";
for ($addr = 0x8050; $addr <= 0x805F; $addr++) {
    printf("$%04X: %02X\n", $addr, $mapper->cpuRead($addr));
}

echo "\nDisassembly:\n";
echo "$8050: 78       SEI\n";
echo "$8051: 07       ???  (illegal opcode - SLO?)\n";
echo "$8052: 09 80    ORA #$80\n";
echo "$8054: 20 ED 8E JSR $8EED\n";
echo "$8057: 4C 57 80 JMP $8057  <- infinite loop\n";

echo "\nThe JSR at $8054 should return to $8057.\n";
echo "When RTS executes, it pops the return address from stack.\n";
echo "JSR pushes PC+2 (so JSR at $8054 pushes $8056).\n";
echo "RTS pulls address and adds 1, so returns to $8057.\n";
echo "\nThis is CORRECT behavior - $8057 is intentionally the return address!\n";
echo "The question is: why is the code executing JSR $8EED?\n\n";

// Check what's at $804F-$8054
echo "Earlier code:\n";
for ($addr = 0x8048; $addr <= 0x8056; $addr++) {
    printf("$%04X: %02X\n", $addr, $mapper->cpuRead($addr));
}

echo "\nLet me check what leads to $804C:\n";

$nes2 = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes2->getCPU();

use andrewthecoder\MOS6502\CPUMonitor;
$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes2->runFrame();
}

// Run frame 4 until we hit $804C
$monitor->clearLog();

for ($i = 0; $i < 10000; $i++) {
    if ($cpu->pc === 0x804C) {
        echo "Hit $804C in frame 4\n";
        
        $instructions = $monitor->getInstructions();
        $start = max(0, count($instructions) - 10);
        
        echo "Previous 10 instructions:\n";
        for ($j = $start; $j < count($instructions); $j++) {
            $inst = $instructions[$j];
            printf("  $%04X: %-3s\n", $inst['pc'], $inst['opcode']);
        }
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
