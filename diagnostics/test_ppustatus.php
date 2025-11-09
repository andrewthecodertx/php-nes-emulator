<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Test PPUSTATUS behavior by manually checking the flag
 * during the early frames
 */

function testPPUStatus(string $romPath, int $instructions): void
{
    echo "=== Testing $romPath ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $ppu = $nes->getPPU();
    $bus = $nes->getBus();

    // Track PPUSTATUS reads
    $ppuStatusValues = [];
    $instructionCount = 0;

    for ($i = 0; $i < $instructions; $i++) {
        // Before executing instruction, check current PPUSTATUS value
        $statusValue = $bus->read(0x2002);
        $ppuStatusValues[] = [
            'instruction' => $i,
            'pc' => $cpu->pc,
            'status' => $statusValue,
            'vblank' => ($statusValue >> 7) & 1,
            'ppu_scanline' => $ppu->getScanline(),
            'ppu_cycle' => $ppu->getCycle(),
        ];

        // Execute one CPU instruction
        $cpu->step();
        $instructionCount++;

        // Stop if frame complete
        if ($ppu->isFrameComplete()) {
            break;
        }
    }

    echo "Executed $instructionCount instructions\n";
    echo "Frame complete: " . ($ppu->isFrameComplete() ? "YES" : "NO") . "\n";
    echo "Final PPU state: scanline={$ppu->getScanline()}, cycle={$ppu->getCycle()}\n";

    // Analyze PPUSTATUS reads
    $uniqueValues = array_unique(array_column($ppuStatusValues, 'status'));
    echo "Unique PPUSTATUS values seen: " . count($uniqueValues) . "\n";
    foreach ($uniqueValues as $value) {
        printf("  %02X (binary: %08b) - VBlank bit: %d\n",
            $value,
            $value,
            ($value >> 7) & 1
        );
    }

    // Show first time VBlank bit was set
    foreach ($ppuStatusValues as $entry) {
        if ($entry['vblank'] === 1) {
            echo "\nFirst VBlank bit set:\n";
            printf("  Instruction: %d\n", $entry['instruction']);
            printf("  PC: %04X\n", $entry['pc']);
            printf("  PPUSTATUS: %02X\n", $entry['status']);
            printf("  PPU: scanline=%d, cycle=%d\n", $entry['ppu_scanline'], $entry['ppu_cycle']);
            break;
        }
    }

    // Show when PPU reaches scanline 241 (VBlank scanline)
    $foundScanline241 = false;
    foreach ($ppuStatusValues as $entry) {
        if ($entry['ppu_scanline'] === 241 && !$foundScanline241) {
            echo "\nWhen PPU reached scanline 241:\n";
            printf("  Instruction: %d\n", $entry['instruction']);
            printf("  PC: %04X\n", $entry['pc']);
            printf("  PPUSTATUS: %02X (VBlank bit: %d)\n", $entry['status'], $entry['vblank']);
            printf("  PPU: scanline=%d, cycle=%d\n", $entry['ppu_scanline'], $entry['ppu_cycle']);
            $foundScanline241 = true;
            break;
        }
    }

    echo "\n";
}

testPPUStatus(__DIR__ . '/roms/donkeykong.nes', 10000);
testPPUStatus(__DIR__ . '/roms/supermario.nes', 10000);

echo "Done!\n";
