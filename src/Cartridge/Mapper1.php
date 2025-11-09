<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Cartridge;

/**
 * Mapper 1 (MMC1)
 *
 * Most common NES mapper (~28% of all games)
 * Used by: Tetris, Legend of Zelda, Metroid, Mega Man 2, and many others
 *
 * Features:
 * - PRG-ROM bank switching (16KB or 32KB modes)
 * - CHR-ROM/RAM bank switching (4KB or 8KB modes)
 * - Configurable mirroring
 * - Serial write interface (5 writes to configure)
 *
 * Memory Map:
 * - PRG-ROM: $8000-$FFFF (switchable banks)
 * - CHR-ROM/RAM: $0000-$1FFF (switchable banks)
 * - Registers: $8000-$FFFF (write-only, via serial interface)
 */
class Mapper1 implements MapperInterface
{
    private Cartridge $cartridge;

    /**
     * PRG-ROM data
     * @var array<int>
     */
    private array $prgRom;

    /**
     * CHR memory (ROM or RAM)
     * @var array<int>
     */
    private array $chrMemory;

    /**
     * Number of PRG-ROM banks (16KB each)
     * @var int
     */
    private int $prgBankCount;

    /**
     * Number of CHR banks (4KB each)
     * @var int
     */
    private int $chrBankCount;

    /**
     * Shift register for serial writes
     * @var int
     */
    private int $shiftRegister = 0x10;

    /**
     * Write counter (5 writes needed to update register)
     * @var int
     */
    private int $writeCount = 0;

    /**
     * Control register
     * Bits: CPPMM
     * - C: CHR mode (0 = 8KB, 1 = 4KB)
     * - PP: PRG mode (0/1 = 32KB, 2 = fix first, 3 = fix last)
     * - MM: Mirroring (0 = one-screen lower, 1 = one-screen upper, 2 = vertical, 3 = horizontal)
     * @var int
     */
    private int $control = 0x0C;

    /**
     * CHR bank 0 (4KB at $0000 or 8KB at $0000)
     * @var int
     */
    private int $chrBank0 = 0;

    /**
     * CHR bank 1 (4KB at $1000)
     * @var int
     */
    private int $chrBank1 = 0;

    /**
     * PRG bank select
     * @var int
     */
    private int $prgBank = 0;

    /**
     * Current mirroring mode
     * @var int
     */
    private int $mirroring;

    /**
     * @var callable|null
     */
    private $logger = null;

    public function setLogger(callable $logger): void
    {
        $this->logger = $logger;
    }


    public function __construct(Cartridge $cartridge)
    {
        $this->cartridge = $cartridge;
        $this->prgRom = $cartridge->getPrgRom();
        $this->prgBankCount = count($this->prgRom) / 16384;

        // Initialize CHR memory (ROM or RAM)
        $chrRomSize = $cartridge->getChrRomSize();
        if ($chrRomSize > 0) {
            // CHR-ROM: already an array
            $this->chrMemory = $cartridge->getChrRom();
            $this->chrBankCount = $chrRomSize / 4096;
        } else {
            // CHR-RAM: 8KB
            $this->chrMemory = array_fill(0, 8192, 0x00);
            $this->chrBankCount = 2;
        }

        $this->mirroring = $cartridge->getMirroring();
        $this->reset();
    }

    public function reset(): void
    {
        $this->shiftRegister = 0x10;
        $this->writeCount = 0;
        $this->control = 0x0C; // PRG mode 3 (fix last bank)
        $this->chrBank0 = 0;
        $this->chrBank1 = 0;
        $this->prgBank = 0;
    }

    public function cpuRead(int $address): int
    {
        if ($address < 0x8000) {
            return 0x00;
        }

        // Determine PRG mode
        $prgMode = ($this->control >> 2) & 0x03;

        if ($prgMode <= 1) { // 32KB mode
            $bank_index = ($this->prgBank & 0x0E) >> 1;
            $offset = ($address & 0x7FFF) | ($bank_index << 15);
            return $this->prgRom[$offset & (count($this->prgRom) - 1)];
        } elseif ($prgMode == 2) { // Fix first bank
            if ($address < 0xC000) { // $8000-$BFFF
                return $this->prgRom[$address & 0x3FFF];
            } else { // $C000-$FFFF
                $offset = ($address & 0x3FFF) | ($this->prgBank << 14);
                return $this->prgRom[$offset & (count($this->prgRom) - 1)];
            }
        } else { // Mode 3: Fix last bank
            if ($address < 0xC000) { // $8000-$BFFF
                $offset = ($address & 0x3FFF) | ($this->prgBank << 14);
                return $this->prgRom[$offset & (count($this->prgRom) - 1)];
            } else { // $C000-$FFFF
                $offset = ($address & 0x3FFF) | ((count($this->prgRom) - 1) & 0xFC000);
                return $this->prgRom[$offset & (count($this->prgRom) - 1)];
            }
        }
    }

