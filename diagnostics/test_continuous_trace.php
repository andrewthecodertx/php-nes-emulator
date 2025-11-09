<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run all 4 frames continuously without clearing the monitor
for ($f = 1; $f <= 4; $f++) {
    $nes->runFrame();
}

$instructions = $monitor->getInstructions();

// Track JSR/RTS across all frames
$jsrStack = [];
$firstMismatch = null;

foreach ($instructions as $i => $inst) {
    if ($inst['opcode'] === 'JSR') {
        $returnAddr = $inst['pc'] + 3;
        $jsrStack[] = ['jsr_pc' => $inst['pc'], 'return_addr' => $returnAddr];
    } elseif ($inst['opcode'] === 'RTS') {
        if (empty($jsrStack)) {
            if ($firstMismatch === null) {
                $firstMismatch = $i;
                printf("First RTS with empty stack at instruction #%d: \$%04X\n", $i, $inst['pc']);
                
                // Show context
                echo "\n10 instructions before:\n";
                for ($j = max(0, $i - 10); $j < $i; $j++) {
                    $prev = $instructions[$j];
                    printf("  %5d: \$%04X %-3s\n", $j, $prev['pc'], $prev['opcode']);
                }
                
                printf(">>> %5d: \$%04X RTS <<<\n", $i, $inst['pc']);
                
                echo "\n5 instructions after:\n";
                for ($j = $i + 1; $j <= min($i + 5, count($instructions) - 1); $j++) {
                    $next = $instructions[$j];
                    printf("  %5d: \$%04X %-3s\n", $j, $next['pc'], $next['opcode']);
                }
            }
        } else {
            $expectedReturn = array_pop($jsrStack);
            
            if (isset($instructions[$i + 1])) {
                $nextPC = $instructions[$i + 1]['pc'];
                
                if ($nextPC !== $expectedReturn['return_addr']) {
                    if ($firstMismatch === null) {
                        $firstMismatch = $i;
                        printf("\nFirst RTS mismatch at instruction #%d:\n", $i);
                        printf("  RTS at \$%04X returns to \$%04X\n", $inst['pc'], $nextPC);
                        printf("  Expected return to \$%04X (from JSR at \$%04X)\n",
                            $expectedReturn['return_addr'], $expectedReturn['jsr_pc']);
                    }
                }
            }
        }
    }
}

echo "\nDone!\n";
