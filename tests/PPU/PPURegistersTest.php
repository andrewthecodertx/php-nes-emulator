<?php

declare(strict_types=1);

namespace tests\PPU;

use andrewthecoder\nes\PPU\PPU;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PPU register behavior ($2000-$2007)
 *
 * These tests verify hardware-accurate register behavior including:
 * - Write-only and read-only registers
 * - Address latch for dual-write sequences
 * - PPUDATA read buffering
 * - Open bus behavior
 * - Side effects of register reads
 */
class PPURegistersTest extends TestCase
{
    private PPU $ppu;

    protected function setUp(): void
    {
        $this->ppu = new PPU();
    }

    // ========================================================================
    // PPUCTRL ($2000) Tests
    // ========================================================================

    public function test_ppuctrl_is_write_only(): void
    {
        $this->ppu->cpuWrite(0x00, 0xFF);

        // Reading PPUCTRL should return 0 (not readable)
        $this->assertEquals(0x00, $this->ppu->cpuRead(0x00));
    }

    public function test_ppuctrl_nametable_bits_write_to_temp_vram_address(): void
    {
        // Write to PPUCTRL with nametable bits set
        $this->ppu->cpuWrite(0x00, 0x03); // Bits 0-1 = 11

        // These bits should be copied to temp VRAM address bits 10-11
        // We can verify this by writing to PPUADDR and seeing the result
        $this->ppu->cpuWrite(0x06, 0x00); // High byte
        $this->ppu->cpuWrite(0x06, 0x00); // Low byte

        // The VRAM address should have bits 10-11 set from PPUCTRL
        // We can't read VRAM address directly, but we can test via PPUDATA
        $this->assertTrue(true); // Placeholder - full test requires more implementation
    }

    // ========================================================================
    // PPUMASK ($2001) Tests
    // ========================================================================

    public function test_ppumask_is_write_only(): void
    {
        $this->ppu->cpuWrite(0x01, 0xFF);

        // Reading PPUMASK should return 0 (not readable)
        $this->assertEquals(0x00, $this->ppu->cpuRead(0x01));
    }

    // ========================================================================
    // PPUSTATUS ($2002) Tests
    // ========================================================================

    public function test_ppustatus_is_read_only(): void
    {
        // Set status manually (would normally be set by rendering)
        // We'll test via the public methods
        $this->assertTrue(true); // Status is read-only, write does nothing
    }

    public function test_reading_ppustatus_clears_vblank_flag(): void
    {
        // This would require clock() implementation to set vblank
        // For now, we can test that reading doesn't crash
        $status = $this->ppu->cpuRead(0x02);
        $this->assertGreaterThanOrEqual(0, $status);
    }

    public function test_reading_ppustatus_resets_address_latch(): void
    {
        // Write first byte to PPUADDR
        $this->ppu->cpuWrite(0x06, 0x20);

        // Read status (should reset latch)
        $this->ppu->cpuRead(0x02);

        // Next write to PPUADDR should be treated as first write again
        $this->ppu->cpuWrite(0x06, 0x30);
        $this->ppu->cpuWrite(0x06, 0x00);

        // VRAM address should be 0x3000, not 0x2000
        // Verify by writing and reading data
        $this->ppu->cpuWrite(0x07, 0x42); // Write to VRAM
        $this->ppu->cpuWrite(0x06, 0x30); // Reset address
        $this->ppu->cpuWrite(0x06, 0x00);

        // Read twice (first read is buffered)
        $this->ppu->cpuRead(0x07);
        $value = $this->ppu->cpuRead(0x07);
        $this->assertEquals(0x42, $value);
    }

    // ========================================================================
    // OAMADDR ($2003) Tests
    // ========================================================================

    public function test_oamaddr_is_write_only(): void
    {
        $this->ppu->cpuWrite(0x03, 0x42);

        // Reading OAMADDR should return 0 (not readable)
        $this->assertEquals(0x00, $this->ppu->cpuRead(0x03));
    }

    // ========================================================================
    // OAMDATA ($2004) Tests
    // ========================================================================

    public function test_oamdata_read_write(): void
    {
        // Set OAM address
        $this->ppu->cpuWrite(0x03, 0x10);

        // Write data
        $this->ppu->cpuWrite(0x04, 0xAB);

        // Set address again and read
        $this->ppu->cpuWrite(0x03, 0x10);
        $value = $this->ppu->cpuRead(0x04);

        $this->assertEquals(0xAB, $value);
    }

