<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Cartridge;

/**
 * Mapper 3 (CNROM)
 *
 * Simple CHR-ROM bank switching mapper
 * Used by: Arkanoid, Joust, Paperboy, and many simple games
 *
 * Features:
 * - Fixed 16KB or 32KB PRG-ROM (no bank switching)
 * - CHR-ROM bank switching (8KB banks)
 * - Fixed mirroring
 *
 * Memory Map:
 * CPU $8000-$BFFF: First 16KB of PRG-ROM
 * CPU $C000-$FFFF: Second 16KB of PRG-ROM (or mirror of first 16KB if only 16KB)
 * CPU $8000-$FFFF (write): Select CHR-ROM bank (lower 2 bits)
 * PPU $0000-$1FFF: Switchable 8KB CHR-ROM bank
 */
class Mapper3 implements MapperInterface
{
    /**
     * PRG-ROM data
     * @var array<int>
     */
    private array $prgRom;

    /**
     * CHR-ROM data
     * @var array<int>
     */
    private array $chrRom;

    /**
     * Mirroring mode
     * @var int 0 = Horizontal, 1 = Vertical
     */
    private int $mirroring;

    /**
     * Whether to mirror PRG-ROM (only 16KB)
     * @var bool
     */
    private bool $prgRomMirrored;

    /**
     * Selected CHR-ROM bank (8KB)
     * @var int
     */
    private int $chrBankSelect = 0;

    /**
     * Number of CHR-ROM banks
     * @var int
     */
    private int $chrBankCount;

    public function __construct(Cartridge $cartridge)
    {
        $this->prgRom = $cartridge->getPrgRom();
        $this->chrRom = $cartridge->getChrRom();
        $this->mirroring = $cartridge->getMirroring();

        // PRG-ROM: 16KB or 32KB
        $prgSize = $cartridge->getPrgRomSize();
        $this->prgRomMirrored = ($prgSize === 16384);

        // CHR-ROM: Calculate number of 8KB banks
        $chrSize = $cartridge->getChrRomSize();
        $this->chrBankCount = $chrSize / 8192;

        $this->reset();
    }

    public function cpuRead(int $address): int
    {
        // PRG-ROM: $8000-$FFFF
        if ($address >= 0x8000) {
            $address &= 0x7FFF; // $8000-$FFFF -> $0000-$7FFF

            // Mirror if only 16KB
            if ($this->prgRomMirrored) {
                $address &= 0x3FFF; // Map $4000-$7FFF to $0000-$3FFF
            }

            return $this->prgRom[$address] ?? 0x00;
        }

        return 0x00;
    }

    public function cpuWrite(int $address, int $value): void
    {
        // Writes to $8000-$FFFF select CHR-ROM bank
        if ($address >= 0x8000) {
            // Lower 2 bits select bank (some games use lower 3-4 bits)
            // Use modulo to wrap to available banks
            $this->chrBankSelect = $value % $this->chrBankCount;
        }
    }

    public function ppuRead(int $address): int
    {
        // CHR-ROM: $0000-$1FFF
        if ($address < 0x2000) {
            // Calculate offset in CHR-ROM
            $bankOffset = $this->chrBankSelect * 8192;
            $chrAddress = $bankOffset + $address;

            return $this->chrRom[$chrAddress] ?? 0x00;
        }

        return 0x00;
    }

    public function ppuWrite(int $address, int $value): void
    {
        // CHR-ROM is read-only, writes are ignored
        // (Some games have CHR-RAM, but that's rare for Mapper 3)
    }

    public function getMirroring(): int
    {
        return $this->mirroring;
    }

    public function reset(): void
    {
        // Reset to first CHR bank
        $this->chrBankSelect = 0;
    }
}
