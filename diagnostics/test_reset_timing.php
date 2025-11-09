<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Check when reset actually takes effect
 */

function testResetTiming(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $bus = $nes->getBus();

    printf("After NES::fromROM() - CPU PC: %04X\n", $cpu->pc);

    // Manually call reset() again
    $cpu->reset();
    printf("After cpu->reset() - CPU PC: %04X\n", $cpu->pc);

    // Execute one clock cycle
    $cpu->step();
    printf("After 1 CPU step - CPU PC: %04X\n", $cpu->pc);

    // Run a few more steps
    for ($i = 0; $i < 10; $i++) {
        $cpu->step();
    }
    printf("After 11 CPU steps - CPU PC: %04X\n", $cpu->pc);

    echo "\n";
}

testResetTiming(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
testResetTiming(__DIR__ . '/roms/supermario.nes', 'Super Mario Bros');

echo "Done!\n";
