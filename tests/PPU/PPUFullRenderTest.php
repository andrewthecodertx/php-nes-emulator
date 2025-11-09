<?php

declare(strict_types=1);

namespace tests\PPU;

use andrewthecoder\nes\PPU\PPU;
use PHPUnit\Framework\TestCase;

/**
 * Full end-to-end rendering tests
 *
 * These tests verify that the complete rendering pipeline produces
 * correct output in the frame buffer
 */
class PPUFullRenderTest extends TestCase
{
    private PPU $ppu;

    protected function setUp(): void
    {
        $this->ppu = new PPU();
    }

    /**
     * Test rendering a single solid-color tile
     */
    public function test_render_single_solid_tile(): void
    {
        // Enable background rendering
        $this->ppu->cpuWrite(0x01, 0x08); // PPUMASK: show background

        // Create a solid tile pattern (tile 0: all pixels = 1)
        for ($row = 0; $row < 8; $row++) {
            $this->ppu->ppuWrite(0x0000 + $row, 0xFF); // Low plane (all 1s)
            $this->ppu->ppuWrite(0x0008 + $row, 0x00); // High plane (all 0s)
        }
        // Result: all pixels = 1 (low=1, high=0)

        // Write tile 0 to nametable position (0,0)
        $this->ppu->ppuWrite(0x2000, 0x00);

        // Set attribute (palette 0 for all tiles)
        $this->ppu->ppuWrite(0x23C0, 0x00);

        // Setup background palette 0
        $this->ppu->ppuWrite(0x3F00, 0x00); // Universal background (hardware 0x00 = dark gray)
        $this->ppu->ppuWrite(0x3F01, 0x30); // Color 1 (white)
        $this->ppu->ppuWrite(0x3F02, 0x10); // Color 2 (light gray)
        $this->ppu->ppuWrite(0x3F03, 0x20); // Color 3 (gray)

        // Render one complete frame
        while (!$this->ppu->isFrameComplete()) {
            $this->ppu->clock();
        }

        // Check first 8x8 tile in frame buffer
        // All pixels should be white (color 0x30 from palette)
        $expectedColor = [236, 238, 236]; // Hardware palette color 0x30

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $frameBuffer = $this->ppu->getFrameBuffer();
                $pixelIndex = $y * 256 + $x;
                $actualColor = $frameBuffer[$pixelIndex];

                $this->assertEquals(
                    $expectedColor,
                    $actualColor,
                    "Pixel at ($x, $y) should be white"
                );
            }
        }
    }

    /**
     * Test rendering a checkerboard pattern tile
     */
    public function test_render_checkerboard_tile(): void
    {
        // Enable background rendering
        $this->ppu->cpuWrite(0x01, 0x08); // PPUMASK: show background

        // Create a checkerboard pattern (tile 1)
        // Pattern: 10101010 alternating per row
        for ($row = 0; $row < 8; $row++) {
            if ($row % 2 === 0) {
                $this->ppu->ppuWrite(0x0010 + $row, 0xAA); // 10101010
            } else {
                $this->ppu->ppuWrite(0x0010 + $row, 0x55); // 01010101
            }
            $this->ppu->ppuWrite(0x0018 + $row, 0x00); // High plane all 0s
        }

        // Write tile 1 to nametable position (0,0)
        $this->ppu->ppuWrite(0x2000, 0x01);

        // Set attribute (palette 0)
        $this->ppu->ppuWrite(0x23C0, 0x00);

        // Setup background palette 0
        $this->ppu->ppuWrite(0x3F00, 0x00); // Color 0 (background)
        $this->ppu->ppuWrite(0x3F01, 0x30); // Color 1 (white)

        // Render one complete frame
        while (!$this->ppu->isFrameComplete()) {
            $this->ppu->clock();
        }

        // Check checkerboard pattern
        $bgColor = [84, 84, 84];        // Color 0x0F (dark gray)
        $fgColor = [236, 238, 236];     // Color 0x30 (white)

        $frameBuffer = $this->ppu->getFrameBuffer();

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $pixelIndex = $y * 256 + $x;
                $actualColor = $frameBuffer[$pixelIndex];

                // Determine expected color based on checkerboard
                if ($y % 2 === 0) {
                    // Even rows: 1,0,1,0,1,0,1,0
                    $expectedColor = ($x % 2 === 0) ? $fgColor : $bgColor;
                } else {
                    // Odd rows: 0,1,0,1,0,1,0,1
                    $expectedColor = ($x % 2 === 0) ? $bgColor : $fgColor;
                }

                $this->assertEquals(
                    $expectedColor,
                    $actualColor,
                    "Pixel at ($x, $y) should match checkerboard pattern"
                );
            }
        }
    }

    /**
     * Test multiple tiles in a row
     */
    public function test_render_multiple_tiles_horizontal(): void
    {
        // Enable background rendering
        $this->ppu->cpuWrite(0x01, 0x08); // PPUMASK: show background

        // Create tile 0: solid color 1
        for ($row = 0; $row < 8; $row++) {
            $this->ppu->ppuWrite(0x0000 + $row, 0xFF);
            $this->ppu->ppuWrite(0x0008 + $row, 0x00);
        }

        // Create tile 1: solid color 2
        for ($row = 0; $row < 8; $row++) {
            $this->ppu->ppuWrite(0x0010 + $row, 0x00);
            $this->ppu->ppuWrite(0x0018 + $row, 0xFF);
        }

        // Write tiles to nametable: tile 0, then tile 1
        $this->ppu->ppuWrite(0x2000, 0x00); // Position (0,0)
        $this->ppu->ppuWrite(0x2001, 0x01); // Position (1,0)

        // Set attributes (palette 0 for both)
        $this->ppu->ppuWrite(0x23C0, 0x00);

        // Setup palette
        $this->ppu->ppuWrite(0x3F00, 0x00); // Color 0
        $this->ppu->ppuWrite(0x3F01, 0x16); // Color 1 (red)
        $this->ppu->ppuWrite(0x3F02, 0x2A); // Color 2 (green)

        // Render one complete frame
        while (!$this->ppu->isFrameComplete()) {
            $this->ppu->clock();
        }

        $frameBuffer = $this->ppu->getFrameBuffer();
        $redColor = [152, 34, 32];      // Hardware palette 0x16
        $greenColor = [76, 208, 32];    // Hardware palette 0x2A

        // Check first tile (8x8) should be red
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $pixelIndex = $y * 256 + $x;
                $this->assertEquals($redColor, $frameBuffer[$pixelIndex], "First tile pixel at ($x, $y)");
            }
        }

        // Check second tile (8x8) should be green
        for ($y = 0; $y < 8; $y++) {
            for ($x = 8; $x < 16; $x++) {
                $pixelIndex = $y * 256 + $x;
                $this->assertEquals($greenColor, $frameBuffer[$pixelIndex], "Second tile pixel at ($x, $y)");
            }
        }
    }

    /**
     * Test rendering with different palettes (attribute table)
     */
    public function test_render_with_palette_selection(): void
    {
        // Enable background rendering
        $this->ppu->cpuWrite(0x01, 0x08); // PPUMASK: show background

        // Create tile 0: solid color 1
        for ($row = 0; $row < 8; $row++) {
            $this->ppu->ppuWrite(0x0000 + $row, 0xFF);
            $this->ppu->ppuWrite(0x0008 + $row, 0x00);
        }

        // Write tile 0 to positions (0,0) and (2,0)
        $this->ppu->ppuWrite(0x2000, 0x00); // Position (0,0) - will use palette 0
        $this->ppu->ppuWrite(0x2002, 0x00); // Position (2,0) - will use palette 1

        // Set attribute byte
        // Each attribute byte controls a 4x4 tile area
        // Format: BR(76) BL(54) TR(32) TL(10)
        // We want top-left to use palette 0, top-right to use palette 1
        $this->ppu->ppuWrite(0x23C0, 0x04); // TL=00 (palette 0), TR=01 (palette 1)

        // Setup palette 0 (red)
        $this->ppu->ppuWrite(0x3F00, 0x00);
        $this->ppu->ppuWrite(0x3F01, 0x16); // Red

        // Setup palette 1 (blue)
        $this->ppu->ppuWrite(0x3F04, 0x00);
        $this->ppu->ppuWrite(0x3F05, 0x12); // Blue

        // Render one complete frame
        while (!$this->ppu->isFrameComplete()) {
            $this->ppu->clock();
        }

        $frameBuffer = $this->ppu->getFrameBuffer();
        $redColor = [152, 34, 32];   // Hardware palette 0x16
        $blueColor = [48, 50, 236];  // Hardware palette 0x12

        // First tile should be red (palette 0)
        $this->assertEquals($redColor, $frameBuffer[0], "First tile uses palette 0 (red)");

        // Third tile should be blue (palette 1)
        $this->assertEquals($blueColor, $frameBuffer[16], "Third tile uses palette 1 (blue)");
    }

    /**
     * Test that rendering disabled produces black screen
     */
    public function test_rendering_disabled_produces_black(): void
    {
        // Don't enable rendering (PPUMASK = 0)

        // Setup some tile data (shouldn't be rendered)
        for ($row = 0; $row < 8; $row++) {
            $this->ppu->ppuWrite(0x0000 + $row, 0xFF);
            $this->ppu->ppuWrite(0x0008 + $row, 0xFF);
        }
        $this->ppu->ppuWrite(0x2000, 0x00);
        $this->ppu->ppuWrite(0x3F01, 0x30);

        // Render one complete frame
        while (!$this->ppu->isFrameComplete()) {
            $this->ppu->clock();
        }

        // Frame buffer should show universal background color (palette 0, pixel 0)
        // When palette RAM is not initialized, it reads as 0x00, which maps to hardware color 0x00
        $frameBuffer = $this->ppu->getFrameBuffer();
        $bgColor = [84, 84, 84]; // Hardware palette 0x00 (dark gray)

        for ($i = 0; $i < 100; $i++) { // Check first 100 pixels
            $this->assertEquals($bgColor, $frameBuffer[$i], "Pixel $i should show background color");
        }
    }
}
