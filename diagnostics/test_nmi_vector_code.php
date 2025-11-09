<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Code at NMI vector (\$8082):\n\n";

for ($addr = 0x8082; $addr <= 0x8095; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("\$%04X: %02X\n", $addr, $byte);
}

echo "\nFirst bytes: AD 78 07 = LDA \$0778\n";
echo "This is the real NMI handler entry point.\n\n";

echo "Now let's look at the code around \$8040:\n\n";

for ($addr = 0x8040; $addr <= 0x805A; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("\$%04X: %02X\n", $addr, $byte);
}

echo "\nThe RTI at \$8040 is likely part of a DIFFERENT interrupt handler (maybe IRQ?).\n";
echo "The code at \$8041-\$8057 might be unreachable dead code or a separate function.\n\n";

echo "Let me check the IRQ vector:\n";
$irqLow = $mapper->cpuRead(0xFFFE);
$irqHigh = $mapper->cpuRead(0xFFFF);
$irqVector = $irqLow | ($irqHigh << 8);
printf("IRQ vector: \$%04X\n\n", $irqVector);

echo "So the question remains: why does the game execute code at \$8049?\n";
echo "Answer: Because an RTS is returning to \$8049!\n";
echo "That RTS is at \$8230, and the stack has \$8048.\n\n";

echo "Who put \$8048 on the stack?\n";
echo "A JSR at \$8046 would push PC+2 = \$8049, which RTS subtracts 1 from, giving \$8048.\n";
echo "NO WAIT - JSR pushes PC-1, and RTS adds 1.\n";
echo "Actually, let me check the 6502 docs again...\n\n";

echo "6502 JSR behavior:\n";
echo "- JSR is 3 bytes: 20 LL HH\n";
echo "- When executed, PC points to the LOW byte of address\n";
echo "- JSR pushes (PC+1) to stack (points to HIGH byte)\n";
echo "- RTS pulls from stack and adds 1, giving PC+2 (next instruction)\n\n";

echo "So if JSR is at \$8046:\n";
echo "- Opcode fetched, PC becomes \$8047 (low byte)\n";
echo "- JSR pushes \$8047+1 = \$8048\n";
echo "- RTS pulls \$8048, adds 1, gives \$8049 ✓ CORRECT!\n\n";

echo "So JSR \$8220 at \$8046 is responsible.\n";
echo "And \$8046 is in that mystery code block at \$8041-\$8057.\n\n";

echo "The real question is: how does execution reach \$8046?\n";

echo "\nDone!\n";
