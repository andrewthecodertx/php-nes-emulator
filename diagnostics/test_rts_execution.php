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

// Frame 4 - find RTS and step through it carefully
for ($i = 0; $i < 10000; $i++) {
    if ($cpu->pc === 0x8230) {
        echo "At \$8230 (RTS instruction)\n";
        printf("  PC before step: \$%04X\n", $cpu->pc);
        printf("  SP before step: \$%02X\n", $cpu->sp);
        printf("  Cycles before:  %d\n", $cpu->getTotalCycles());
        
        $cpu->step();
        
        printf("  PC after step:  \$%04X\n", $cpu->pc);
        printf("  SP after step:  \$%02X\n", $cpu->sp);
        printf("  Cycles after:   %d\n", $cpu->getTotalCycles());
        
        // Step again to see next instruction
        printf("\nNext instruction:\n");
        printf("  PC: \$%04X\n", $cpu->pc);
        
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
