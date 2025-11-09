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

// Frame 4 - check CPU internal state
for ($i = 0; $i < 10000; $i++) {
    if ($cpu->pc === 0x8230) {
        echo "Before step() at \$8230:\n";
        
        // Use reflection to check internal CPU state
        $reflection = new ReflectionClass($cpu);
        
        $cyclesProperty = $reflection->getProperty('cycles');
        $cyclesProperty->setAccessible(true);
        $cycles = $cyclesProperty->getValue($cpu);
        
        printf("  PC: \$%04X\n", $cpu->pc);
        printf("  SP: \$%02X\n", $cpu->sp);
        printf("  Cycles: %d\n", $cycles);
        
        // Check if halted
        $haltedProperty = $reflection->getProperty('halted');
        $haltedProperty->setAccessible(true);
        $halted = $haltedProperty->getValue($cpu);
        printf("  Halted: %s\n", $halted ? 'true' : 'false');
        
        echo "\nCalling step()...\n";
        
        $cpu->step();
        
        echo "\nAfter step():\n";
        $cycles = $cyclesProperty->getValue($cpu);
        printf("  PC: \$%04X\n", $cpu->pc);
        printf("  SP: \$%02X\n", $cpu->sp);
        printf("  Cycles: %d\n", $cycles);
        
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
