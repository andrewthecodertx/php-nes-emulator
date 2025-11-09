<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\PPU\PPU;

echo "=== Testing Palette RAM Writes for Super Mario ===\n\n";

// Create a logging PPU that tracks palette writes
class LoggingPPU extends PPU
{
    public array $paletteWrites = [];
    public array $ppuAddrWrites = [];

    protected function ppuWrite(int $address, int $data): void
    {
        if ($address >= 0x3F00 && $address <= 0x3F1F) {
            $this->paletteWrites[] = [
                'frame' => $this->getFrameCount(),
                'scanline' => $this->getScanline(),
                'cycle' => $this->getCycle(),
                'address' => $address,
                'data' => $data
            ];
        }
        parent::ppuWrite($address, $data);
    }

    public function cpuWrite(int $address, int $data): void
    {
        if ($address === 0x06) { // PPUADDR
            $this->ppuAddrWrites[] = [
                'frame' => $this->getFrameCount(),
                'data' => $data
            ];
        }
        parent::cpuWrite($address, $data);
    }

    public function getFrameCount(): int
    {
        $refl = new ReflectionClass(parent::class);
        $prop = $refl->getProperty('frameCount');
        $prop->setAccessible(true);
        return $prop->getValue($this);
    }
}

$romPath = __DIR__ . '/../../roms/supermario.nes';

if (!file_exists($romPath)) {
    echo "Error: supermario.nes not found\n";
    exit(1);
}

$nes = NES::fromROM($romPath);

// Replace PPU with logging version
$bus = $nes->getBus();
$busRefl = new ReflectionClass($bus);
$ppuProp = $busRefl->getProperty('ppu');
$ppuProp->setAccessible(true);

$loggingPPU = new LoggingPPU($nes->getCartridge()->getMapper(), $nes->getCartridge()->getMirroring());
$ppuProp->setValue($bus, $loggingPPU);

echo "Running 120 initialization frames...\n";
for ($i = 0; $i < 120; $i++) {
    $nes->runFrame();
}

echo "Frames completed: 120\n\n";

echo "PPUADDR Writes: " . count($loggingPPU->ppuAddrWrites) . "\n";
if (count($loggingPPU->ppuAddrWrites) > 0) {
    echo "First 20 PPUADDR writes:\n";
    foreach (array_slice($loggingPPU->ppuAddrWrites, 0, 20) as $write) {
        printf("  Frame %3d: \$%02X\n", $write['frame'], $write['data']);
    }
    echo "\n";

    // Look for palette address writes ($3F)
    $paletteAddrWrites = array_filter($loggingPPU->ppuAddrWrites, fn($w) => $w['data'] === 0x3F);
    echo "PPUADDR writes with value \$3F (palette high byte): " . count($paletteAddrWrites) . "\n";
    if (count($paletteAddrWrites) > 0) {
        echo "First 10:\n";
        foreach (array_slice($paletteAddrWrites, 0, 10) as $write) {
            printf("  Frame %3d: \$3F\n", $write['frame']);
        }
    }
}

echo "\nPalette RAM Writes: " . count($loggingPPU->paletteWrites) . "\n";
if (count($loggingPPU->paletteWrites) > 0) {
    echo "SUCCESS! Game IS writing to palette RAM:\n";
    foreach (array_slice($loggingPPU->paletteWrites, 0, 20) as $write) {
        printf("  Frame %3d, Scanline %3d, Cycle %3d: \$%04X = \$%02X\n",
            $write['frame'], $write['scanline'], $write['cycle'],
            $write['address'], $write['data']);
    }
} else {
    echo "❌ PROBLEM: Game is NOT writing to palette RAM!\n";
    echo "This means either:\n";
    echo "  1. Game code isn't reaching palette write routines\n";
    echo "  2. PPUADDR isn't being set to \$3F00-\$3F1F range\n";
    echo "  3. CPU/PPU communication is broken\n";
}

echo "\n";
