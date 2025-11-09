<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function testVBlankWaitLoop(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $ppu = $nes->getPPU();
    $bus = $nes->getBus();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run 100 instructions
    for ($i = 0; $i < 100; $i++) {
        $cpu->step();
    }

    $instructions = $monitor->getInstructions();

    echo "First 20 instructions:\n";
    for ($i = 0; $i < 20 && $i < count($instructions); $i++) {
        $inst = $instructions[$i];
        printf("  %3d: PC=$%04X  op=$%02X  %-3s", $i, $inst['pc'], $inst['instruction'], $inst['opcode']);
        
        // If this is a LDA, show what address is being read
        if ($inst['opcode'] === 'LDA' && isset($inst['operand1'], $inst['operand2'])) {
            $addr = $inst['operand1'] | ($inst['operand2'] << 8);
            printf("  [$%04X]", $addr);
        }
        // If BPL or BEQ, show the branch target
        if (($inst['opcode'] === 'BPL' || $inst['opcode'] === 'BEQ') && isset($inst['operand1'])) {
            $offset = $inst['operand1'];
            if ($offset >= 128) $offset -= 256;  // Sign extend
            $target = ($inst['pc'] + 2 + $offset) & 0xFFFF;
            printf("  -> $%04X", $target);
        }

        echo "\n";
    }

    echo "\n";
}

testVBlankWaitLoop(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
testVBlankWaitLoop(__DIR__ . '/roms/supermario.nes', 'Super Mario');

echo "Done!\n";
