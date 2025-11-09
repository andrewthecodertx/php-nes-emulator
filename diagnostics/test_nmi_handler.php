<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "NMI Handler code:\n\n";

for ($addr = 0x8082; $addr <= 0x80A0; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("\$%04X: %02X\n", $addr, $byte);
}

echo "\nDisassembly guess:\n";
echo "\$8082: 20 ?? ??    JSR ...\n";
echo "This JSR should eventually return to \$8085\n";
echo "Then continue to RTI at \$8040\n\n";

echo "But our trace shows RTS returning to \$8049 instead.\n";
echo "This means the return address \$8048 is on the stack.\n";
echo "\$8048 bytes are: 82 20\n";
echo "Wait... that's not right. Let me recalculate.\n\n";

echo "If RTS returns to \$8049, it means the stack had \$8048.\n";
echo "JSR pushes (PC+2), so if JSR is at X, it pushes (X+2).\n";
echo "For stack to have \$8048, JSR must be at \$8046.\n\n";

echo "Looking at \$8046: 20 20 82 = JSR \$8220\n";
echo "This JSR is at \$8046 in the NMI handler code at \$8041-\$8057!\n\n";

echo "So the NMI handler itself is calling JSR \$8220!\n";
echo "And when that returns (via RTS at \$8230), it returns to \$8049.\n";
echo "Then it continues executing the rest of the NMI handler.\n";
echo "Eventually it should reach RTI at \$8040 to exit the NMI.\n\n";

echo "But instead, after \$8049, the code reaches the crash handler at \$8057.\n";
echo "This means the code from \$8049-\$8056 is executing and NOT jumping back to RTI.\n";

echo "\nDone!\n";
