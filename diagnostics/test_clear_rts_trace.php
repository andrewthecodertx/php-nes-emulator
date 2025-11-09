<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

// Run 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes->runFrame();
}

// Frame 4 - manually step and watch for RTS
for ($i = 0; $i < 10000; $i++) {
    $pc = $cpu->pc;
    $mapper = $nes->getMapper();
    $opcode = $mapper->cpuRead($pc);
    
    if ($pc === 0x8230 && $opcode === 0x60) { // RTS opcode is $60
        echo "About to execute RTS at \$8230\n";
        printf("  Current PC: \$%04X\n", $cpu->pc);
        printf("  Current SP: \$%02X\n", $cpu->sp);
        
        // Read what's on the stack
        $bus = $nes->getBus();
        $stackLow = $bus->read(0x0100 | (($cpu->sp + 1) & 0xFF));
        $stackHigh = $bus->read(0x0100 | (($cpu->sp + 2) & 0xFF));
        $stackValue = $stackLow | ($stackHigh << 8);
        
        printf("  Stack has: \$%04X (will return to \$%04X after RTS adds 1)\n", 
            $stackValue, $stackValue + 1);
        
        // Execute the RTS
        $cpu->step();
        
        printf("\nAfter RTS:\n");
        printf("  New PC: \$%04X\n", $cpu->pc);
        printf("  New SP: \$%02X\n", $cpu->sp);
        
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