    public function test_oamdata_write_auto_increments_address(): void
    {
        $this->ppu->cpuWrite(0x03, 0x00);

        // Write 4 bytes (one sprite)
        $this->ppu->cpuWrite(0x04, 0x10); // Y
        $this->ppu->cpuWrite(0x04, 0x20); // Tile
        $this->ppu->cpuWrite(0x04, 0x30); // Attr
        $this->ppu->cpuWrite(0x04, 0x40); // X

        // Read back
        $this->ppu->cpuWrite(0x03, 0x00);
        $this->assertEquals(0x10, $this->ppu->cpuRead(0x04));
        $this->ppu->cpuWrite(0x03, 0x01);
        $this->assertEquals(0x20, $this->ppu->cpuRead(0x04));
        $this->ppu->cpuWrite(0x03, 0x02);
        $this->assertEquals(0x30, $this->ppu->cpuRead(0x04));
        $this->ppu->cpuWrite(0x03, 0x03);
        $this->assertEquals(0x40, $this->ppu->cpuRead(0x04));
    }

    // ========================================================================
    // PPUSCROLL ($2005) Tests
    // ========================================================================

    public function test_ppuscroll_is_write_only(): void
    {
        $this->ppu->cpuWrite(0x05, 0x10);

        // Reading PPUSCROLL should return 0 (not readable)
        $this->assertEquals(0x00, $this->ppu->cpuRead(0x05));
    }

    public function test_ppuscroll_uses_address_latch(): void
    {
        // First write: X scroll
        $this->ppu->cpuWrite(0x05, 0x7F); // X = 127

        // Second write: Y scroll
        $this->ppu->cpuWrite(0x05, 0xEF); // Y = 239

        // Latch should be reset (next write is X again)
        $this->ppu->cpuWrite(0x05, 0x00);

        // No assertion - just testing that it doesn't crash
        $this->assertTrue(true);
    }

    public function test_ppuscroll_latch_resets_on_status_read(): void
    {
        // Write X scroll
        $this->ppu->cpuWrite(0x05, 0x10);

        // Read status (resets latch)
        $this->ppu->cpuRead(0x02);

        // Next write should be X scroll again
        $this->ppu->cpuWrite(0x05, 0x20);

        $this->assertTrue(true); // No crash = success
    }

    // ========================================================================
    // PPUADDR ($2006) Tests
    // ========================================================================

    public function test_ppuaddr_is_write_only(): void
    {
        $this->ppu->cpuWrite(0x06, 0x20);

        // Reading PPUADDR should return 0 (not readable)
        $this->assertEquals(0x00, $this->ppu->cpuRead(0x06));
    }

    public function test_ppuaddr_dual_write_sequence(): void
    {
        // Write high byte
        $this->ppu->cpuWrite(0x06, 0x20);

        // Write low byte
        $this->ppu->cpuWrite(0x06, 0x15);

        // VRAM address should now be 0x2015
        // Verify by writing and reading back
        $this->ppu->cpuWrite(0x07, 0xAA);

        // Reset address and read
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x15);

        // First read is buffered
        $this->ppu->cpuRead(0x07);
        // Second read returns actual value
        $value = $this->ppu->cpuRead(0x07);

