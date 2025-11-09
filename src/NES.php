<?php

declare(strict_types=1);

namespace andrewthecoder\nes;

use andrewthecoder\MOS6502\CPU;
use andrewthecoder\nes\Bus\NESBus;
use andrewthecoder\nes\PPU\PPU;
use andrewthecoder\nes\APU\APU;
use andrewthecoder\nes\Cartridge\Cartridge;
use andrewthecoder\nes\Cartridge\Mapper0;
use andrewthecoder\nes\Cartridge\Mapper1;
use andrewthecoder\nes\Cartridge\Mapper2;
use andrewthecoder\nes\Cartridge\Mapper3;
use andrewthecoder\nes\Cartridge\Mapper4;
use andrewthecoder\nes\Cartridge\MapperInterface;
use InvalidArgumentException;

/**
 * NES Emulator
 *
 * Main emulator class that coordinates CPU, PPU, and cartridge
 */
class NES
{
    /**
     * 6502 CPU
     *
     * @var CPU
     */
    private CPU $cpu;

    /**
     * NES Bus (connects CPU, RAM, PPU, Cartridge)
     *
     * @var NESBus
     */
    private NESBus $bus;

    /**
     * PPU (Picture Processing Unit)
     *
     * @var PPU
     */
    private PPU $ppu;

    /**
     * Cartridge mapper
     *
     * @var MapperInterface
     */
    private MapperInterface $mapper;

    /**
     * Create NES emulator and load ROM
     *
     * @param string $romPath Path to .nes ROM file
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromROM(string $romPath): self
    {
        $cartridge = Cartridge::fromFile($romPath);
        return self::fromCartridge($cartridge);
    }

    /**
     * Create NES emulator from cartridge
     *
     * @param Cartridge $cartridge
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromCartridge(Cartridge $cartridge): self
    {
        // Create mapper based on mapper number
        $mapper = self::createMapper($cartridge);

        $ppu = new PPU();
        $apu = new APU();

        // Connect PPU to mapper for CHR-ROM/CHR-RAM access
        $ppu->setMapper($mapper);

        // Set nametable mirroring mode from cartridge
        $ppu->setMirroring($cartridge->getMirroring());

        $bus = new NESBus($ppu, $apu, $mapper);
        $cpu = new CPU($bus);

        // Give bus access to CPU for NMI delivery
        $bus->setCPU($cpu);

        // Disable auto-tick - we'll handle timing manually in clock()
        // This prevents double-ticking of the PPU
        $cpu->setAutoTickBus(false);

        $nes = new self();
        $nes->cpu = $cpu;
        $nes->bus = $bus;
        $nes->ppu = $ppu;
        $nes->mapper = $mapper;

        $nes->reset();

        return $nes;
    }

    /**
     * Create mapper from cartridge
     *
     * @param Cartridge $cartridge
     * @return MapperInterface
     * @throws InvalidArgumentException
     */
    private static function createMapper(Cartridge $cartridge): MapperInterface
    {
        $mapperNumber = $cartridge->getMapperNumber();

        return match ($mapperNumber) {
            0 => new Mapper0($cartridge),
            1 => new Mapper1($cartridge),
            2 => new Mapper2($cartridge),
            3 => new Mapper3($cartridge),
            4 => new Mapper4($cartridge),
            default => throw new InvalidArgumentException("Mapper $mapperNumber not supported"),
        };
    }

    /**
     * Reset NES to initial state
     */
    public function reset(): void
    {
        $this->bus->reset();
        $this->cpu->reset();
    }

    /**
     * System clock counter (tracks PPU cycles)
     *
     * @var int
     */
    private int $systemClock = 0;

    /**
     * Execute one CPU instruction
     * PPU will advance by 3x the CPU cycles
     *
     * @deprecated Use clock() for proper timing synchronization
     */
    public function step(): void
    {
        $this->cpu->step();
    }

    /**
     * Clock the entire system by one PPU cycle
     *
     * This is the core timing mechanism. The PPU runs every cycle,
     * and the CPU runs every 3rd cycle. This matches real NES hardware
     * where the PPU clock is 3x faster than the CPU clock.
     *
     * @return bool True if frame is complete
     */
    public function clock(): bool
    {
        // PPU runs every cycle
        $this->ppu->clock();

        // APU runs every CPU cycle (same speed as CPU)
        if ($this->systemClock % 3 === 0) {
            $this->bus->getAPU()->clock();
        }

        // CPU runs every 3rd cycle (PPU is 3x faster)
        if ($this->systemClock % 3 === 0) {
            // Step CPU by one cycle (not one instruction!)
            $this->cpu->step();
        }

        // Check for PPU NMI (VBlank interrupt)
        if ($this->ppu->hasNMI()) {
            $this->cpu->requestNMI();
            $this->ppu->clearNMI();
        }

        // Check for APU IRQ (rare but needed for some games)
        if ($this->bus->getAPU()->hasIRQ()) {
            $this->cpu->requestIRQ();
            $this->bus->getAPU()->clearIRQ();
        }

        $this->systemClock++;

        return $this->ppu->isFrameComplete();
    }

    /**
     * Run emulation until frame is complete
     * Returns when PPU has finished rendering a frame
     */
    public function runFrame(): void
    {
        $this->ppu->clearFrameComplete();

        while (!$this->clock()) {
            // Keep clocking until frame completes
        }

        // IMPORTANT: Complete any in-progress CPU instruction before returning
        // The frame might complete in the middle of a multi-cycle instruction,
        // leaving the CPU with pending cycles. We need to finish that instruction
        // so the next frame starts cleanly.
        while ($this->cpu->hasPendingCycles()) {
            $this->cpu->step();
        }
    }

    /**
     * Run emulation for a specific number of CPU instructions
     *
     * @param int $instructions Number of CPU instructions to execute
     */
    public function runInstructions(int $instructions): void
    {
        for ($i = 0; $i < $instructions; $i++) {
            $this->step();
        }
    }

    /**
     * Get frame buffer from PPU
     * Returns 256x240 array of [R, G, B] pixels
     *
     * @return array<array{int, int, int}>
     */
    public function getFrameBuffer(): array
    {
        return $this->ppu->getFrameBuffer();
    }

    /**
     * Get frame buffer as flat array
     * Returns [R, G, B, R, G, B, ...] for direct use in canvas
     *
     * @return array<int>
     */
    public function getFrameBufferFlat(): array
    {
        return $this->ppu->getFrameBufferFlat();
    }

    /**
     * Get CPU instance
     *
     * @return CPU
     */
    public function getCPU(): CPU
    {
        return $this->cpu;
    }

    /**
     * Get PPU instance
     *
     * @return PPU
     */
    public function getPPU(): PPU
    {
        return $this->ppu;
    }

    /**
     * Get bus instance
     *
     * @return NESBus
     */
    public function getBus(): NESBus
    {
        return $this->bus;
    }

    /**
     * Get mapper instance
     *
     * @return MapperInterface
     */
    public function getMapper(): MapperInterface
    {
        return $this->mapper;
    }

    /**
     * Check if NMI (Non-Maskable Interrupt) is pending
     * Used by PPU to signal VBlank
     *
     * @return bool
     */
    public function hasNMI(): bool
    {
        return $this->ppu->hasNMI();
    }

    /**
     * Clear NMI flag
     */
    public function clearNMI(): void
    {
        $this->ppu->clearNMI();
    }
}
