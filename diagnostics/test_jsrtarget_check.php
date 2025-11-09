<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Checking the JSR target address calculation:\n\n";

// JSR is at $8054
$jsrAddr = 0x8054;
$lowByte = $mapper->cpuRead($jsrAddr + 1);
$highByte = $mapper->cpuRead($jsrAddr + 2);

$target = $lowByte | ($highByte << 8);

printf("JSR at \$%04X\n", $jsrAddr);
printf("  Operand bytes: \$%02X \$%02X\n", $lowByte, $highByte);
printf("  Target address: \$%04X\n", $target);
printf("  Return address would be: \$%04X\n", $jsrAddr + 3);

echo "\nLet me also check the previous JSR:\n";

$jsrAddr2 = 0x8049;
$lowByte2 = $mapper->cpuRead($jsrAddr2 + 1);
$highByte2 = $mapper->cpuRead($jsrAddr2 + 2);
$target2 = $lowByte2 | ($highByte2 << 8);

printf("\nJSR at \$%04X\n", $jsrAddr2);
printf("  Operand bytes: \$%02X \$%02X\n", $lowByte2, $highByte2);
printf("  Target address: \$%04X\n", $target2);
printf("  Return address would be: \$%04X\n", $jsrAddr2 + 3);

echo "\nBoth look correct. The issue is that \$8057 contains a crash loop.\n";
echo "This suggests the game developers put it there intentionally as an\n";
echo "error handler for 'should never get here' situations.\n\n";

echo "The real question: WHY is this code path being executed in frame 4?\n";
echo "Let me check what calls \$8049:\n\n";

use andrewthecoder\MOS6502\CPUMonitor;

$nes2 = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes2->getCPU();
$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run 3 frames
for ($f = 1; $f <= 3; $f++) {
    $nes2->runFrame();
}

// Frame 4
$monitor->clearLog();
$nes2->runFrame();

$instructions = $monitor->getInstructions();

// Find all executions of $8049
foreach ($instructions as $i => $inst) {
    if ($inst['pc'] === 0x8049) {
        echo "Found \$8049 execution:\n";
        
        // Show previous 5 instructions
        for ($j = max(0, $i - 5); $j < $i; $j++) {
            $prev = $instructions[$j];
            printf("  \$%04X: %-3s\n", $prev['pc'], $prev['opcode']);
        }
        printf(">>> \$%04X: %-3s <<<\n", $inst['pc'], $inst['opcode']);
        break;
    }
}

echo "\nDone!\n";
