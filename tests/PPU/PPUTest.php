<?php

declare(strict_types=1);

namespace tests\PPU;

use andrewthecoder\nes\PPU\PPU;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PPU foundation (memory, palette, OAM)
 */
class PPUTest extends TestCase
{
    private PPU $ppu;

    protected function setUp(): void
    {
        $this->ppu = new PPU();
    }

    // ========================================================================
    // Initialization Tests
    // ========================================================================

    public function test_ppu_initializes_with_black_frame_buffer(): void
    {
        $frameBuffer = $this->ppu->getFrameBuffer();

        // Frame buffer should be 256x240 pixels = 61,440 pixels
        $this->assertCount(256 * 240, $frameBuffer);

        // All pixels should be black (0, 0, 0)
        for ($i = 0; $i < 256 * 240; $i++) {
            $this->assertEquals([0, 0, 0], $frameBuffer[$i], "Pixel $i should be black");
        }
    }

    public function test_reset_clears_oam(): void
    {
        // Write some data to OAM
        $this->ppu->writeOAM(0x00, 0xFF);
        $this->ppu->writeOAM(0x10, 0xAA);

        // Reset should clear it
        $this->ppu->reset();

        $this->assertEquals(0x00, $this->ppu->readOAM(0x00));
        $this->assertEquals(0x00, $this->ppu->readOAM(0x10));
    }

    // ========================================================================
    // Palette RAM Tests
    // ========================================================================

    public function test_palette_ram_read_write(): void
    {
        // Write to background palette 0, color 1
        $this->ppu->ppuWrite(0x3F01, 0x12);
        $this->assertEquals(0x12, $this->ppu->ppuRead(0x3F01));

        // Write to sprite palette 2, color 3
        $this->ppu->ppuWrite(0x3F17, 0x2A);
        $this->assertEquals(0x2A, $this->ppu->ppuRead(0x3F17));
    }

    public function test_palette_ram_mirrors_every_32_bytes(): void
    {
        // Write to $3F00
        $this->ppu->ppuWrite(0x3F00, 0x0F);

        // Should be mirrored at $3F20, $3F40, $3F60, etc.
        $this->assertEquals(0x0F, $this->ppu->ppuRead(0x3F20));
        $this->assertEquals(0x0F, $this->ppu->ppuRead(0x3F40));
        $this->assertEquals(0x0F, $this->ppu->ppuRead(0x3F60));
        $this->assertEquals(0x0F, $this->ppu->ppuRead(0x3FE0));
    }

    public function test_palette_transparent_color_mirroring(): void
    {
        // The transparent color at $3F10 should mirror to $3F00
        $this->ppu->ppuWrite(0x3F00, 0x0D);
        $this->assertEquals(0x0D, $this->ppu->ppuRead(0x3F10));

        // Also $3F14 -> $3F04, $3F18 -> $3F08, $3F1C -> $3F0C
        $this->ppu->ppuWrite(0x3F04, 0x12);
        $this->assertEquals(0x12, $this->ppu->ppuRead(0x3F14));

        $this->ppu->ppuWrite(0x3F08, 0x16);
        $this->assertEquals(0x16, $this->ppu->ppuRead(0x3F18));

        $this->ppu->ppuWrite(0x3F0C, 0x1A);
        $this->assertEquals(0x1A, $this->ppu->ppuRead(0x3F1C));
    }

    public function test_palette_transparent_color_writes_mirror_correctly(): void
    {
        // Writing to $3F10 should write to $3F00
        $this->ppu->ppuWrite(0x3F10, 0x20);
        $this->assertEquals(0x20, $this->ppu->ppuRead(0x3F00));
        $this->assertEquals(0x20, $this->ppu->ppuRead(0x3F10));

        // Writing to $3F14 should write to $3F04
        $this->ppu->ppuWrite(0x3F14, 0x21);
        $this->assertEquals(0x21, $this->ppu->ppuRead(0x3F04));
        $this->assertEquals(0x21, $this->ppu->ppuRead(0x3F14));
    }

    public function test_palette_non_transparent_colors_dont_mirror(): void
    {
        // $3F11 should NOT mirror to $3F01
        $this->ppu->ppuWrite(0x3F01, 0x10);
        $this->ppu->ppuWrite(0x3F11, 0x20);

        $this->assertEquals(0x10, $this->ppu->ppuRead(0x3F01));
        $this->assertEquals(0x20, $this->ppu->ppuRead(0x3F11));
    }

    // ========================================================================
    // Nametable Tests
    // ========================================================================

    public function test_nametable_read_write(): void
    {
        // Write to nametable 0
        $this->ppu->ppuWrite(0x2000, 0x42);
        $this->assertEquals(0x42, $this->ppu->ppuRead(0x2000));

        // Write to nametable 1
        $this->ppu->ppuWrite(0x2400, 0x55);
        $this->assertEquals(0x55, $this->ppu->ppuRead(0x2400));
    }

