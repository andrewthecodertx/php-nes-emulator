<?php

declare(strict_types=1);

namespace andrewthecoder\nes\PPU;

/**
 * PPUCTRL Register ($2000) - Write Only
 *
 * Controls PPU behavior including nametable selection, address increment,
 * sprite/background pattern table selection, and NMI enable.
 */
class PPUControl
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

    /** @return int Nametable X (0 or 1) */
    public function nametableX(): int
    {
        return ($this->register >> 0) & 0x01;
    }

    /** @return int Nametable Y (0 or 1) */
    public function nametableY(): int
    {
        return ($this->register >> 1) & 0x01;
    }

    /** @return int VRAM address increment per CPU read/write (1 or 32) */
    public function incrementMode(): int
    {
        return (($this->register >> 2) & 0x01) ? 32 : 1;
    }

    /** @return int Sprite pattern table address (0x0000 or 0x1000) */
    public function spritePatternTable(): int
    {
        return (($this->register >> 3) & 0x01) ? 0x1000 : 0x0000;
    }

    /** @return int Background pattern table address (0x0000 or 0x1000) */
    public function backgroundPatternTable(): int
    {
        return (($this->register >> 4) & 0x01) ? 0x1000 : 0x0000;
    }

    /** @return int Sprite size (0 = 8x8, 1 = 8x16) */
    public function spriteSize(): int
    {
        return ($this->register >> 5) & 0x01;
    }

    /** @return bool PPU master/slave select (unused in NES) */
    public function slaveMode(): bool
    {
        return (bool)(($this->register >> 6) & 0x01);
    }

    /** @return bool Generate NMI at start of vblank (0 = off, 1 = on) */
    public function enableNMI(): bool
    {
        return (bool)(($this->register >> 7) & 0x01);
    }
}
