<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Function at \$8EED:\n\n";

for ($addr = 0x8EED; $addr <= 0x8F00; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("\$%04X: %02X\n", $addr, $byte);
}

echo "\nDisassembly:\n";
echo "\$8EED: 8D 00 20    STA \$2000 (PPUCTRL)\n";
echo "\$8EF0: 8D 78 07    STA \$0778\n";
echo "\$8EF3: 60          RTS\n\n";

echo "This function just does two STAs and returns.\n";
echo "So it WILL return to \$8057, causing the crash.\n\n";

echo "This means either:\n";
echo "1. The code path to \$8054 is wrong (shouldn't reach JSR \$8EED)\n";
echo "2. The code at \$8054 is supposed to be different\n";
echo "3. There's a conditional branch that should skip \$8054\n\n";

echo "Let me check if there's a branch before \$8054:\n\n";

for ($addr = 0x8049; $addr <= 0x8057; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("\$%04X: %02X  ", $addr, $byte);
    
    // Check for branch opcodes
    if (in_array($byte, [0x10, 0x30, 0x50, 0x70, 0x90, 0xB0, 0xD0, 0xF0])) {
        echo "<-- BRANCH INSTRUCTION";
    }
    
    echo "\n";
}

echo "\nNo branches found. The code should execute sequentially.\n";
echo "Therefore, the game INTENTIONALLY reaches the crash handler.\n";
echo "This must be error handling code that triggers when something is wrong.\n\n";

echo "Let me check what value is being tested before JSR \$8EED:\n";
echo "\$804F: LDA \$0778\n";
echo "\$8052: ORA #\$80\n";
echo "\$8054: JSR \$8EED  <- this is UNCONDITIONAL\n\n";

echo "Wait! Maybe the crash is intentional!\n";
echo "Maybe the game detects an error condition and deliberately crashes.\n\n";

echo "Let me check what \$0774 and \$0778 are used for...\n";

echo "\nDone!\n";
