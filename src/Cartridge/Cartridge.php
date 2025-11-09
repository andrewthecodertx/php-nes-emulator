<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Cartridge;

use InvalidArgumentException;

/**
 * NES Cartridge (ROM) Loader
 *
 * Parses iNES format ROM files and provides access to:
 * - PRG-ROM (Program ROM for CPU)
 * - CHR-ROM (Character ROM for PPU)
 * - Mapper configuration
 * - Mirroring mode
 */
class Cartridge
{
    /**
     * PRG-ROM data (CPU program code)
     * Typically 16KB or 32KB
     *
     * @var array<int>
     */
    private array $prgRom = [];

    /**
     * CHR-ROM data (PPU graphics data)
     * Typically 8KB
     *
     * @var array<int>
     */
    private array $chrRom = [];

    /**
     * Mapper number (0-255)
     * Determines how ROM is mapped to CPU/PPU address space
     *
     * @var int
     */
    private int $mapperNumber;

    /**
     * Mirroring mode
     * 0 = Horizontal, 1 = Vertical
     *
     * @var int
     */
    private int $mirroring;

    /**
     * Load cartridge from iNES format file
     *
     * @param string $filename Path to .nes ROM file
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromFile(string $filename): self
    {
        if (!file_exists($filename)) {
            throw new InvalidArgumentException("ROM file not found: $filename");
        }

        $data = file_get_contents($filename);
        if ($data === false) {
            throw new InvalidArgumentException("Failed to read ROM file: $filename");
        }

        return self::fromData($data);
    }

    /**
     * Load cartridge from iNES format data
     *
     * @param string $data Binary ROM data
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromData(string $data): self
    {
        $length = strlen($data);

        if ($length < 16) {
            throw new InvalidArgumentException("ROM too small (less than 16 bytes)");
        }

        // Check magic number: "NES" followed by MS-DOS EOF (0x1A)
        $magic = substr($data, 0, 4);
        if ($magic !== "NES\x1A") {
            throw new InvalidArgumentException("Invalid iNES header (magic number mismatch)");
        }

        // Parse header
        $prgRomSize = ord($data[4]) * 16384; // 16KB units
        $chrRomSize = ord($data[5]) * 8192;  // 8KB units
        $flags6 = ord($data[6]);
        $flags7 = ord($data[7]);

        // Extract mapper number (upper 4 bits of flags7, lower 4 bits of flags6)
        $mapperNumber = ($flags7 & 0xF0) | ($flags6 >> 4);

        // Extract mirroring (bit 0 of flags6)
        $mirroring = $flags6 & 0x01;

        // Check for trainer (not supported)
        if ($flags6 & 0x04) {
            throw new InvalidArgumentException("Trainer not supported");
        }

        // Calculate expected file size
        $expectedSize = 16 + $prgRomSize + $chrRomSize;
        if ($length < $expectedSize) {
            throw new InvalidArgumentException(
                "ROM file too small. Expected at least $expectedSize bytes, got $length bytes"
            );
        }

        // Extract PRG-ROM
        $prgRomOffset = 16;
        $prgRom = [];
        for ($i = 0; $i < $prgRomSize; $i++) {
            $prgRom[] = ord($data[$prgRomOffset + $i]);
        }

        // Extract CHR-ROM
        $chrRomOffset = 16 + $prgRomSize;
        $chrRom = [];
        for ($i = 0; $i < $chrRomSize; $i++) {
            $chrRom[] = ord($data[$chrRomOffset + $i]);
        }

        // Create cartridge instance
        $cartridge = new self();
        $cartridge->prgRom = $prgRom;
        $cartridge->chrRom = $chrRom;
        $cartridge->mapperNumber = $mapperNumber;
        $cartridge->mirroring = $mirroring;

        return $cartridge;
    }

    /**
     * Get PRG-ROM data
     *
     * @return array<int>
     */
    public function getPrgRom(): array
    {
        return $this->prgRom;
    }

    /**
     * Get PRG-ROM size in bytes
     *
     * @return int
     */
    public function getPrgRomSize(): int
    {
        return count($this->prgRom);
    }

    /**
     * Get CHR-ROM data
     *
     * @return array<int>
     */
    public function getChrRom(): array
    {
        return $this->chrRom;
    }

    /**
     * Get CHR-ROM size in bytes
     *
     * @return int
     */
    public function getChrRomSize(): int
    {
        return count($this->chrRom);
    }

    /**
     * Get mapper number
     *
     * @return int
     */
    public function getMapperNumber(): int
    {
        return $this->mapperNumber;
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
     * Check if mirroring is horizontal
     *
     * @return bool
     */
    public function isHorizontalMirroring(): bool
    {
        return $this->mirroring === 0;
    }

    /**
     * Check if mirroring is vertical
     *
     * @return bool
     */
    public function isVerticalMirroring(): bool
    {
        return $this->mirroring === 1;
    }
}
