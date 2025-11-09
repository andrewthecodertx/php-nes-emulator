<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

/**
 * Trace memory reads during the VBlank wait loops
 * to see what values the games are reading
 */

function traceMemoryReads(string $romPath, int $maxInstructions): void
{
    echo "=== $romPath ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    // Attach CPU monitor
    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run one frame
    for ($i = 0; $i < $maxInstructions && !$nes->getPPU()->isFrameComplete(); $i++) {
        $cpu->step();
    }

    $memoryAccesses = $monitor->getMemoryAccesses();
    echo "Total memory accesses: " . count($memoryAccesses) . "\n";

    // Count reads from PPUSTATUS ($2002)
    $ppuStatusReads = array_filter($memoryAccesses, function($access) {
        return $access['address'] === 0x2002 && $access['type'] === 'read';
    });

    echo "PPUSTATUS reads ($2002): " . count($ppuStatusReads) . "\n";

    // Show first 20 PPUSTATUS reads
    echo "First 20 PPUSTATUS reads:\n";
    $count = 0;
    foreach ($ppuStatusReads as $access) {
        printf("  Read $2002 = %02X (binary: %08b)\n", $access['data'], $access['data']);
        if (++$count >= 20) break;
    }

    // Show unique values read from PPUSTATUS
    $uniqueValues = array_unique(array_column($ppuStatusReads, 'data'));
    echo "\nUnique PPUSTATUS values: " . count($uniqueValues) . "\n";
    foreach ($uniqueValues as $value) {
        printf("  %02X (binary: %08b) - VBlank=%d, Sprite0=%d, SpriteOverflow=%d\n",
            $value,
            $value,
            ($value >> 7) & 1,
            ($value >> 6) & 1,
            ($value >> 5) & 1
        );
    }

    echo "\n";
}

traceMemoryReads(__DIR__ . '/roms/donkeykong.nes', 5000);
traceMemoryReads(__DIR__ . '/roms/supermario.nes', 5000);

echo "Done!\n";
