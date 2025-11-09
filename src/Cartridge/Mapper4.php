<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Cartridge;

/**
 * Mapper 4 (MMC3)
 *
 * Most advanced common mapper (~22% of all games)
 * Used by: Super Mario Bros 2, 3, Mega Man 3-6, Kirby's Adventure, and many others
 *
 * Features:
 * - 8KB PRG-ROM bank switching (with 8KB fixed banks)
 * - 1KB or 2KB CHR-ROM bank switching
 * - Configurable PRG/CHR banking modes
 * - Scanline counter for IRQ timing
 * - Configurable mirroring
 *
 * Memory Map:
 * CPU $8000-$9FFF: 8KB switchable PRG-ROM bank (or fixed to second-last bank)
 * CPU $A000-$BFFF: 8KB switchable PRG-ROM bank
 * CPU $C000-$DFFF: 8KB switchable PRG-ROM bank (or fixed to second-last bank)
 * CPU $E000-$FFFF: 8KB PRG-ROM bank (fixed to last bank)
 * PPU $0000-$07FF: 2KB switchable CHR bank
 * PPU $0800-$0FFF: 2KB switchable CHR bank
 * PPU $1000-$13FF: 1KB switchable CHR bank
 * PPU $1400-$17FF: 1KB switchable CHR bank
 * PPU $1800-$1BFF: 1KB switchable CHR bank
 * PPU $1C00-$1FFF: 1KB switchable CHR bank
 *
 * Registers:
 * $8000-$9FFF (even): Bank select
 * $8001-$9FFF (odd): Bank data
 * $A000-$BFFF (even): Mirroring
 * $A001-$BFFF (odd): PRG-RAM protect
 * $C000-$DFFF (even): IRQ latch
 * $C001-$DFFF (odd): IRQ reload
 * $E000-$FFFF (even): IRQ disable
 * $E001-$FFFF (odd): IRQ enable
 */
class Mapper4 implements MapperInterface
{
    private Cartridge $cartridge;
    private array $prgRom;
    private array $chrMemory;
    private int $prgBankCount; // Number of 8KB banks
    private int $chrBankCount; // Number of 1KB banks

    // Bank registers
    private array $bankRegisters = [0, 0, 0, 0, 0, 0, 0, 0];
    private int $bankSelectRegister = 0;
    private int $prgBankMode = 0; // 0 or 1
    private int $chrBankMode = 0; // 0 or 1

    // Mirroring
    private int $mirroring = 0;

    // IRQ (scanline counter)
    private int $irqLatch = 0;
    private int $irqCounter = 0;
    private bool $irqReload = false;
    private bool $irqEnabled = false;
    private bool $irqPending = false;

    // PRG-RAM
    private array $prgRam;
    private bool $prgRamEnabled = true;
    private bool $prgRamWriteProtect = false;

    public function __construct(Cartridge $cartridge)
    {
        $this->cartridge = $cartridge;
        $this->prgRom = $cartridge->getPrgRom();
        $this->mirroring = $cartridge->getMirroring();

        // Calculate bank counts
        $prgSize = $cartridge->getPrgRomSize();
        $this->prgBankCount = $prgSize / 8192; // 8KB banks

        $chrSize = $cartridge->getChrRomSize();
        if ($chrSize > 0) {
            $this->chrMemory = $cartridge->getChrRom();
            $this->chrBankCount = $chrSize / 1024; // 1KB banks
        } else {
            // CHR-RAM (8KB)
            $this->chrMemory = array_fill(0, 8192, 0x00);
            $this->chrBankCount = 8;
        }

        // PRG-RAM (8KB)
        $this->prgRam = array_fill(0, 8192, 0x00);

        $this->reset();
    }

    public function cpuRead(int $address): int
    {
        // PRG-RAM: $6000-$7FFF
        if ($address >= 0x6000 && $address < 0x8000) {
            if ($this->prgRamEnabled) {
                return $this->prgRam[$address & 0x1FFF];
            }
            return 0x00;
        }

        // PRG-ROM: $8000-$FFFF
        if ($address >= 0x8000) {
            $bank = 0;

            if ($address < 0xA000) {
                // $8000-$9FFF
                if ($this->prgBankMode === 0) {
                    $bank = $this->bankRegisters[6];
                } else {
                    $bank = $this->prgBankCount - 2;
                }
            } elseif ($address < 0xC000) {
                // $A000-$BFFF
                $bank = $this->bankRegisters[7];
            } elseif ($address < 0xE000) {
                // $C000-$DFFF
                if ($this->prgBankMode === 0) {
                    $bank = $this->prgBankCount - 2;
                } else {
                    $bank = $this->bankRegisters[6];
                }
            } else {
                // $E000-$FFFF (fixed to last bank)
                $bank = $this->prgBankCount - 1;
            }

            $offset = $bank * 8192 + ($address & 0x1FFF);
            return $this->prgRom[$offset] ?? 0x00;
        }

        return 0x00;
    }

