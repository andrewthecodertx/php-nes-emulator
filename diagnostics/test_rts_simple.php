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

// Frame 4 - find RTS
for ($i = 0; $i < 10000; $i++) {
    if ($cpu->pc === 0x8230) {
        printf("Before RTS: PC=\$%04X  SP=\$%02X\n", $cpu->pc, $cpu->sp);
        
        $cpu->step();
        
        printf("After RTS:  PC=\$%04X  SP=\$%02X\n", $cpu->pc, $cpu->sp);
        
        // The PC should have changed if RTS executed
        if ($cpu->pc === 0x8230) {
            echo "\nBUG: PC didn't change! RTS didn't execute!\n";
        } else {
            echo "\nRTS executed successfully\n";
        }
        
        break;
    }
    
    $cpu->step();
}

echo "\nDone!\n";
