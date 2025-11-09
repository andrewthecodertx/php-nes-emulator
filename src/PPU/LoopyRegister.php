<?php

declare(strict_types=1);

namespace andrewthecoder\nes\PPU;

/**
 * Loopy Register - VRAM Address Register
 *
 * This is the internal VRAM address register used by the PPU for scrolling.
 * It was reverse-engineered by "Loopy" and is named in their honor.
 *
 * The 15-bit register layout:
 * yyy NN YYYYY XXXXX
 * ||| || ||||| +++++-- coarse X scroll (5 bits, tile 0-31)
 * ||| || +++++-------- coarse Y scroll (5 bits, tile 0-29)
 * ||| ++-------------- nametable select (2 bits: 00=TL, 01=TR, 10=BL, 11=BR)
 * +++----------------- fine Y scroll (3 bits, pixel 0-7 within tile)
 *
 * The NES has 2KB of internal VRAM which is organized as 4 nametables
 * of 1KB each (though only 2KB exists, so 2 are mirrored).
 *
 * Each nametable is 32x30 tiles (960 tiles), and each tile is 8x8 pixels.
 */
class LoopyRegister
{
    private int $register = 0x0000;

    /**
     * Set the full register value
     */
    public function set(int $value): void
    {
        $this->register = $value & 0x7FFF; // 15 bits
    }

    /**
     * Get the full register value
     */
    public function get(): int
    {
        return $this->register;
    }

    // ========================================================================
    // Bit Field Accessors
    // ========================================================================

    /**
     * Get coarse X scroll (bits 0-4)
     * Tile X coordinate (0-31)
     *
     * @return int 0-31
     */
    public function coarseX(): int
    {
        return $this->register & 0x001F;
    }

    /**
     * Set coarse X scroll (bits 0-4)
     *
     * @param int $value 0-31
     */
    public function setCoarseX(int $value): void
    {
        $this->register = ($this->register & ~0x001F) | ($value & 0x1F);
    }

    /**
     * Get coarse Y scroll (bits 5-9)
     * Tile Y coordinate (0-29, though hardware allows 0-31)
     *
     * @return int 0-31
     */
    public function coarseY(): int
    {
        return ($this->register >> 5) & 0x001F;
    }

    /**
     * Set coarse Y scroll (bits 5-9)
     *
     * @param int $value 0-31
     */
    public function setCoarseY(int $value): void
    {
        $this->register = ($this->register & ~0x03E0) | (($value & 0x1F) << 5);
    }

    /**
     * Get nametable X (bit 10)
     * Horizontal nametable selection (0=left, 1=right)
     *
     * @return int 0 or 1
     */
    public function nametableX(): int
    {
        return ($this->register >> 10) & 0x0001;
    }

    /**
     * Set nametable X (bit 10)
     *
     * @param int $value 0 or 1
     */
    public function setNametableX(int $value): void
    {
        $this->register = ($this->register & ~0x0400) | (($value & 0x01) << 10);
    }

    /**
     * Get nametable Y (bit 11)
     * Vertical nametable selection (0=top, 1=bottom)
     *
     * @return int 0 or 1
     */
    public function nametableY(): int
    {
        return ($this->register >> 11) & 0x0001;
    }

    /**
     * Set nametable Y (bit 11)
     *
     * @param int $value 0 or 1
     */
    public function setNametableY(int $value): void
    {
        $this->register = ($this->register & ~0x0800) | (($value & 0x01) << 11);
    }

    /**
     * Get fine Y scroll (bits 12-14)
     * Vertical pixel offset within tile (0-7)
     *
     * @return int 0-7
     */
    public function fineY(): int
    {
        return ($this->register >> 12) & 0x0007;
    }

    /**
     * Set fine Y scroll (bits 12-14)
     *
     * @param int $value 0-7
     */
    public function setFineY(int $value): void
    {
        $this->register = ($this->register & ~0x7000) | (($value & 0x07) << 12);
    }

    // ========================================================================
    // Scroll Operations (Hardware-Accurate)
    // ========================================================================

