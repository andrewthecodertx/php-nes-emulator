<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 10 frames
for ($frame = 0; $frame < 10; $frame++) {
    $nes->runFrame();
}

$instructions = $monitor->getInstructions();

echo "Super Mario - Instructions outside $8000-$9FFF:\n\n";

foreach ($instructions as $i => $inst) {
    $pc = $inst['pc'];
    
    // Show instructions outside the $8000-$9FFF range
    if ($pc < 0x8000 || $pc >= 0xA000) {
        printf("Instruction #%d: PC=$%04X  opcode=$%02X  %-3s\n",
            $i, $pc, $inst['instruction'], $inst['opcode']);
        
        // Show a few instructions before and after for context
        if ($i > 0 && $i < count($instructions) - 1) {
            $prev = $instructions[$i - 1];
            $next = $instructions[$i + 1];
            printf("  Before: PC=$%04X  %-3s\n", $prev['pc'], $prev['opcode']);
            printf("  After:  PC=$%04X  %-3s\n", $next['pc'], $next['opcode']);
        }
        echo "\n";
    }
}

echo "Done!\n";
