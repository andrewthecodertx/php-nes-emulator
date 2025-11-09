<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\Cartridge\Cartridge;

echo "ROM Information\n";
echo "===============\n\n";

$roms = glob(__DIR__ . '/roms/*.nes');

foreach ($roms as $romPath) {
    $name = basename($romPath, '.nes');
    $cartridge = Cartridge::fromFile($romPath);

    printf("%-20s Mapper: %d  PRG: %3dKB  CHR: %3dKB  Mirror: %s\n",
        $name,
        $cartridge->getMapperNumber(),
        $cartridge->getPrgRomSize() / 1024,
        $cartridge->getChrRomSize() / 1024,
        $cartridge->isVerticalMirroring() ? 'V' : 'H'
    );
}

echo "\n";
