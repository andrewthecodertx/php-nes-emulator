<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function testPPUStatus(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $bus = $nes->getBus();

    // Run first 10 instructions
    for ($i = 0; $i < 10; $i++) {
        $oldPC = $cpu->pc;
        $cpu->step();
        
        // Check if this was a read from $2002
        if ($i >= 6) {  // After initialization
            // Try reading PPUSTATUS directly
            $status = $bus->read(0x2002);
            printf("  After instruction %d: PC=$%04X, PPUSTATUS=$%02X (VBlank=%d)\n",
                $i, $oldPC, $status, ($status & 0x80) ? 1 : 0);
        }
    }

    echo "\n";
}

testPPUStatus(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
testPPUStatus(__DIR__ . '/roms/supermario.nes', 'Super Mario');

echo "Done!\n";
