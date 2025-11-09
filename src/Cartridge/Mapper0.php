<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Cartridge;

/**
 * Mapper 0 (NROM)
 *
 * The simplest mapper with no bank switching:
 * - 16KB or 32KB PRG-ROM (32KB or 16KB mirrored)
 * - 8KB CHR-ROM (or CHR-RAM)
 * - Fixed mirroring
 *
 * Memory Map:
 * CPU $8000-$BFFF: First 16KB of PRG-ROM
 * CPU $C000-$FFFF: Second 16KB of PRG-ROM (or mirror of first 16KB)
 * PPU $0000-$1FFF: CHR-ROM (8KB)
 */
class Mapper0 implements MapperInterface
{
    /**
     * PRG-ROM data
     *
     * @var array<int>
     */
    private array $prgRom;

    /**
     * CHR-ROM/RAM data
     *
     * @var array<int>
     */
    private array $chrMemory;

    /**
     * Mirroring mode
     *
     * @var int 0 = Horizontal, 1 = Vertical
     */
    private int $mirroring;

    /**
     * Whether to mirror PRG-ROM
     * True if only 16KB PRG-ROM (mirror $8000-$BFFF to $C000-$FFFF)
     *
     * @var bool
     */
    private bool $prgRomMirrored;

    /**
     * Whether CHR is RAM (writable) or ROM (read-only)
     *
     * @var bool
     */
    private bool $chrIsRam;

    /**
     * Construct Mapper 0
     *
     * @param Cartridge $cartridge
     */
    public function __construct(Cartridge $cartridge)
    {
        $this->prgRom = $cartridge->getPrgRom();
        $this->mirroring = $cartridge->getMirroring();

        // If no CHR-ROM, use CHR-RAM (8KB)
        if ($cartridge->getChrRomSize() === 0) {
            $this->chrMemory = array_fill(0, 8192, 0x00);
            $this->chrIsRam = true;
        } else {
            $this->chrMemory = $cartridge->getChrRom();
            $this->chrIsRam = false;
        }

        // Determine if PRG-ROM should be mirrored
        $this->prgRomMirrored = count($this->prgRom) <= 16384;
    }

    /**
     * CPU reads from PRG-ROM
     *
     * @param int $address 0x8000-0xFFFF
     * @return int
     */
    public function cpuRead(int $address): int
    {
        // PRG-ROM is at $8000-$FFFF
        if ($address >= 0x8000) {
            $offset = $address - 0x8000;

            // If only 16KB, mirror the ROM
            if ($this->prgRomMirrored) {
                $offset &= 0x3FFF; // Wrap to 16KB
            }

            return $this->prgRom[$offset] ?? 0x00;
        }

        return 0x00;
    }

    /**
     * CPU writes to cartridge space
     * NROM doesn't support writes (ROM is read-only)
     *
     * @param int $address
     * @param int $value
     */
    public function cpuWrite(int $address, int $value): void
    {
        // NROM has no writable registers or PRG-RAM
        // Writes are ignored
    }

    /**
     * PPU reads from CHR-ROM/RAM
     *
     * @param int $address 0x0000-0x1FFF
     * @return int
     */
    public function ppuRead(int $address): int
    {
        // CHR-ROM/RAM is at $0000-$1FFF (8KB)
        if ($address < 0x2000) {
            return $this->chrMemory[$address] ?? 0x00;
        }

        return 0x00;
    }

    /**
     * PPU writes to CHR-RAM
     * Only works if using CHR-RAM (no CHR-ROM in cartridge)
     *
     * @param int $address 0x0000-0x1FFF
     * @param int $value
     */
    public function ppuWrite(int $address, int $value): void
    {
        // Only allow writes if using CHR-RAM (not CHR-ROM which is read-only)
        if ($this->chrIsRam && $address < 0x2000) {
            $this->chrMemory[$address] = $value & 0xFF;
        }
        // CHR-ROM writes are ignored
    }

    /**
     * Get mirroring mode
     *
     * @return int 0 = Horizontal, 1 = Vertical
     */
    public function getMirroring(): int
    {
        return $this->mirroring;
    }

    /**
     * Reset mapper (no-op for NROM)
     */
    public function reset(): void
    {
        // NROM has no state to reset
    }
}
