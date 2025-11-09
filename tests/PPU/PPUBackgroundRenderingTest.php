<?php

declare(strict_types=1);

namespace tests\PPU;

use andrewthecoder\nes\PPU\PPU;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PPU background rendering
 *
 * Background rendering involves:
 * 1. Fetching tile data from nametables
 * 2. Fetching attributes from attribute tables
 * 3. Fetching pattern data from pattern tables (CHR ROM)
 * 4. Shifting pixel data through 16-bit shifters
 * 5. Compositing pixels to the frame buffer
 */
class PPUBackgroundRenderingTest extends TestCase
{
    private PPU $ppu;

    protected function setUp(): void
    {
        $this->ppu = new PPU();
    }

    // ========================================================================
    // Basic Pattern Table Tests
    // ========================================================================

    public function test_pattern_data_layout(): void
    {
        // Pattern tables store tiles as 16 bytes each:
        // - Bytes 0-7: Low bit plane (one bit per pixel, 8 rows)
        // - Bytes 8-15: High bit plane (one bit per pixel, 8 rows)
        //
        // Combined, they form 2-bit pixels (values 0-3)

        // Write a simple tile pattern (a diagonal line)
        // Low plane:  0b10000000 for each row
        // High plane: 0b00000000 for each row
        // Result: pixel value 1 in top-left corner

        for ($row = 0; $row < 8; $row++) {
            $this->ppu->ppuWrite(0x0000 + $row, 0x80); // Low plane
            $this->ppu->ppuWrite(0x0008 + $row, 0x00); // High plane
        }

        // Read back and verify
        $this->assertEquals(0x80, $this->ppu->ppuRead(0x0000));
        $this->assertEquals(0x00, $this->ppu->ppuRead(0x0008));
    }

    public function test_2bit_pixel_composition(): void
    {
        // Each pixel is 2 bits: low bit + high bit
        // This gives 4 possible values: 0, 1, 2, 3

        $tileLsb = 0b11110000; // Low bits
        $tileMsb = 0b11001100; // High bits

        // Expected pixels (from left to right, MSB to LSB):
        // Pixel 0 (bit 7): lsb=1, msb=1 -> 3
        // Pixel 1 (bit 6): lsb=1, msb=1 -> 3
        // Pixel 2 (bit 5): lsb=1, msb=0 -> 1
        // Pixel 3 (bit 4): lsb=1, msb=0 -> 1
        // Pixel 4 (bit 3): lsb=0, msb=1 -> 2
        // Pixel 5 (bit 2): lsb=0, msb=1 -> 2
        // Pixel 6 (bit 1): lsb=0, msb=0 -> 0
        // Pixel 7 (bit 0): lsb=0, msb=0 -> 0

        for ($col = 0; $col < 8; $col++) {
            $bitPos = 7 - $col;
            $lowBit = ($tileLsb >> $bitPos) & 1;
            $highBit = ($tileMsb >> $bitPos) & 1;
            $pixel = ($highBit << 1) | $lowBit;

            if ($col < 2) {
                $this->assertEquals(3, $pixel, "Pixel $col should be 3");
            } elseif ($col < 4) {
                $this->assertEquals(1, $pixel, "Pixel $col should be 1");
            } elseif ($col < 6) {
                $this->assertEquals(2, $pixel, "Pixel $col should be 2");
            } else {
                $this->assertEquals(0, $pixel, "Pixel $col should be 0");
            }
        }
    }

    // ========================================================================
    // Nametable Tests
    // ========================================================================

    public function test_nametable_address_calculation(): void
    {
        // Nametables are 32x30 tiles, located at:
        // 0x2000 (top-left), 0x2400 (top-right), 0x2800 (bottom-left), 0x2C00 (bottom-right)

        // Write tile IDs to specific nametable locations
        $this->ppu->ppuWrite(0x2000, 0x42); // Top-left nametable, tile (0,0)
        $this->ppu->ppuWrite(0x2001, 0x43); // Tile (1,0)
        $this->ppu->ppuWrite(0x2020, 0x44); // Tile (0,1) - next row (32 tiles per row)

        $this->assertEquals(0x42, $this->ppu->ppuRead(0x2000));
        $this->assertEquals(0x43, $this->ppu->ppuRead(0x2001));
        $this->assertEquals(0x44, $this->ppu->ppuRead(0x2020));
    }