    /**
     * Increment horizontal scroll (move right by 1 tile)
     *
     * This is called during rendering to move to the next tile.
     * When reaching the end of a nametable (32 tiles), it wraps
     * to 0 and flips the horizontal nametable bit.
     */
    public function incrementX(): void
    {
        // Check if rendering is enabled (would be checked by caller)
        if ($this->coarseX() === 31) {
            // Wrap coarse X to 0
            $this->setCoarseX(0);

            // Flip horizontal nametable
            $this->setNametableX($this->nametableX() ^ 1);
        } else {
            // Increment coarse X
            $this->setCoarseX($this->coarseX() + 1);
        }
    }

    /**
     * Increment vertical scroll (move down by 1 scanline)
     *
     * This is called at the end of each scanline during rendering.
     * First increments fine Y (pixel offset within tile), then
     * when that overflows, increments coarse Y (tile row).
     *
     * Hardware bug: Coarse Y can go up to 31, but nametables are only
     * 30 tiles tall. Rows 30-31 actually access attribute table data.
     */
    public function incrementY(): void
    {
        // Check if rendering is enabled (would be checked by caller)
        if ($this->fineY() < 7) {
            // Increment fine Y (still within same tile)
            $this->setFineY($this->fineY() + 1);
        } else {
            // Fine Y overflows, reset to 0
            $this->setFineY(0);

            // Now increment coarse Y
            $y = $this->coarseY();

            if ($y === 29) {
                // Reached bottom of nametable (30 rows)
                $y = 0;

                // Flip vertical nametable
                $this->setNametableY($this->nametableY() ^ 1);
            } elseif ($y === 31) {
                // Hardware bug: Row 31 wraps to 0 without flipping nametable
                // This accesses attribute table space
                $y = 0;
            } else {
                // Normal increment
                $y++;
            }

            $this->setCoarseY($y);
        }
    }

    /**
     * Transfer horizontal bits from another register
     *
     * Copies coarse X and nametable X from source.
     * This is called at the end of each scanline (dot 257) to reset
     * horizontal position for the next scanline.
     *
     * @param LoopyRegister $source Source register (typically temp VRAM address)
     */
    public function transferX(LoopyRegister $source): void
    {
        // Copy bits 0-4 (coarse X) and bit 10 (nametable X)
        $this->setCoarseX($source->coarseX());
        $this->setNametableX($source->nametableX());
    }

    /**
     * Transfer vertical bits from another register
     *
     * Copies coarse Y, nametable Y, and fine Y from source.
     * This is called during pre-render scanline (dots 280-304) to
     * reset vertical position for the next frame.
     *
     * @param LoopyRegister $source Source register (typically temp VRAM address)
     */
    public function transferY(LoopyRegister $source): void
    {
        // Copy bits 5-9 (coarse Y), bit 11 (nametable Y), and bits 12-14 (fine Y)
        $this->setCoarseY($source->coarseY());
        $this->setNametableY($source->nametableY());
        $this->setFineY($source->fineY());
    }

    // ========================================================================
    // Utility Methods
    // ========================================================================

    /**
     * Get the full VRAM address (0x0000-0x3FFF)
     *
     * This is useful for direct VRAM access during rendering.
     *
     * @return int VRAM address
     */
    public function address(): int
    {
        return $this->register & 0x3FFF;
    }

    /**
     * Get the nametable address (0x2000-0x2FFF)
     *
     * @return int Nametable base address
     */
    public function nametableAddress(): int
    {
        return 0x2000 | ($this->register & 0x0FFF);
    }

    /**
     * Get the attribute table address for current position
     *
     * Each 32x32 pixel area (4x4 tiles) shares one attribute byte.
     *
     * @return int Attribute table address
     */
    public function attributeAddress(): int
    {
        return 0x23C0
            | ($this->nametableY() << 11)
            | ($this->nametableX() << 10)
            | (($this->coarseY() >> 2) << 3)
            | ($this->coarseX() >> 2);
    }
}
