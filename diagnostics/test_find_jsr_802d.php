<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Searching for JSR that would push \$802D (returning to \$802E)...\n\n";

echo "JSR at address X pushes (X+2) when PC points to low byte after opcode fetch.\n";
echo "For return address \$802E:\n";
echo "- RTS pulls value and adds 1, so stack has \$802D\n";
echo "- JSR pushed \$802D when PC was at \$802C (operand high byte)\n";
echo "- So JSR opcode is at \$802B\n\n";

echo "Wait, that doesn't sound right. Let me recalculate using the actual formula:\n";
echo "- JSR at \$XXXX has PC = \$XXXX after opcode fetch\n";
echo "- Then PC++, so PC = \$XXXX+1 (low byte of address)\n";
echo "- JSR pushes (PC+1) = \$XXXX+2 (high byte of address)\n";
echo "- RTS pulls \$XXXX+2, adds 1, gives \$XXXX+3\n\n";

echo "For RTS to return to \$802E:\n";
echo "- RTS adds 1 to pulled value, so pulled value is \$802D\n";
echo "- JSR pushed \$802D = (PC+1)\n";
echo "- So PC was \$802C when JSR pushed\n";
echo "- PC = \$802C means opcode was fetched from \$802B\n";
echo "- So JSR is at \$802B\n\n";

echo "Let me check \$802B:\n";

for ($addr = 0x8028; $addr <= 0x8032; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("\$%04X: %02X", $addr, $byte);
    if ($addr === 0x802B) echo " <-- Should be JSR (20)";
    echo "\n";
}

$byte802B = $mapper->cpuRead(0x802B);
if ($byte802B === 0x20) {
    $targetLow = $mapper->cpuRead(0x802C);
    $targetHigh = $mapper->cpuRead(0x802D);
    $target = $targetLow | ($targetHigh << 8);
    printf("\nJSR at \$802B calls \$%04X\n", $target);
    printf("This JSR would return to \$802E ✓\n");
} else {
    printf("\nByte at \$802B is \$%02X, not JSR!\n", $byte802B);
    echo "My calculation must be wrong.\n";
}

echo "\nDone!\n";
