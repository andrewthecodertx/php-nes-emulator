<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== NES Mapper Implementation Test ===\n\n";

$roms = [
    'donkeykong.nes' => ['name' => 'Donkey Kong', 'mapper' => 0, 'desc' => 'NROM'],
    'supermario.nes' => ['name' => 'Super Mario Bros', 'mapper' => 0, 'desc' => 'NROM'],
    'nestest.nes' => ['name' => 'NESTest', 'mapper' => 0, 'desc' => 'NROM'],
    'tetris.nes' => ['name' => 'Tetris', 'mapper' => 1, 'desc' => 'MMC1'],
    'joust.nes' => ['name' => 'Joust', 'mapper' => 3, 'desc' => 'CNROM'],
];

foreach ($roms as $romFile => $info) {
    $path = __DIR__ . '/../../roms/' . $romFile;

    if (!file_exists($path)) {
        echo "⊘ {$info['name']} - File not found\n";
        continue;
    }

    try {
        $nes = NES::fromROM($path);

        // Run a few frames to verify it works
        for ($i = 0; $i < 5; $i++) {
            $nes->runFrame();
        }

        $ppu = $nes->getPPU();
        $frame = $ppu->getFrameBuffer();

        // Count non-black pixels
        $nonBlack = 0;
        foreach ($frame as $pixel) {
            if ($pixel[0] !== 0 || $pixel[1] !== 0 || $pixel[2] !== 0) {
                $nonBlack++;
            }
        }

        $mapperInfo = "Mapper {$info['mapper']} ({$info['desc']})";
        printf("✓ %-25s %s - %d non-black pixels\n", $info['name'], $mapperInfo, $nonBlack);

    } catch (Exception $e) {
        echo "✗ {$info['name']} - Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Implemented mappers:\n";
echo "  • Mapper 0 (NROM) - Simplest, no bank switching\n";
echo "  • Mapper 1 (MMC1) - ~28% of games, PRG/CHR bank switching\n";
echo "  • Mapper 2 (UxROM) - PRG bank switching, CHR-RAM\n";
echo "  • Mapper 3 (CNROM) - CHR-ROM bank switching\n";
echo "  • Mapper 4 (MMC3) - ~22% of games, advanced features with IRQ\n";
echo "\nThese 5 mappers cover ~60-70% of all NES games!\n";
