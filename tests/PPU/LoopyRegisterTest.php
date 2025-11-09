<?php

declare(strict_types=1);

namespace tests\PPU;

use andrewthecoder\nes\PPU\LoopyRegister;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Loopy Register (VRAM addressing)
 *
 * The Loopy register is a 15-bit register used for VRAM addressing and scrolling.
 * Layout: yyy NN YYYYY XXXXX
 * - XXXXX: Coarse X (5 bits, 0-31)
 * - YYYYY: Coarse Y (5 bits, 0-31)
 * - NN: Nametable select (2 bits)
 * - yyy: Fine Y (3 bits, 0-7)
 */
class LoopyRegisterTest extends TestCase
{
    // ========================================================================
    // Bit Field Tests
    // ========================================================================

    public function test_coarse_x_read_write(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseX(15);
        $this->assertEquals(15, $reg->coarseX());

        $reg->setCoarseX(31);
        $this->assertEquals(31, $reg->coarseX());

        $reg->setCoarseX(0);
        $this->assertEquals(0, $reg->coarseX());
    }

    public function test_coarse_x_masks_to_5_bits(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseX(0xFF); // 255
        $this->assertEquals(31, $reg->coarseX()); // Should be masked to 5 bits
    }

    public function test_coarse_y_read_write(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseY(15);
        $this->assertEquals(15, $reg->coarseY());

        $reg->setCoarseY(29); // Max valid nametable row
        $this->assertEquals(29, $reg->coarseY());

        $reg->setCoarseY(31); // Hardware allows 0-31
        $this->assertEquals(31, $reg->coarseY());
    }

    public function test_coarse_y_masks_to_5_bits(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseY(0xFF);
        $this->assertEquals(31, $reg->coarseY());
    }

    public function test_nametable_x_read_write(): void
    {
        $reg = new LoopyRegister();

        $reg->setNametableX(0);
        $this->assertEquals(0, $reg->nametableX());

        $reg->setNametableX(1);
        $this->assertEquals(1, $reg->nametableX());
    }

    public function test_nametable_x_masks_to_1_bit(): void
    {
        $reg = new LoopyRegister();

        $reg->setNametableX(0xFF);
        $this->assertEquals(1, $reg->nametableX());
    }

    public function test_nametable_y_read_write(): void
    {
        $reg = new LoopyRegister();

        $reg->setNametableY(0);
        $this->assertEquals(0, $reg->nametableY());

        $reg->setNametableY(1);
        $this->assertEquals(1, $reg->nametableY());
    }

    public function test_nametable_y_masks_to_1_bit(): void
    {
        $reg = new LoopyRegister();

        $reg->setNametableY(0xFF);
        $this->assertEquals(1, $reg->nametableY());
    }

    public function test_fine_y_read_write(): void
    {
        $reg = new LoopyRegister();

        $reg->setFineY(0);
        $this->assertEquals(0, $reg->fineY());

        $reg->setFineY(7);
        $this->assertEquals(7, $reg->fineY());

        $reg->setFineY(3);
        $this->assertEquals(3, $reg->fineY());
    }

    public function test_fine_y_masks_to_3_bits(): void
    {
        $reg = new LoopyRegister();

        $reg->setFineY(0xFF);
        $this->assertEquals(7, $reg->fineY());
    }

    // ========================================================================
    // Bit Field Independence Tests
    // ========================================================================

    public function test_bit_fields_dont_interfere(): void
    {
        $reg = new LoopyRegister();

        // Set all fields to different values
        $reg->setCoarseX(10);
        $reg->setCoarseY(20);
        $reg->setNametableX(1);
        $reg->setNametableY(1);
        $reg->setFineY(5);

        // All should retain their values
        $this->assertEquals(10, $reg->coarseX());
        $this->assertEquals(20, $reg->coarseY());
        $this->assertEquals(1, $reg->nametableX());
        $this->assertEquals(1, $reg->nametableY());
        $this->assertEquals(5, $reg->fineY());
    }

    public function test_setting_one_field_doesnt_affect_others(): void
    {
        $reg = new LoopyRegister();

        $reg->set(0x7FFF); // Set all bits to 1

        // Change coarse X, others should remain
        $reg->setCoarseX(0);
        $this->assertEquals(0, $reg->coarseX());
        $this->assertEquals(31, $reg->coarseY());
        $this->assertEquals(1, $reg->nametableX());
        $this->assertEquals(1, $reg->nametableY());
        $this->assertEquals(7, $reg->fineY());
    }

    // ========================================================================
    // Full Register Tests
    // ========================================================================

