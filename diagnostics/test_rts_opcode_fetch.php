<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();
$mapper = $nes->getMapper();

// Run 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes->runFrame();
}

// Frame 4 - check what byte is at $8230
for ($i = 0; $i < 10000; $i++) {
    if ($cpu->pc === 0x8230) {
        echo "Reached PC=$8230\n\n";
        
        // Check the byte at $8230
        $byte = $mapper->cpuRead(0x8230);
        printf("Byte at \$8230: \$%02X\n", $byte);
        
        if ($byte === 0x60) {
            echo "This is RTS opcode (correct)\n\n";
        } else {
            echo "This is NOT RTS! Expected \$60\n\n";
        }
        
        // Check surrounding bytes
        echo "Surrounding bytes:\n";
        for ($addr = 0x822D; $addr <= 0x8233; $addr++) {
            $b = $mapper->cpuRead($addr);
            printf("  \$%04X: %02X", $addr, $b);
            if ($addr === 0x8230) echo " <-- PC is here";
            echo "\n";
        }
        
        // Now let's manually check if the CPU recognizes this opcode
        echo "\nChecking if CPU can decode this opcode:\n";
        
        // Use reflection to access the instruction register
        $reflection = new ReflectionClass($cpu);
        $irProperty = $reflection->getProperty('instructionRegister');
        $irProperty->setAccessible(true);
        $ir = $irProperty->getValue($cpu);
        
        $opcodeData = $ir->getOpcode('0x60');
        
        if ($opcodeData) {
            printf("  Opcode 0x60 found: %s\n", $opcodeData->getMnemonic());
            printf("  Addressing mode: %s\n", $opcodeData->getAddressingMode());
            printf("  Cycles: %d\n", $opcodeData->getCycles());
        } else {
            echo "  Opcode 0x60 NOT FOUND in instruction register!\n";
        }
        
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
