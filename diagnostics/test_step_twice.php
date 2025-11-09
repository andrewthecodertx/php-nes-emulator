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

// Frame 4 - step multiple times at $8230
for ($i = 0; $i < 10000; $i++) {
    if ($cpu->pc === 0x8230) {
        echo "At \$8230 (RTS)\n";
        
        // Use reflection to check cycles
        $reflection = new ReflectionClass($cpu);
        $cyclesProperty = $reflection->getProperty('cycles');
        $cyclesProperty->setAccessible(true);
        
        // Step until cycles is 0 and instruction executes
        for ($step = 1; $step <= 10; $step++) {
            $cyclesBefore = $cyclesProperty->getValue($cpu);
            $pcBefore = $cpu->pc;
            
            printf("\nStep %d:\n", $step);
            printf("  Before: PC=\$%04X  Cycles=%d\n", $pcBefore, $cyclesBefore);
            
            $cpu->step();
            
            $cyclesAfter = $cyclesProperty->getValue($cpu);
            $pcAfter = $cpu->pc;
            
            printf("  After:  PC=\$%04X  Cycles=%d\n", $pcAfter, $cyclesAfter);
            
            if ($pcAfter !== $pcBefore) {
                echo "  >>> PC CHANGED! Instruction executed!\n";
                break;
            }
            
            if ($cyclesAfter === 0 && $pcAfter === $pcBefore) {
                echo "  >>> Next step() should execute the instruction\n";
            }
        }
        
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
