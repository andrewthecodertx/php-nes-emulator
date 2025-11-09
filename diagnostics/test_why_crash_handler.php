<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

// Let's look at a larger context around $8049
echo "Code from $8040 to $8060:\n\n";

for ($addr = 0x8040; $addr <= 0x8060; $addr += 16) {
    printf("$%04X:", $addr);
    for ($i = 0; $i < 16; $i++) {
        printf(" %02X", $mapper->cpuRead($addr + $i));
    }
    echo "\n";
}

echo "\nLet me disassemble this properly:\n\n";

// Around $8049 JSR
echo "$8049: 20 19 8E    JSR \$8E19\n";
echo "$804C: EE 74 07    INC \$0774\n";
echo "$804F: AD 78 07    LDA \$0778\n";
echo "$8052: 09 80       ORA #\$80\n";
echo "$8054: 20 ED 8E    JSR \$8EED\n";
echo "$8057: 4C 57 80    JMP \$8057  <- crash handler\n";
echo "$805A: (unreachable code)\n";

echo "\nThe code at $8054 does JSR \$8EED which never returns normally.\n";
echo "Let me check what \$8EED does:\n\n";

for ($addr = 0x8EED; $addr <= 0x8EFF; $addr++) {
    printf("$%04X: %02X\n", $addr, $mapper->cpuRead($addr));
}

echo "\nDisassembly of \$8EED:\n";
echo "$8EED: 8D ?? ??    STA ...\n";
echo "$8EF0: 8D ?? ??    STA ...\n";
echo "$8EF3: 60         RTS\n";

echo "\n\$8EED does two STAs and returns. This should return to \$8057.\n";
echo "\nThe issue: \$8057 is a crash handler loop!\n";
echo "This means the code at \$8054-\$8057 is SUPPOSED to crash after calling \$8EED.\n";
echo "\nWhy would the game intentionally call a function that crashes?\n";
echo "Answer: The JSR at \$8054 might be conditional or part of error handling.\n";
echo "\nLet me check what calls the code at \$8049-\$8054:\n\n";

// Check NMI/IRQ vectors
echo "Interrupt vectors:\n";
printf("NMI:   \$%02X%02X\n", $mapper->cpuRead(0xFFFB), $mapper->cpuRead(0xFFFA));
printf("RESET: \$%02X%02X\n", $mapper->cpuRead(0xFFFD), $mapper->cpuRead(0xFFFC));
printf("IRQ:   \$%02X%02X\n", $mapper->cpuRead(0xFFFF), $mapper->cpuRead(0xFFFE));

echo "\nDone!\n";
