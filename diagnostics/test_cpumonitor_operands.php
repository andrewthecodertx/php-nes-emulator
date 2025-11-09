<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 20 instructions
for ($i = 0; $i < 20; $i++) {
    $cpu->step();
}

$instructions = $monitor->getInstructions();

echo "First 20 instructions with operands:\n\n";

foreach ($instructions as $i => $inst) {
    printf("%2d: PC=$%04X  op=$%02X  %-3s", 
        $i, $inst['pc'], $inst['instruction'], $inst['opcode']);

    if (isset($inst['operand1'])) {
        printf("  operand1=$%02X", $inst['operand1']);
    }
    if (isset($inst['operand2'])) {
        printf("  operand2=$%02X", $inst['operand2']);
    }

    // If this is LDA absolute, show the full address
    if ($inst['opcode'] === 'LDA' && isset($inst['operand1'], $inst['operand2'])) {
        $addr = $inst['operand1'] | ($inst['operand2'] << 8);
        printf("  [reads from $%04X]", $addr);
    }

    echo "\n";
}

echo "\nDone!\n";
