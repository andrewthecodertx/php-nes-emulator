<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

/**
 * Compare detailed execution of Donkey Kong vs Super Mario
 * to find where initialization diverges
 */

function traceGameDetailed(string $romPath, string $label): array
{
    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $ppu = $nes->getPPU();
    $bus = $nes->getBus();

    // Attach monitor
    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run 5 frames
    for ($i = 0; $i < 5; $i++) {
        $nes->runFrame();
    }

    // Get statistics
    $instructions = $monitor->getInstructions();

    // Count VBlank reads
    $vblankWaitCount = 0;
    foreach ($instructions as $inst) {
        // Common VBlank wait patterns: LDA $2002, BPL/BMI
        if ($inst['pc'] === 0x800A || $inst['pc'] === 0xC7A8) {
            $vblankWaitCount++;
        }
    }

    // Check nametable writes
    $nametableWrites = 0;
    // We can't easily track writes through the monitor, so let's check final nametable state

    // Read nametable through PPUDATA
    $bus->write(0x2006, 0x20); // High byte
    $bus->write(0x2006, 0x00); // Low byte
    $bus->read(0x2007); // Dummy read

    $nonZeroNametable = 0;
    $nametableValues = [];
    for ($i = 0; $i < 256; $i++) {
        $value = $bus->read(0x2007);
        if ($value !== 0x00) {
            $nonZeroNametable++;
        }
        $nametableValues[] = $value;
    }

    // Check if nametable is all one value
    $uniqueValues = array_unique($nametableValues);

    // Check palette
    $paletteNonZero = 0;
    for ($addr = 0x3F00; $addr < 0x3F20; $addr++) {
        $bus->write(0x2006, ($addr >> 8) & 0xFF);
        $bus->write(0x2006, $addr & 0xFF);
        $bus->read(0x2007); // Dummy
        $value = $bus->read(0x2007);
        if ($value !== 0x00) {
            $paletteNonZero++;
        }
    }

    return [
        'instructions' => count($instructions),
        'vblank_waits' => $vblankWaitCount,
        'nametable_nonzero' => $nonZeroNametable,
        'nametable_unique' => count($uniqueValues),
        'nametable_sample' => array_slice($nametableValues, 0, 32),
        'palette_nonzero' => $paletteNonZero,
    ];
}

echo "Comparing game initialization after 5 frames\n";
echo "=============================================\n\n";

$dkStats = traceGameDetailed(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
$smStats = traceGameDetailed(__DIR__ . '/roms/supermario.nes', 'Super Mario');

echo "Donkey Kong (WORKS):\n";
printf("  Instructions executed: %d\n", $dkStats['instructions']);
printf("  VBlank wait loops: %d\n", $dkStats['vblank_waits']);
printf("  Nametable non-zero bytes: %d\n", $dkStats['nametable_nonzero']);
printf("  Nametable unique values: %d\n", $dkStats['nametable_unique']);
echo "  First 32 nametable bytes: " . implode(' ', array_map(fn($v) => sprintf('%02X', $v), $dkStats['nametable_sample'])) . "\n";
printf("  Palette non-zero entries: %d\n", $dkStats['palette_nonzero']);

echo "\nSuper Mario Bros (BROKEN):\n";
printf("  Instructions executed: %d\n", $smStats['instructions']);
printf("  VBlank wait loops: %d\n", $smStats['vblank_waits']);
printf("  Nametable non-zero bytes: %d\n", $smStats['nametable_nonzero']);
printf("  Nametable unique values: %d\n", $smStats['nametable_unique']);
echo "  First 32 nametable bytes: " . implode(' ', array_map(fn($v) => sprintf('%02X', $v), $smStats['nametable_sample'])) . "\n";
printf("  Palette non-zero entries: %d\n", $smStats['palette_nonzero']);

echo "\nDone!\n";
