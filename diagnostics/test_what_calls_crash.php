<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Code around \$8049 (where RTS returns to):\n\n";

for ($addr = 0x8040; $addr <= 0x8060; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("\$%04X: %02X", $addr, $byte);
    if ($addr === 0x8049) echo " <-- RTS returns here";
    if ($addr === 0x8057) echo " <-- Infinite loop crash handler";
    echo "\n";
}

echo "\nDisassembly:\n";
echo "\$8040: 40          RTI\n";
echo "\$8041: A9 06       LDA #\$06\n";
echo "\$8043: 8D 01 20    STA \$2001 (PPUMASK)\n";
echo "\$8046: 20 20 82    JSR \$8220\n";
echo "\$8049: 20 19 8E    JSR \$8E19  <-- RTS returns here\n";
echo "\$804C: EE 74 07    INC \$0774\n";
echo "\$804F: AD 78 07    LDA \$0778\n";
echo "\$8052: 09 80       ORA #\$80\n";
echo "\$8054: 20 ED 8E    JSR \$8EED\n";
echo "\$8057: 4C 57 80    JMP \$8057  <-- Crash handler\n";

echo "\nSo the code flow is:\n";
echo "1. Some function calls a subroutine\n";
echo "2. That subroutine eventually reaches \$8230 (RTS)\n";
echo "3. RTS returns to \$8049\n";
echo "4. Executes JSR \$8E19\n";
echo "5. Then the normal flow continues...\n";
echo "6. Eventually reaches \$8057 crash handler\n\n";

echo "The question is: WHY is \$8049 on the stack?\n";
echo "Who pushed \$8048 (which RTS adds 1 to, making \$8049)?\n\n";

echo "Let me check the NMI vector:\n";
$nmiLow = $mapper->cpuRead(0xFFFA);
$nmiHigh = $mapper->cpuRead(0xFFFB);
$nmiVector = $nmiLow | ($nmiHigh << 8);
printf("NMI vector: \$%04X\n", $nmiVector);

echo "\nIf NMI points near \$8040, that might explain it!\n";

echo "\nDone!\n";