    public function test_get_set_full_register(): void
    {
        $reg = new LoopyRegister();

        $reg->set(0x1234);
        $this->assertEquals(0x1234, $reg->get());

        $reg->set(0x7FFF); // Max 15 bits
        $this->assertEquals(0x7FFF, $reg->get());
    }

    public function test_register_masks_to_15_bits(): void
    {
        $reg = new LoopyRegister();

        $reg->set(0xFFFF);
        $this->assertEquals(0x7FFF, $reg->get()); // Top bit should be masked off
    }

    // ========================================================================
    // Increment X Tests
    // ========================================================================

    public function test_increment_x_normal(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseX(5);
        $reg->incrementX();
        $this->assertEquals(6, $reg->coarseX());

        $reg->setCoarseX(0);
        $reg->incrementX();
        $this->assertEquals(1, $reg->coarseX());
    }

    public function test_increment_x_wraps_at_32(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseX(31);
        $reg->setNametableX(0);

        $reg->incrementX();

        // Should wrap to 0 and flip nametable
        $this->assertEquals(0, $reg->coarseX());
        $this->assertEquals(1, $reg->nametableX());
    }

    public function test_increment_x_flips_nametable_both_ways(): void
    {
        $reg = new LoopyRegister();

        // Test 0 -> 1
        $reg->setCoarseX(31);
        $reg->setNametableX(0);
        $reg->incrementX();
        $this->assertEquals(1, $reg->nametableX());

        // Test 1 -> 0
        $reg->setCoarseX(31);
        $reg->setNametableX(1);
        $reg->incrementX();
        $this->assertEquals(0, $reg->nametableX());
    }

    public function test_increment_x_doesnt_affect_other_fields(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseX(10);
        $reg->setCoarseY(15);
        $reg->setNametableY(1);
        $reg->setFineY(3);

        $reg->incrementX();

        $this->assertEquals(11, $reg->coarseX());
        $this->assertEquals(15, $reg->coarseY());
        $this->assertEquals(1, $reg->nametableY());
        $this->assertEquals(3, $reg->fineY());
    }

    // ========================================================================
    // Increment Y Tests
    // ========================================================================

    public function test_increment_y_fine_y_only(): void
    {
        $reg = new LoopyRegister();

        $reg->setFineY(3);
        $reg->setCoarseY(10);

        $reg->incrementY();

        // Fine Y should increment, coarse Y unchanged
        $this->assertEquals(4, $reg->fineY());
        $this->assertEquals(10, $reg->coarseY());
    }

    public function test_increment_y_fine_y_overflow(): void
    {
        $reg = new LoopyRegister();

        $reg->setFineY(7); // Max fine Y
        $reg->setCoarseY(10);

        $reg->incrementY();

        // Fine Y wraps to 0, coarse Y increments
        $this->assertEquals(0, $reg->fineY());
        $this->assertEquals(11, $reg->coarseY());
    }

    public function test_increment_y_coarse_y_wraps_at_29(): void
    {
        $reg = new LoopyRegister();

        $reg->setFineY(7);
        $reg->setCoarseY(29); // Last valid nametable row
        $reg->setNametableY(0);

        $reg->incrementY();

        // Should wrap to 0 and flip nametable Y
        $this->assertEquals(0, $reg->fineY());
        $this->assertEquals(0, $reg->coarseY());
        $this->assertEquals(1, $reg->nametableY());
    }

    public function test_increment_y_coarse_y_wraps_at_31_without_nametable_flip(): void
    {
        $reg = new LoopyRegister();

        $reg->setFineY(7);
        $reg->setCoarseY(31); // Hardware bug: row 31 wraps without flipping
        $reg->setNametableY(0);

        $reg->incrementY();

        // Wraps to 0, but nametable doesn't flip (hardware bug)
        $this->assertEquals(0, $reg->fineY());
        $this->assertEquals(0, $reg->coarseY());
        $this->assertEquals(0, $reg->nametableY()); // NOT flipped
    }

    public function test_increment_y_nametable_flips_both_ways(): void
    {
        $reg = new LoopyRegister();

        // Test 0 -> 1
        $reg->setFineY(7);
        $reg->setCoarseY(29);
        $reg->setNametableY(0);
        $reg->incrementY();
        $this->assertEquals(1, $reg->nametableY());

        // Test 1 -> 0
        $reg->setFineY(7);
        $reg->setCoarseY(29);
        $reg->setNametableY(1);
        $reg->incrementY();
        $this->assertEquals(0, $reg->nametableY());
    }

