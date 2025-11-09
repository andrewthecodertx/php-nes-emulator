<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function checkAfterResetStep(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    printf("  After reset(): PC=$%04X\n", $cpu->pc);

    // Step once to process the reset
    $cpu->step();
    printf("  After step():  PC=$%04X\n", $cpu->pc);

    echo "\n";
}

checkAfterResetStep(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB - WORKS)');
checkAfterResetStep(__DIR__ . '/roms/supermario.nes', 'Super Mario (32KB - BROKEN)');
checkAfterResetStep(__DIR__ . '/roms/joust.nes', 'Joust (16KB - WORKS)');
checkAfterResetStep(__DIR__ . '/roms/tetris.nes', 'Tetris (32KB - BROKEN)');

echo "Done!\n";
