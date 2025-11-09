<?php

declare(strict_types=1);

namespace andrewthecoder\nes\PPU;

/**
 * PPUMASK Register ($2001) - Write Only
 *
 * Controls rendering options including grayscale, color emphasis,
 * and sprite/background enable.
 */
class PPUMask
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

    /** @return bool Grayscale (0 = normal, 1 = grayscale) */
    public function grayscale(): bool
    {
        return (bool)(($this->register >> 0) & 0x01);
    }

    /** @return bool Show background in leftmost 8 pixels */
    public function renderBackgroundLeft(): bool
    {
        return (bool)(($this->register >> 1) & 0x01);
    }

    /** @return bool Show sprites in leftmost 8 pixels */
    public function renderSpritesLeft(): bool
    {
        return (bool)(($this->register >> 2) & 0x01);
    }

    /** @return bool Enable background rendering */
    public function renderBackground(): bool
    {
        return (bool)(($this->register >> 3) & 0x01);
    }

    /** @return bool Enable sprite rendering */
    public function renderSprites(): bool
    {
        return (bool)(($this->register >> 4) & 0x01);
    }

    /** @return bool Emphasize red (NTSC) / green (PAL) */
    public function emphasizeRed(): bool
    {
        return (bool)(($this->register >> 5) & 0x01);
    }

    /** @return bool Emphasize green (NTSC) / red (PAL) */
    public function emphasizeGreen(): bool
    {
        return (bool)(($this->register >> 6) & 0x01);
    }

    /** @return bool Emphasize blue */
    public function emphasizeBlue(): bool
    {
        return (bool)(($this->register >> 7) & 0x01);
    }

    /** @return bool True if rendering is enabled (background OR sprites) */
    public function isRenderingEnabled(): bool
    {
        return $this->renderBackground() || $this->renderSprites();
    }
}