    public function test_nametable_mirrors_at_3000(): void
    {
        // $2000-$2FFF mirrors at $3000-$3EFF
        $this->ppu->ppuWrite(0x2123, 0xAB);
        $this->assertEquals(0xAB, $this->ppu->ppuRead(0x3123));

        $this->ppu->ppuWrite(0x3456, 0xCD);
        $this->assertEquals(0xCD, $this->ppu->ppuRead(0x2456));
    }

    // ========================================================================
    // OAM Tests
    // ========================================================================

    public function test_oam_read_write(): void
    {
        // Write sprite data
        $this->ppu->writeOAM(0x00, 0x50); // Y position
        $this->ppu->writeOAM(0x01, 0x42); // Tile ID
        $this->ppu->writeOAM(0x02, 0x03); // Attributes
        $this->ppu->writeOAM(0x03, 0x80); // X position

        $this->assertEquals(0x50, $this->ppu->readOAM(0x00));
        $this->assertEquals(0x42, $this->ppu->readOAM(0x01));
        $this->assertEquals(0x03, $this->ppu->readOAM(0x02));
        $this->assertEquals(0x80, $this->ppu->readOAM(0x03));
    }

    public function test_oam_address_wraps_at_256(): void
    {
        $this->ppu->writeOAM(0xFF, 0xAA);
        $this->assertEquals(0xAA, $this->ppu->readOAM(0xFF));

        // Address 0x100 should wrap to 0x00
        $this->ppu->writeOAM(0x100, 0xBB);
        $this->assertEquals(0xBB, $this->ppu->readOAM(0x00));
    }

    public function test_oam_address_register(): void
    {
        $this->ppu->setOAMAddress(0x42);
        $this->assertEquals(0x42, $this->ppu->getOAMAddress());

        // Should wrap at 0xFF
        $this->ppu->setOAMAddress(0x1FF);
        $this->assertEquals(0xFF, $this->ppu->getOAMAddress());
    }

    // ========================================================================
    // Hardware Palette Tests
    // ========================================================================

    public function test_hardware_palette_contains_64_colors(): void
    {
        // Test by reading all 64 colors through palette RAM
        for ($i = 0; $i < 64; $i++) {
            $this->ppu->ppuWrite(0x3F00, $i);
            $color = $this->ppu->getColorFromPalette(0, 0);

            // Should return array with 3 elements (RGB)
            $this->assertIsArray($color);
            $this->assertCount(3, $color);

            // All values should be 0-255
            $this->assertGreaterThanOrEqual(0, $color[0]);
            $this->assertLessThanOrEqual(255, $color[0]);
            $this->assertGreaterThanOrEqual(0, $color[1]);
            $this->assertLessThanOrEqual(255, $color[1]);
            $this->assertGreaterThanOrEqual(0, $color[2]);
            $this->assertLessThanOrEqual(255, $color[2]);
        }
    }

    public function test_get_color_from_palette_background(): void
    {
        // Set up background palette 0
        $this->ppu->ppuWrite(0x3F00, 0x0F); // Universal background (black)
        $this->ppu->ppuWrite(0x3F01, 0x30); // Color 1
        $this->ppu->ppuWrite(0x3F02, 0x15); // Color 2
        $this->ppu->ppuWrite(0x3F03, 0x20); // Color 3

        // Get color 1 from palette 0
        $color = $this->ppu->getColorFromPalette(0, 1);
        $this->assertEquals([236, 238, 236], $color); // Color 0x30
    }

    public function test_get_color_from_palette_sprite(): void
    {
        // Sprite palettes are 4-7
        $this->ppu->ppuWrite(0x3F11, 0x16); // Sprite palette 0, color 1

        // Palette index 4 = sprite palette 0
        $color = $this->ppu->getColorFromPalette(4, 1);
        $this->assertEquals([152, 34, 32], $color); // Color 0x16 from hardware palette
    }

    public function test_palette_index_masks_to_6_bits(): void
    {
        // Palette indices should be masked to 0x00-0x3F (6 bits)
        $this->ppu->ppuWrite(0x3F00, 0xFF); // Write 0xFF
        $color = $this->ppu->getColorFromPalette(0, 0);

        // Should read as 0x3F (last valid color) = black in the NES palette
        $this->assertEquals([0, 0, 0], $color); // Color 0x3F
    }

    // ========================================================================
    // Address Space Tests
    // ========================================================================

    public function test_ppu_address_wraps_at_16k(): void
    {
        // PPU address space is 14-bit (0x0000-0x3FFF)
        // Addresses above should wrap
        $this->ppu->ppuWrite(0x3F00, 0xAA);

        // 0x7F00 should wrap to 0x3F00
        $this->assertEquals(0xAA, $this->ppu->ppuRead(0x7F00));

        // 0xBF00 should wrap to 0x3F00
        $this->assertEquals(0xAA, $this->ppu->ppuRead(0xBF00));
    }

    public function test_data_values_mask_to_8_bits(): void
    {
        // Writing values > 0xFF should wrap
        $this->ppu->ppuWrite(0x3F00, 0x1FF);
        $this->assertEquals(0xFF, $this->ppu->ppuRead(0x3F00));

        $this->ppu->writeOAM(0x00, 0x3AB);
        $this->assertEquals(0xAB, $this->ppu->readOAM(0x00));
    }
}
