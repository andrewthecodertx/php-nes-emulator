<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\Cartridge\Cartridge;

$games = [
    'donkeykong.nes' => 'WORKS',
    'joust.nes' => 'WORKS',
    'arkanoid.nes' => 'WORKS NOW',
    'supermario.nes' => 'BROKEN',
    'tetris.nes' => 'BROKEN',
];

echo "Game Status by Mapper:\n\n";

foreach ($games as $rom => $status) {
    $cartridge = Cartridge::fromFile(__DIR__ . "/roms/$rom");
    printf("%-20s Mapper %d  %3dKB PRG  %s\n",
        basename($rom, '.nes'),
        $cartridge->getMapperNumber(),
        $cartridge->getPrgRomSize() / 1024,
        $status
    );
}

echo "\nPattern:\n";
echo "- Mapper 0 (NROM):  DonkeyKong 16KB ✓, SuperMario 32KB ✗\n";
echo "- Mapper 1 (MMC1):  Tetris 32KB ✗\n";
echo "- Mapper 3 (CNROM): Joust 16KB ✓, Arkanoid 32KB ✓\n\n";

echo "Interesting! Mapper 3 works with both 16KB and 32KB.\n";
echo "But Mapper 0 only works with 16KB, and Mapper 1 (32KB) is broken.\n";
echo "This suggests the bug is mapper-specific, not size-specific!\n";

echo "\nDone!\n";
