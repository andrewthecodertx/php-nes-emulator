<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function checkMemoryAccesses(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run 2 frames
    for ($frame = 0; $frame < 2; $frame++) {
        $nes->runFrame();
    }

    $accesses = $monitor->getMemoryAccesses();

    // Count writes to PPU registers
    $ppuWrites = [
        0x2000 => 0, // PPUCTRL
        0x2001 => 0, // PPUMASK
        0x2003 => 0, // OAMADDR
        0x2004 => 0, // OAMDATA
        0x2005 => 0, // PPUSCROLL
        0x2006 => 0, // PPUADDR
        0x2007 => 0, // PPUDATA
    ];

    $ppuReads = [
        0x2002 => 0, // PPUSTATUS
        0x2007 => 0, // PPUDATA
    ];

    foreach ($accesses as $access) {
        $addr = $access['address'];
        if ($access['type'] === 'write' && isset($ppuWrites[$addr])) {
            $ppuWrites[$addr]++;
        }
        if ($access['type'] === 'read' && isset($ppuReads[$addr])) {
            $ppuReads[$addr]++;
        }
    }

    printf("  PPU Register Writes (first 2 frames):\n");
    foreach ($ppuWrites as $addr => $count) {
        $name = match($addr) {
            0x2000 => 'PPUCTRL',
            0x2001 => 'PPUMASK',
            0x2003 => 'OAMADDR',
            0x2004 => 'OAMDATA',
            0x2005 => 'PPUSCROLL',
            0x2006 => 'PPUADDR',
            0x2007 => 'PPUDATA',
        };
        printf("    $%04X (%s): %d\n", $addr, $name, $count);
    }

    printf("\n  PPU Register Reads (first 2 frames):\n");
    foreach ($ppuReads as $addr => $count) {
        $name = match($addr) {
            0x2002 => 'PPUSTATUS',
            0x2007 => 'PPUDATA',
        };
        printf("    $%04X (%s): %d\n", $addr, $name, $count);
    }

    echo "\n";
}

checkMemoryAccesses(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (WORKS)');
checkMemoryAccesses(__DIR__ . '/roms/supermario.nes', 'Super Mario (BROKEN)');

echo "Done!\n";
