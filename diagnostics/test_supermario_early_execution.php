<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();
$bus = $nes->getBus();
$ppu = $nes->getPPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run with proper frame synchronization for 2 frames
for ($frame = 0; $frame < 2; $frame++) {
    $nes->runFrame();
}

$instructions = $monitor->getInstructions();

echo "Super Mario - First 2 frames\n";
echo "Total instructions: " . count($instructions) . "\n\n";

// Find any writes to $2006 (PPUADDR) or $2007 (PPUDATA)
$ppuaddrWrites = 0;
$ppudataWrites = 0;

foreach ($instructions as $inst) {
    if ($inst['opcode'] === 'STA') {
        // Check if storing to $2006 or $2007
        // Need to examine operand bytes
        if (isset($inst['operand1'], $inst['operand2'])) {
            $addr = $inst['operand1'] | ($inst['operand2'] << 8);
            if ($addr === 0x2006) {
                $ppuaddrWrites++;
            } elseif ($addr === 0x2007) {
                $ppudataWrites++;
            }
        }
    }
}

printf("PPUADDR ($2006) writes: %d\n", $ppuaddrWrites);
printf("PPUDATA ($2007) writes: %d\n", $ppudataWrites);

// Check how many times we read from PPUSTATUS
$ppustatusReads = 0;
foreach ($instructions as $inst) {
    if ($inst['opcode'] === 'LDA' && isset($inst['operand1'], $inst['operand2'])) {
        $addr = $inst['operand1'] | ($inst['operand2'] << 8);
        if ($addr === 0x2002) {
            $ppustatusReads++;
        }
    }
}

printf("PPUSTATUS ($2002) reads: %d\n", $ppustatusReads);

// Show PC coverage
$pcAddresses = array_column($instructions, 'pc');
$uniquePCs = array_unique($pcAddresses);
printf("\nUnique PC addresses executed: %d\n", count($uniquePCs));
printf("Address range: $%04X - $%04X\n", min($uniquePCs), max($uniquePCs));

echo "\nDone!\n";
