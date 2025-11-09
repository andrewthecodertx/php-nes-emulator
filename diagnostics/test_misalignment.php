<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();
$mapper = $nes->getMapper();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes->runFrame();
}

// Run frame 4 and look for any execution in the $8050-$8057 region
$monitor->clearLog();
$nes->runFrame();

$instructions = $monitor->getInstructions();

echo "All instructions in $8048-$8058 region during frame 4:\n\n";

$found = [];
foreach ($instructions as $i => $inst) {
    $pc = $inst['pc'];
    if ($pc >= 0x8048 && $pc <= 0x8058) {
        if (!in_array($pc, $found)) {
            $found[] = $pc;
            
            // Show previous instruction
            if ($i > 0) {
                $prev = $instructions[$i - 1];
                printf("Instruction before: $%04X %-3s\n", $prev['pc'], $prev['opcode']);
            }
            
            printf(">>> $%04X: %02X %-3s <<< %s\n", 
                $pc, $inst['instruction'], $inst['opcode'],
                ($pc === 0x8050 || $pc === 0x8057) ? "**MISALIGNED**" : "");
            
            // Show next few for context
            for ($j = 1; $j <= 3 && ($i + $j) < count($instructions); $j++) {
                $next = $instructions[$i + $j];
                printf("            $%04X %-3s\n", $next['pc'], $next['opcode']);
            }
            echo "\n";
        }
    }
}

// Check correct disassembly
echo "\nCorrect disassembly starting at $804C:\n";
$addr = 0x804C;
echo sprintf("$%04X: EE 74 07    INC \$0774\n", $addr);
echo sprintf("$%04X: AD 78 07    LDA \$0778\n", 0x804F);
echo sprintf("$%04X: 09 80       ORA #\$80\n", 0x8052);
echo sprintf("$%04X: 20 ED 8E    JSR \$8EED\n", 0x8054);
echo sprintf("$%04X: 4C 57 80    JMP \$8057 (infinite loop - should never reach here)\n", 0x8057);

echo "\nMisaligned disassembly if execution starts at $8050:\n";
echo sprintf("$%04X: 78          SEI\n", 0x8050);
echo sprintf("$%04X: 07 09       SLO \$09 (illegal)\n", 0x8051);
echo sprintf("$%04X: 80          NOP (illegal)\n", 0x8053);
echo sprintf("$%04X: 20 ED 8E    JSR \$8EED\n", 0x8054);
echo sprintf("$%04X: 4C 57 80    JMP \$8057\n", 0x8057);

echo "\nDone!\n";
