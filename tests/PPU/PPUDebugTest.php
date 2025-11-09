<?php

declare(strict_types=1);

namespace tests\PPU;

use andrewthecoder\nes\PPU\PPU;
use PHPUnit\Framework\TestCase;

/**
 * Debug test to trace rendering issues
 */
class PPUDebugTest extends TestCase
{
    public function test_simple_two_pixel_pattern(): void
    {
        $ppu = new PPU();

        // Enable background rendering
        $ppu->cpuWrite(0x01, 0x08); // PPUMASK: show background

        // Create a simple 2-pixel pattern in tile 0
        // Row 0: 11000000 (first two pixels are 1, rest are 0)
        $ppu->ppuWrite(0x0000, 0xC0); // 11000000 binary
        $ppu->ppuWrite(0x0008, 0x00); // High plane all 0s

        // All other rows also 11000000
        for ($row = 1; $row < 8; $row++) {
            $ppu->ppuWrite(0x0000 + $row, 0xC0);
            $ppu->ppuWrite(0x0008 + $row, 0x00);
        }

        // Write tile 0 to nametable position (0,0)
        $ppu->ppuWrite(0x2000, 0x00);

        // Set attribute (palette 0)
        $ppu->ppuWrite(0x23C0, 0x00);

        // Setup background palette 0
        $ppu->ppuWrite(0x3F00, 0x00); // Color 0 (hardware color 0x00 = dark gray [84,84,84])
        $ppu->ppuWrite(0x3F01, 0x30); // Color 1 (hardware color 0x30 = white [236,238,236])

        // Render one complete frame
        while (!$ppu->isFrameComplete()) {
            $ppu->clock();
        }

        $frameBuffer = $ppu->getFrameBuffer();

        // First two pixels should be white (pixel value 1)
        $white = [236, 238, 236]; // Hardware palette 0x30
        $this->assertEquals($white, $frameBuffer[0], "Pixel 0 should be white");
        $this->assertEquals($white, $frameBuffer[1], "Pixel 1 should be white");

        // Remaining pixels in the row should be dark gray (pixel value 0)
        $gray = [84, 84, 84]; // Hardware palette 0x00
        $this->assertEquals($gray, $frameBuffer[2], "Pixel 2 should be gray");
        $this->assertEquals($gray, $frameBuffer[3], "Pixel 3 should be gray");
    }
}