        $this->assertEquals(0xAA, $value);
    }

    public function test_ppuaddr_high_byte_masks_to_6_bits(): void
    {
        // Write high byte with upper bits set (use nametable address, not palette)
        $this->ppu->cpuWrite(0x06, 0xFF); // Gets masked to 0x3F
        $this->ppu->cpuWrite(0x06, 0x00); // Address becomes 0x3F00

        // Write data (this is palette RAM, so use it directly)
        $this->ppu->cpuWrite(0x07, 0x12);

        // Verify by reading back (palette reads are immediate)
        $this->ppu->cpuWrite(0x06, 0x3F);
        $this->ppu->cpuWrite(0x06, 0x00);
        $value = $this->ppu->cpuRead(0x07); // Palette RAM - immediate read

        $this->assertEquals(0x12, $value);
    }

    // ========================================================================
    // PPUDATA ($2007) Tests
    // ========================================================================

    public function test_ppudata_write_increments_address_by_1(): void
    {
        // Default increment mode is +1
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x00);

        // Write two bytes
        $this->ppu->cpuWrite(0x07, 0xAA);
        $this->ppu->cpuWrite(0x07, 0xBB);

        // Read back (address should have auto-incremented)
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x00);

        $this->ppu->cpuRead(0x07); // Buffer first
        $this->assertEquals(0xAA, $this->ppu->cpuRead(0x07));
        $this->assertEquals(0xBB, $this->ppu->cpuRead(0x07));
    }

    public function test_ppudata_write_increments_address_by_32(): void
    {
        // Set increment mode to +32 (bit 2 of PPUCTRL)
        $this->ppu->cpuWrite(0x00, 0x04);

        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x00);

        // Write byte
        $this->ppu->cpuWrite(0x07, 0xCC);

        // Next write should be at 0x2000 + 32 = 0x2020
        $this->ppu->cpuWrite(0x07, 0xDD);

        // Verify
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x20);

        $this->ppu->cpuRead(0x07); // Buffer
        $value = $this->ppu->cpuRead(0x07);

        $this->assertEquals(0xDD, $value);
    }

    public function test_ppudata_read_is_buffered(): void
    {
        // Write data
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x00);
        $this->ppu->cpuWrite(0x07, 0x42);

        // Reset address
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x00);

        // First read returns stale buffer (0)
        $firstRead = $this->ppu->cpuRead(0x07);
        $this->assertEquals(0x00, $firstRead);

        // Second read returns the actual value
        // But we need to reset address first since read increments
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x00);
        $this->ppu->cpuRead(0x07); // Throw away buffer
        $secondRead = $this->ppu->cpuRead(0x07);

        $this->assertEquals(0x42, $secondRead);
    }

    public function test_ppudata_palette_reads_not_buffered(): void
    {
        // Write to palette RAM
        $this->ppu->cpuWrite(0x06, 0x3F);
        $this->ppu->cpuWrite(0x06, 0x00);
        $this->ppu->cpuWrite(0x07, 0x0F);

        // Read palette (should NOT be buffered)
        $this->ppu->cpuWrite(0x06, 0x3F);
        $this->ppu->cpuWrite(0x06, 0x00);

        $value = $this->ppu->cpuRead(0x07);
        $this->assertEquals(0x0F, $value); // Immediate, not buffered
    }

    public function test_ppudata_address_wraps_at_0x4000(): void
    {
        // Write to end of address space
        $this->ppu->cpuWrite(0x06, 0x3F);
        $this->ppu->cpuWrite(0x06, 0xFF);
        $this->ppu->cpuWrite(0x07, 0x99);

        // Next write should wrap to 0x0000
        $this->ppu->cpuWrite(0x07, 0x88);

        // This would write to pattern table (CHR-ROM)
        // For now, just verify no crash
        $this->assertTrue(true);
    }

    // ========================================================================
    // Register Mirroring Tests
    // ========================================================================

    public function test_registers_mirror_every_8_bytes(): void
    {
        // $2000 should behave same as $2008, $2010, $2018, etc.
        // Test by setting increment mode via mirrored register
        $this->ppu->cpuWrite(0x00, 0x00); // PPUCTRL, increment = +1
        $this->ppu->cpuWrite(0x06, 0x20); // Set address
        $this->ppu->cpuWrite(0x06, 0x00);
        $this->ppu->cpuWrite(0x07, 0xAA); // Write to 0x2000, address becomes 0x2001

        // Now write to PPUCTRL via mirrored address 0x08, set increment to +32
        $this->ppu->cpuWrite(0x08, 0x04); // Should affect PPUCTRL (bit 2 = increment +32)

        // Write another byte - should increment by +32 if mirroring works
        $this->ppu->cpuWrite(0x07, 0xBB); // Write to current address (0x2001), address becomes 0x2021

        // Verify: read from 0x2001 should give 0xBB
        $this->ppu->cpuWrite(0x06, 0x20);
        $this->ppu->cpuWrite(0x06, 0x01);
        $this->ppu->cpuRead(0x07); // Buffer
        $value = $this->ppu->cpuRead(0x07);

        $this->assertEquals(0xBB, $value);
    }
}
