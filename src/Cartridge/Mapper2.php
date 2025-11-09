<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Cartridge;

/**
 * Mapper 2 (UxROM)
 *
 * PRG-ROM bank switching mapper
 * Used by: Mega Man, Castlevania, Contra, Duck Tales, and many others
 *
 * Features:
 * - Switchable 16KB PRG-ROM bank at $8000-$BFFF
 * - Fixed 16KB PRG-ROM bank at $C000-$FFFF (last bank)
 * - 8KB CHR-RAM (no CHR-ROM)
 * - Fixed mirroring
 *
 * Memory Map:
 * CPU $8000-$BFFF: Switchable 16KB PRG-ROM bank
 * CPU $C000-$FFFF: Fixed 16KB PRG-ROM bank (last bank)
 * CPU $8000-$FFFF (write): Select PRG-ROM bank (lower 4 bits)
 * PPU $0000-$1FFF: 8KB CHR-RAM
 */
class Mapper2 implements MapperInterface
{
    /**
     * PRG-ROM data
     * @var array<int>
     */
    private array $prgRom;

    /**
     * CHR-RAM data (8KB)
     * @var array<int>
     */
    private array $chrRam;

    /**
     * Mirroring mode
     * @var int 0 = Horizontal, 1 = Vertical
     */
    private int $mirroring;

    /**
     * Number of 16KB PRG-ROM banks
     * @var int
     */
    private int $prgBankCount;

    /**
     * Selected PRG-ROM bank for $8000-$BFFF
     * @var int
     */
    private int $prgBankSelect = 0;

    /**
     * Last PRG-ROM bank index (fixed at $C000-$FFFF)
     * @var int
     */
    private int $prgLastBank;

    public function __construct(Cartridge $cartridge)
    {
        $this->prgRom = $cartridge->getPrgRom();
        $this->mirroring = $cartridge->getMirroring();

        // Calculate number of 16KB banks
        $prgSize = $cartridge->getPrgRomSize();
        $this->prgBankCount = $prgSize / 16384;
        $this->prgLastBank = $this->prgBankCount - 1;

        // Initialize CHR-RAM (8KB)
        $this->chrRam = array_fill(0, 8192, 0x00);

        $this->reset();
    }

    public function cpuRead(int $address): int
    {
        if ($address >= 0x8000 && $address < 0xC000) {
            // $8000-$BFFF: Switchable bank
            $bankOffset = $this->prgBankSelect * 16384;
            $prgAddress = $bankOffset + ($address & 0x3FFF);
            return $this->prgRom[$prgAddress] ?? 0x00;
        }

        if ($address >= 0xC000) {
            // $C000-$FFFF: Fixed last bank
            $bankOffset = $this->prgLastBank * 16384;
            $prgAddress = $bankOffset + ($address & 0x3FFF);
            return $this->prgRom[$prgAddress] ?? 0x00;
        }

        return 0x00;
    }

    public function cpuWrite(int $address, int $value): void
    {
        // Writes to $8000-$FFFF select PRG-ROM bank
        if ($address >= 0x8000) {
            // Lower bits select bank (typically 3-4 bits, depends on ROM size)
            $this->prgBankSelect = $value % $this->prgBankCount;
        }
    }

    public function ppuRead(int $address): int
    {
        // CHR-RAM: $0000-$1FFF
        if ($address < 0x2000) {
            return $this->chrRam[$address];
        }

        return 0x00;
    }

    public function ppuWrite(int $address, int $value): void
    {
        // CHR-RAM is writable
        if ($address < 0x2000) {
            $this->chrRam[$address] = $value & 0xFF;
        }
    }

    public function getMirroring(): int
    {
        return $this->mirroring;
    }

    public function reset(): void
    {
        // Reset to first bank
        $this->prgBankSelect = 0;

        // Clear CHR-RAM
        $this->chrRam = array_fill(0, 8192, 0x00);
    }
}
