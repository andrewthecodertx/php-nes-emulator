<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function testFrameExecution(string $romPath, string $label, int $frames): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $ppu = $nes->getPPU();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run frames properly
    for ($i = 0; $i < $frames; $i++) {
        $nes->runFrame();
    }

    $instructions = $monitor->getInstructions();

    printf("After %d frames:\n", $frames);
    printf("  Total instructions: %d\n", count($instructions));
    printf("  CPU: PC=\$%04X  A=\$%02X  X=\$%02X  Y=\$%02X\n",
        $cpu->pc, $cpu->accumulator, $cpu->registerX, $cpu->registerY);
    printf("  PPU: Scanline=%d  Cycle=%d  FrameCount=%d\n",
        $ppu->getScanline(), $ppu->getCycle(), $ppu->getFrameCount());

    // Check most executed addresses
    $pcCounts = array_count_values(array_column($instructions, 'pc'));
    arsort($pcCounts);

    echo "  Top 5 most executed addresses:\n";
    $count = 0;
    foreach ($pcCounts as $pc => $execCount) {
        printf("    \$%04X: %d times\n", $pc, $execCount);
        if (++$count >= 5) break;
    }

    // Check for VBlank wait pattern
    $vblankWaitCount = 0;
    foreach ($instructions as $inst) {
        if (($inst['pc'] === 0x800A || $inst['pc'] === 0xC7A8) && $inst['opcode'] === 'LDA') {
            $vblankWaitCount++;
        }
    }
    printf("  VBlank wait iterations: %d\n", $vblankWaitCount);

    echo "\n";
}

echo "Testing frame execution with proper clocking\n";
echo "===========================================\n\n";

testFrameExecution(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (WORKS)', 2);
testFrameExecution(__DIR__ . '/roms/supermario.nes', 'Super Mario (BROKEN)', 2);

echo "Done!\n";