    public function cpuWrite(int $address, int $value): void
    {
        // PRG-RAM: $6000-$7FFF
        if ($address >= 0x6000 && $address < 0x8000) {
            if ($this->prgRamEnabled && !$this->prgRamWriteProtect) {
                $this->prgRam[$address & 0x1FFF] = $value & 0xFF;
            }
            return;
        }

        // Registers: $8000-$FFFF
        if ($address >= 0x8000) {
            $even = ($address & 0x0001) === 0;

            if ($address < 0xA000) {
                // $8000-$9FFF
                if ($even) {
                    // Bank select ($8000, $8002, ...)
                    $this->bankSelectRegister = $value & 0x07;
                    $this->prgBankMode = ($value >> 6) & 0x01;
                    $this->chrBankMode = ($value >> 7) & 0x01;
                } else {
                    // Bank data ($8001, $8003, ...)
                    $this->bankRegisters[$this->bankSelectRegister] = $value;
                }
            } elseif ($address < 0xC000) {
                // $A000-$BFFF
                if ($even) {
                    // Mirroring ($A000, $A002, ...)
                    $this->mirroring = $value & 0x01;
                } else {
                    // PRG-RAM protect ($A001, $A003, ...)
                    $this->prgRamWriteProtect = ($value & 0x40) !== 0;
                    $this->prgRamEnabled = ($value & 0x80) !== 0;
                }
            } elseif ($address < 0xE000) {
                // $C000-$DFFF
                if ($even) {
                    // IRQ latch ($C000, $C002, ...)
                    $this->irqLatch = $value;
                } else {
                    // IRQ reload ($C001, $C003, ...)
                    $this->irqReload = true;
                }
            } else {
                // $E000-$FFFF
                if ($even) {
                    // IRQ disable ($E000, $E002, ...)
                    $this->irqEnabled = false;
                    $this->irqPending = false;
                } else {
                    // IRQ enable ($E001, $E003, ...)
                    $this->irqEnabled = true;
                }
            }
        }
    }

    public function ppuRead(int $address): int
    {
        if ($address < 0x2000) {
            $bank = 0;

            if ($this->chrBankMode === 0) {
                // Mode 0: Two 2KB banks at $0000-$0FFF, four 1KB banks at $1000-$1FFF
                if ($address < 0x0800) {
                    $bank = $this->bankRegisters[0] & 0xFE; // 2KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x07FF)] ?? 0x00;
                } elseif ($address < 0x1000) {
                    $bank = $this->bankRegisters[1] & 0xFE; // 2KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x07FF)] ?? 0x00;
                } elseif ($address < 0x1400) {
                    $bank = $this->bankRegisters[2]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                } elseif ($address < 0x1800) {
                    $bank = $this->bankRegisters[3]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                } elseif ($address < 0x1C00) {
                    $bank = $this->bankRegisters[4]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                } else {
                    $bank = $this->bankRegisters[5]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                }
            } else {
                // Mode 1: Four 1KB banks at $0000-$0FFF, two 2KB banks at $1000-$1FFF
                if ($address < 0x0400) {
                    $bank = $this->bankRegisters[2]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                } elseif ($address < 0x0800) {
                    $bank = $this->bankRegisters[3]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                } elseif ($address < 0x0C00) {
                    $bank = $this->bankRegisters[4]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                } elseif ($address < 0x1000) {
                    $bank = $this->bankRegisters[5]; // 1KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x03FF)] ?? 0x00;
                } elseif ($address < 0x1800) {
                    $bank = $this->bankRegisters[0] & 0xFE; // 2KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x07FF)] ?? 0x00;
                } else {
                    $bank = $this->bankRegisters[1] & 0xFE; // 2KB bank
                    return $this->chrMemory[($bank * 1024) + ($address & 0x07FF)] ?? 0x00;
                }
            }
        }

        return 0x00;
    }

    public function ppuWrite(int $address, int $value): void
    {
        // Only allow writes if using CHR-RAM (no CHR-ROM)
        if ($address < 0x2000 && $this->cartridge->getChrRomSize() === 0) {
            $this->chrMemory[$address] = $value & 0xFF;
        }
    }

    public function getMirroring(): int
    {
        return $this->mirroring;
    }

    /**
     * Clock the IRQ counter (called by PPU at specific times)
     * This should be called when the PPU renders scanline 260 (or on rising edge of A12)
     */
    public function clockIRQ(): void
    {
        if ($this->irqCounter === 0 || $this->irqReload) {
            $this->irqCounter = $this->irqLatch;
            $this->irqReload = false;
        } else {
            $this->irqCounter--;
        }

        if ($this->irqCounter === 0 && $this->irqEnabled) {
            $this->irqPending = true;
        }
    }

    /**
     * Check if IRQ should be triggered
     */
    public function hasIRQ(): bool
    {
        return $this->irqPending;
    }

    /**
     * Clear IRQ pending flag
     */
    public function clearIRQ(): void
    {
        $this->irqPending = false;
    }

    public function reset(): void
    {
        $this->bankRegisters = [0, 0, 0, 0, 0, 0, 0, 0];
        $this->bankSelectRegister = 0;
        $this->prgBankMode = 0;
        $this->chrBankMode = 0;
        $this->irqLatch = 0;
        $this->irqCounter = 0;
        $this->irqReload = false;
        $this->irqEnabled = false;
        $this->irqPending = false;
        $this->prgRamEnabled = true;
        $this->prgRamWriteProtect = false;
    }
}
