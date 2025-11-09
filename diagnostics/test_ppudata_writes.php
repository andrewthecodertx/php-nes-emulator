<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

// Monkey-patch to intercept PPUDATA writes
class DebugNES extends NES {
    private array $ppuDataWrites = [];

    public function getPPUDataWrites(): array {
        return $this->ppuDataWrites;
    }

    public function runFrameWithLogging(): void {
        // Store original ppuWrite
        $ppu = $this->getPPU();
        $reflection = new ReflectionClass($ppu);

        // Can't easily intercept without modifying PPU class
        // So let's just check palette state before/after each frame

        parent::runFrame();
    }
}

// Test both games
function testPPUDataWrites(string $romPath, string $label): void {
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $bus = $nes->getBus();
    $ppu = $nes->getPPU();

    // Track VRAM address writes
    $vramAddressWrites = [];
    $ppuDataWrites = [];

    // Run 10 frames
    for ($frame = 0; $frame < 10; $frame++) {
        $nes->runFrame();
    }

    // Check final palette state
    $bus->write(0x2006, 0x3F);
    $bus->write(0x2006, 0x00);
    $bus->read(0x2007); // Dummy

    $nonZero = 0;
    for ($i = 0; $i < 32; $i++) {
        $val = $bus->read(0x2007);
        if ($val !== 0) $nonZero++;
    }

    printf("After 10 frames: %d non-zero palette entries\n", $nonZero);

    // The problem is we can't easily intercept writes without modifying the PPU class
    // Let me try a different approach - check if PPUADDR is being set to palette range

    echo "\n";
}

testPPUDataWrites(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
testPPUDataWrites(__DIR__ . '/roms/supermario.nes', 'Super Mario');

echo "Done!\n";
