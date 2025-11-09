<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();
$bus = $nes->getBus();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes->runFrame();
}

// Frame 4 - trace JSR/RTS pairs
$monitor->clearLog();
$nes->runFrame();

$instructions = $monitor->getInstructions();

// Track JSR/RTS to find mismatched pairs
$jsrStack = [];
$issues = [];

foreach ($instructions as $i => $inst) {
    if ($inst['opcode'] === 'JSR') {
        // JSR pushes PC+2 (return address)
        $returnAddr = $inst['pc'] + 3;
        $jsrStack[] = ['jsr_pc' => $inst['pc'], 'return_addr' => $returnAddr, 'index' => $i];
    } elseif ($inst['opcode'] === 'RTS') {
        if (empty($jsrStack)) {
            $issues[] = sprintf("RTS at \$%04X (instruction #%d) with empty JSR stack!", 
                $inst['pc'], $i);
        } else {
            $expectedReturn = array_pop($jsrStack);
            
            // Check the next instruction after RTS
            if (isset($instructions[$i + 1])) {
                $nextPC = $instructions[$i + 1]['pc'];
                
                if ($nextPC !== $expectedReturn['return_addr']) {
                    $issues[] = sprintf(
                        "RTS at \$%04X returns to \$%04X, expected \$%04X (from JSR at \$%04X)",
                        $inst['pc'],
                        $nextPC,
                        $expectedReturn['return_addr'],
                        $expectedReturn['jsr_pc']
                    );
                }
            }
        }
    }
}

if (empty($issues)) {
    echo "All JSR/RTS pairs match correctly!\n";
} else {
    echo "Found JSR/RTS mismatches:\n\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
}

echo "\nDone!\n";
