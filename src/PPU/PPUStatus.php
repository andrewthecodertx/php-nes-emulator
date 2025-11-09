<?php

declare(strict_types=1);

namespace andrewthecoder\nes\PPU;

/**
 * PPUSTATUS Register ($2002) - Read Only
 *
 * Reports PPU status including vblank, sprite 0 hit, and sprite overflow.
 * Reading this register clears the vblank flag and the address latch.
 */
class PPUStatus
{
    private int $register = 0x00;

    public function set(int $value): void
    {
        $this->register = $value & 0xFF;
    }

    public function get(): int
    {
        return $this->register;
    }

    /**
     * Set or clear the sprite overflow flag
     */
    public function setSpriteOverflow(bool $value): void
    {
        if ($value) {
            $this->register |= 0x20;  // Set bit 5
        } else {
            $this->register &= ~0x20; // Clear bit 5
        }
    }

    /**
     * Set or clear the sprite 0 hit flag
     */
    public function setSpriteZeroHit(bool $value): void
    {
        if ($value) {
            $this->register |= 0x40;  // Set bit 6
        } else {
            $this->register &= ~0x40; // Clear bit 6
        }
    }

    /**
     * Set or clear the vertical blank flag
     */
    public function setVerticalBlank(bool $value): void
    {
        if ($value) {
            $this->register |= 0x80;  // Set bit 7
        } else {
            $this->register &= ~0x80; // Clear bit 7
        }
    }

    /** @return bool Sprite overflow (more than 8 sprites on scanline) */
    public function spriteOverflow(): bool
    {
        return (bool)(($this->register >> 5) & 0x01);
    }

    /** @return bool Sprite 0 hit (sprite 0 collision with background) */
    public function spriteZeroHit(): bool
    {
        return (bool)(($this->register >> 6) & 0x01);
    }

    /** @return bool Vertical blank has started */
    public function verticalBlank(): bool
    {
        return (bool)(($this->register >> 7) & 0x01);
    }

    /**
     * Clear vertical blank flag (happens on read of $2002)
     */
    public function clearVerticalBlank(): void
    {
        $this->setVerticalBlank(false);
    }
}
