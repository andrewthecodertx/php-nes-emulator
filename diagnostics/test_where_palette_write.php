<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function findPaletteWrites(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $mapper = $nes->getMapper();
    $cpu = $nes->getCPU();
    $bus = $nes->getBus();

    // Search ROM for code that writes to $3F00 (palette)
    // Pattern: LDA #$3F, STA $2006, LDA #$00, STA $2006
    
    echo "Searching ROM for palette initialization code...\n";
    
    // Pattern: A9 3F 8D 06 20 A9 00 8D 06 20
    // LDA #$3F, STA $2006, LDA #$00, STA $2006
    
    $found = false;
    for ($addr = 0x8000; $addr <= 0xFFF0; $addr++) {
        $bytes = [];
        for ($i = 0; $i < 10; $i++) {
            $bytes[] = $mapper->cpuRead($addr + $i);
        }
        
        // Check for pattern
        if ($bytes[0] === 0xA9 && $bytes[1] === 0x3F &&  // LDA #$3F
            $bytes[2] === 0x8D && $bytes[3] === 0x06 && $bytes[4] === 0x20 &&  // STA $2006
            $bytes[5] === 0xA9 && $bytes[6] === 0x00 &&  // LDA #$00
            $bytes[7] === 0x8D && $bytes[8] === 0x06 && $bytes[9] === 0x20) {  // STA $2006
            
            printf("  Found palette init code at \$%04X\n", $addr);
            $found = true;
        }
    }
    
    if (!$found) {
        echo "  No standard palette init pattern found\n";
        echo "  Game might use different initialization method\n";
    }
    
    echo "\n";
}

findPaletteWrites(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (WORKS)');
findPaletteWrites(__DIR__ . '/roms/supermario.nes', 'Super Mario (BROKEN)');

echo "Done!\n";