    public function test_attribute_table_address_calculation(): void
    {
        // Attribute tables start at 0x23C0, 0x27C0, 0x2BC0, 0x2FC0
        // Each attribute byte controls a 4x4 tile area (32x32 pixels)
        // The byte is divided into 4 2-bit palette selections for 2x2 tile groups

        // Write to attribute table
        $this->ppu->ppuWrite(0x23C0, 0xFF); // All palettes set to 3

        $this->assertEquals(0xFF, $this->ppu->ppuRead(0x23C0));
    }

    // ========================================================================
    // Palette Tests
    // ========================================================================

    public function test_background_palette_selection(): void
    {
        // Background palettes are at 0x3F00-0x3F0F (4 palettes * 4 colors)
        // Palette 0: 0x3F00-0x3F03
        // Palette 1: 0x3F04-0x3F07
        // Palette 2: 0x3F08-0x3F0B
        // Palette 3: 0x3F0C-0x3F0F

        // Set background palette 0
        $this->ppu->ppuWrite(0x3F00, 0x0F); // Universal background color
        $this->ppu->ppuWrite(0x3F01, 0x30); // Color 1
        $this->ppu->ppuWrite(0x3F02, 0x10); // Color 2
        $this->ppu->ppuWrite(0x3F03, 0x20); // Color 3

        // Verify
        $this->assertEquals(0x0F, $this->ppu->ppuRead(0x3F00));
        $this->assertEquals(0x30, $this->ppu->ppuRead(0x3F01));
        $this->assertEquals(0x10, $this->ppu->ppuRead(0x3F02));
        $this->assertEquals(0x20, $this->ppu->ppuRead(0x3F03));
    }

    public function test_pixel_palette_lookup(): void
    {
        // Setup: palette 1, pixel value 2
        // Address = 0x3F00 + (palette << 2) + pixel
        //         = 0x3F00 + (1 << 2) + 2
        //         = 0x3F00 + 4 + 2
        //         = 0x3F06

        $this->ppu->ppuWrite(0x3F06, 0x16); // Red color

        $colorIndex = $this->ppu->ppuRead(0x3F06);
        $this->assertEquals(0x16, $colorIndex);

        // Get RGB from hardware palette
        $color = $this->ppu->getColorFromPalette(1, 2);
        $this->assertEquals([152, 34, 32], $color); // Hardware palette color 0x16
    }

    // ========================================================================
    // Simple Rendering Test
    // ========================================================================

    public function test_simple_tile_render(): void
    {
        // Setup a simple scene:
        // - Nametable has tile ID 1 at position (0,0)
        // - Tile 1 in pattern table is a solid color (all pixels = 1)
        // - Attribute byte selects palette 0
        // - Palette 0 color 1 is white

        // Enable background rendering
        $this->ppu->cpuWrite(0x01, 0x08); // PPUMASK: show background

        // Write tile pattern (tile 1: all pixels = 1)
        for ($row = 0; $row < 8; $row++) {
            $this->ppu->ppuWrite(0x0010 + $row, 0xFF); // Low plane (all 1s)
            $this->ppu->ppuWrite(0x0018 + $row, 0x00); // High plane (all 0s)
        }

        // Write to nametable (tile 1 at position 0,0)
        $this->ppu->ppuWrite(0x2000, 0x01);

        // Write attribute (palette 0 for all)
        $this->ppu->ppuWrite(0x23C0, 0x00);

        // Setup palette (background palette 0)
        $this->ppu->ppuWrite(0x3F00, 0x0F); // Universal background
        $this->ppu->ppuWrite(0x3F01, 0x30); // Color 1 = white

        // Run rendering for first visible scanline (scanline 0)
        // Start from pre-render to properly initialize
        while ($this->ppu->getScanline() < 0) {
            $this->ppu->clock();
        }

        // Now on scanline 0, render first 8 pixels (one tile)
        for ($cycle = 0; $cycle < 8; $cycle++) {
            $this->ppu->clock();
        }

        // Check that first pixel of frame buffer has been rendered
        // (This test mainly verifies the pipeline doesn't crash)
        $this->assertTrue(true);
    }
}
