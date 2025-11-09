<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Check if PPU is advancing 3x for every CPU cycle
 */

function testClockRatio(string $romPath): void
{
    echo "=== Testing $romPath ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $ppu = $nes->getPPU();

    echo "Initial state:\n";
    echo "  CPU cycles: {$cpu->cycles}\n";
    echo "  PPU scanline: {$ppu->getScanline()}, cycle: {$ppu->getCycle()}\n\n";

    // Execute 100 CPU instructions
    echo "Executing 100 CPU instructions...\n";
    for ($i = 0; $i < 100; $i++) {
        $cpuCyclesBefore = $cpu->cycles;
        $ppuCycleBefore = $ppu->getCycle();
        $ppuScanlineBefore = $ppu->getScanline();

        $cpu->step();

        $cpuCyclesAfter = $cpu->cycles;
        $ppuCycleAfter = $ppu->getCycle();
        $ppuScanlineAfter = $ppu->getScanline();

        $cpuCyclesUsed = $cpuCyclesAfter - $cpuCyclesBefore;

        // First few instructions
        if ($i < 10) {
            printf("  Instruction %d: CPU +%d cycles, PPU was (%d,%d), now (%d,%d)\n",
                $i,
                $cpuCyclesUsed,
                $ppuScanlineBefore,
                $ppuCycleBefore,
                $ppuScanlineAfter,
                $ppuCycleAfter
            );
        }
    }

    echo "\nFinal state:\n";
    echo "  CPU cycles: {$cpu->cycles}\n";
    echo "  PPU scanline: {$ppu->getScanline()}, cycle: {$ppu->getCycle()}\n";
    echo "\n";
}

testClockRatio(__DIR__ . '/roms/donkeykong.nes');
testClockRatio(__DIR__ . '/roms/supermario.nes');

echo "Done!\n";