    public function test_increment_y_doesnt_affect_x_fields(): void
    {
        $reg = new LoopyRegister();

        $reg->setCoarseX(15);
        $reg->setNametableX(1);
        $reg->setFineY(7);
        $reg->setCoarseY(29);

        $reg->incrementY();

        // X fields should be unaffected
        $this->assertEquals(15, $reg->coarseX());
        $this->assertEquals(1, $reg->nametableX());
    }

    // ========================================================================
    // Transfer Tests
    // ========================================================================

    public function test_transfer_x(): void
    {
        $source = new LoopyRegister();
        $dest = new LoopyRegister();

        // Set source
        $source->setCoarseX(15);
        $source->setNametableX(1);
        $source->setCoarseY(10);
        $source->setNametableY(1);
        $source->setFineY(5);

        // Set dest to different values
        $dest->setCoarseX(5);
        $dest->setNametableX(0);
        $dest->setCoarseY(20);
        $dest->setNametableY(0);
        $dest->setFineY(3);

        // Transfer X
        $dest->transferX($source);

        // Only X fields should be copied
        $this->assertEquals(15, $dest->coarseX());
        $this->assertEquals(1, $dest->nametableX());

        // Y fields should be unchanged
        $this->assertEquals(20, $dest->coarseY());
        $this->assertEquals(0, $dest->nametableY());
        $this->assertEquals(3, $dest->fineY());
    }

    public function test_transfer_y(): void
    {
        $source = new LoopyRegister();
        $dest = new LoopyRegister();

        // Set source
        $source->setCoarseX(15);
        $source->setNametableX(1);
        $source->setCoarseY(10);
        $source->setNametableY(1);
        $source->setFineY(5);

        // Set dest to different values
        $dest->setCoarseX(5);
        $dest->setNametableX(0);
        $dest->setCoarseY(20);
        $dest->setNametableY(0);
        $dest->setFineY(3);

        // Transfer Y
        $dest->transferY($source);

        // Only Y fields should be copied
        $this->assertEquals(10, $dest->coarseY());
        $this->assertEquals(1, $dest->nametableY());
        $this->assertEquals(5, $dest->fineY());

        // X fields should be unchanged
        $this->assertEquals(5, $dest->coarseX());
        $this->assertEquals(0, $dest->nametableX());
    }

    // ========================================================================
    // Utility Method Tests
    // ========================================================================

    public function test_address(): void
    {
        $reg = new LoopyRegister();

        $reg->set(0x2345);
        $this->assertEquals(0x2345, $reg->address());

        $reg->set(0x3FFF);
        $this->assertEquals(0x3FFF, $reg->address());
    }

    public function test_nametable_address(): void
    {
        $reg = new LoopyRegister();

        // Nametable 0 (top-left)
        $reg->setNametableX(0);
        $reg->setNametableY(0);
        $this->assertEquals(0x2000, $reg->nametableAddress() & 0x2C00);

        // Nametable 1 (top-right)
        $reg->setNametableX(1);
        $reg->setNametableY(0);
        $this->assertEquals(0x2400, $reg->nametableAddress() & 0x2C00);

        // Nametable 2 (bottom-left)
        $reg->setNametableX(0);
        $reg->setNametableY(1);
        $this->assertEquals(0x2800, $reg->nametableAddress() & 0x2C00);

        // Nametable 3 (bottom-right)
        $reg->setNametableX(1);
        $reg->setNametableY(1);
        $this->assertEquals(0x2C00, $reg->nametableAddress() & 0x2C00);
    }

    public function test_attribute_address(): void
    {
        $reg = new LoopyRegister();

        // Top-left corner of nametable 0
        $reg->setNametableX(0);
        $reg->setNametableY(0);
        $reg->setCoarseX(0);
        $reg->setCoarseY(0);

        $attrAddr = $reg->attributeAddress();

        // Should be start of attribute table
        $this->assertEquals(0x23C0, $attrAddr);
    }

    public function test_attribute_address_for_different_tiles(): void
    {
        $reg = new LoopyRegister();

        $reg->setNametableX(0);
        $reg->setNametableY(0);

        // Tiles 0-3, 0-3 share attribute byte at 0x23C0
        $reg->setCoarseX(0);
        $reg->setCoarseY(0);
        $addr1 = $reg->attributeAddress();

        $reg->setCoarseX(3);
        $reg->setCoarseY(3);
        $addr2 = $reg->attributeAddress();

        $this->assertEquals($addr1, $addr2); // Same attribute byte

        // Tile 4,4 should be different
        $reg->setCoarseX(4);
        $reg->setCoarseY(4);
        $addr3 = $reg->attributeAddress();

        $this->assertNotEquals($addr1, $addr3);
    }
}
