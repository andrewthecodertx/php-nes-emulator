<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Cartridge;

/**
 * Mapper Interface
 *
 * Mappers control how ROM is mapped to CPU and PPU address spaces.
 * Different mappers provide different banking schemes and additional features.
 */
interface MapperInterface
{
    /**
     * CPU reads from cartridge address space ($4020-$FFFF)
     *
     * @param int $address CPU address (0x4020-0xFFFF)
     * @return int Byte value (0x00-0xFF)
     */
    public function cpuRead(int $address): int;

    /**
     * CPU writes to cartridge address space ($4020-$FFFF)
     * Some mappers use writes to trigger bank switching
     *
     * @param int $address CPU address (0x4020-0xFFFF)
     * @param int $value Byte value (0x00-0xFF)
     */
    public function cpuWrite(int $address, int $value): void;

    /**
     * PPU reads from pattern table address space ($0000-$1FFF)
     *
     * @param int $address PPU address (0x0000-0x1FFF)
     * @return int Byte value (0x00-0xFF)
     */
    public function ppuRead(int $address): int;

    /**
     * PPU writes to pattern table address space ($0000-$1FFF)
     * Only works with CHR-RAM (writable)
     *
     * @param int $address PPU address (0x0000-0x1FFF)
     * @param int $value Byte value (0x00-0xFF)
     */
    public function ppuWrite(int $address, int $value): void;

    /**
     * Get mirroring mode
     *
     * @return int 0 = Horizontal, 1 = Vertical
     */
    public function getMirroring(): int;

    /**
     * Reset mapper to initial state
     */
    public function reset(): void;
}