    public function cpuWrite(int $address, int $value): void
    {
        if ($address < 0x8000) {
            return;
        }

        if ($this->logger) {
            call_user_func($this->logger, sprintf(
                "CPU Write @ 0x%04X, Value: 0x%02X", $address, $value
            ));
        }

        if (($value & 0x80) !== 0) {
            if ($this->logger) call_user_func($this->logger, "  -> RESET");
            $this->shiftRegister = 0x10;
            $this->writeCount = 0;
            $this->control = $this->control | 0x0C;
            return;
        }

        if ($this->writeCount < 5) {
            $this->shiftRegister = ($this->shiftRegister >> 1) | (($value & 1) << 4);
            $this->writeCount++;
            if ($this->logger) {
                call_user_func($this->logger, sprintf(
                    "  -> Shift Register: %s (Write %d)",
                    str_pad(decbin($this->shiftRegister), 5, '0', STR_PAD_LEFT),
                    $this->writeCount
                ));
            }
        }

        if ($this->writeCount === 5) {
            $targetRegister = ($address >> 13) & 3;
            if ($this->logger) {
                call_user_func($this->logger, sprintf(
                    "  -> Write Complete. Target: %d (0x%04X-0x%04X)",
                    $targetRegister,
                    0x8000 + ($targetRegister * 0x2000),
                    0x9FFF + ($targetRegister * 0x2000)
                ));
            }

            switch ($targetRegister) {
                case 0: // Control ($8000-$9FFF)
                    $this->control = $this->shiftRegister & 0x1F;
                    $this->updateMirroring();
                    if ($this->logger) call_user_func($this->logger, "    -> Control set to 0x" . dechex($this->control));
                    break;
                case 1: // CHR bank 0 ($A000-$BFFF)
                    $this->chrBank0 = $this->shiftRegister & 0x1F;
                    if ($this->logger) call_user_func($this->logger, "    -> CHR Bank 0 set to 0x" . dechex($this->chrBank0));
                    break;
                case 2: // CHR bank 1 ($C000-$DFFF)
                    $this->chrBank1 = $this->shiftRegister & 0x1F;
                    if ($this->logger) call_user_func($this->logger, "    -> CHR Bank 1 set to 0x" . dechex($this->chrBank1));
                    break;
                case 3: // PRG bank ($E000-$FFFF)
                    $this->prgBank = $this->shiftRegister & 0x0F;
                    if ($this->logger) call_user_func($this->logger, "    -> PRG Bank set to 0x" . dechex($this->prgBank));
                    break;
            }

            $this->shiftRegister = 0x10;
            $this->writeCount = 0;
        }
    }

    public function ppuRead(int $address): int
    {
        if ($address >= 0x2000) {
            return 0x00;
        }

        $chrMode = ($this->control >> 4) & 1;

        if ($chrMode === 0) { // 8KB mode
            $bank = $this->chrBank0 >> 1;
            $offset = $address | ($bank << 13);
        } else { // 4KB mode
            if ($address < 0x1000) {
                $offset = ($address & 0x0FFF) | ($this->chrBank0 << 12);
            } else {
                $offset = ($address & 0x0FFF) | ($this->chrBank1 << 12);
            }
        }

        if ($this->cartridge->getChrRomSize() === 0) { // CHR-RAM
            return $this->chrMemory[$offset & 0x1FFF];
        } else {
            return $this->chrMemory[$offset & (count($this->chrMemory) - 1)];
        }
    }

    public function ppuWrite(int $address, int $value): void
    {
        if ($address >= 0x2000) {
            return;
        }

        // Only allow writes to CHR-RAM (not CHR-ROM)
        if ($this->cartridge->getChrRomSize() == 0) {
            $chrMode = ($this->control >> 4) & 0x01;

            if ($chrMode == 0) {
                $bank = $this->chrBank0 >> 1;
                $offset = $address + ($bank * 8192);
            } else {
                if ($address < 0x1000) {
                    $offset = $address + ($this->chrBank0 * 4096);
                } else {
                    $offset = ($address - 0x1000) + ($this->chrBank1 * 4096);
                }
            }

            $this->chrMemory[$offset & (count($this->chrMemory) - 1)] = $value & 0xFF;
        }
    }

    private function updateMirroring(): void
    {
        $mirrorMode = $this->control & 0x03;

        $this->mirroring = match ($mirrorMode) {
            0, 1 => $mirrorMode, // One-screen (0 or 1)
            2 => 1, // Vertical mirroring
            3 => 0, // Horizontal mirroring
        };
    }

    public function getMirroring(): int
    {
        return $this->mirroring;
    }
}
