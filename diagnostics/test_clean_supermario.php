<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "Testing Super Mario WITHOUT manual palette writes\n";
echo "==================================================\n\n";

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$bus = $nes->getBus();

// Run 10 frames WITHOUT any manual interference
for ($i = 0; $i < 10; $i++) {
    $nes->runFrame();
}

// Read palette
$bus->write(0x2006, 0x3F);
$bus->write(0x2006, 0x00);
$bus->read(0x2007); // Dummy

echo "Palette after 10 clean frames:\n  ";
$nonZero = 0;
for ($i = 0; $i < 32; $i++) {
    $value = $bus->read(0x2007);
    if ($value !== 0x00) $nonZero++;
    printf("%02X ", $value);
    if (($i + 1) % 16 === 0) echo "\n  ";
}
printf("\nNon-zero palette entries: %d\n", $nonZero);

echo "\nDone!\n";
