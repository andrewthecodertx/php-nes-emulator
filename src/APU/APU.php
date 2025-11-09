<?php

declare(strict_types=1);

namespace andrewthecoder\nes\APU;

/**
 * NES APU (Audio Processing Unit)
 *
 * The APU generates audio for the NES through 5 channels:
 * - 2 Pulse wave channels
 * - 1 Triangle wave channel
 * - 1 Noise channel
 * - 1 DMC (Delta Modulation Channel) for samples
 *
 * This is a stub implementation that provides register reads/writes
 * but doesn't generate actual audio. This is sufficient for games
 * that just need to initialize the APU.
 *
 * Register Map:
 * $4000-$4003: Pulse 1
 * $4004-$4007: Pulse 2
 * $4008-$400B: Triangle
 * $400C-$400F: Noise
 * $4010-$4013: DMC
 * $4015: Status
 * $4017: Frame Counter
 */
class APU
{
    /**
     * APU registers ($4000-$4017)
     * @var array<int>
     */
    private array $registers = [];

    /**
     * Frame counter mode
     * @var int
     */
    private int $frameCounterMode = 0;

    /**
     * Frame counter IRQ enabled
     * @var bool
     */
    private bool $frameIRQEnabled = false;

    /**
     * IRQ pending flag
     * @var bool
     */
    private bool $irqPending = false;

    /**
     * Cycle counter
     * @var int
     */
    private int $cycles = 0;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Reset APU to power-on state
     */
    public function reset(): void
    {
        // Initialize all registers to 0
        $this->registers = array_fill(0, 0x18, 0x00);

        // Silence all channels
        $this->registers[0x15] = 0x00; // Status register

        $this->frameCounterMode = 0;
        $this->frameIRQEnabled = false;
        $this->irqPending = false;
        $this->cycles = 0;
    }

    /**
     * Write to APU register
     *
     * @param int $address $4000-$4017
     * @param int $value 0x00-0xFF
     */
    public function write(int $address, int $value): void
    {
        $register = $address - 0x4000;

        if ($register < 0 || $register >= 0x18) {
            return;
        }

        $this->registers[$register] = $value & 0xFF;

        // Handle special registers
        switch ($address) {
            case 0x4015: // Status register (channel enable)
                // Writing to status enables/disables channels
                // Bit 0: Pulse 1, Bit 1: Pulse 2, Bit 2: Triangle
                // Bit 3: Noise, Bit 4: DMC
                break;

            case 0x4017: // Frame Counter
                // Bit 7: Mode (0 = 4-step, 1 = 5-step)
                // Bit 6: IRQ disable
                $this->frameCounterMode = ($value >> 7) & 0x01;
                $this->frameIRQEnabled = (($value >> 6) & 0x01) == 0;

                // Writing to $4017 resets the frame counter
                $this->cycles = 0;

                // If IRQ disable bit is set, clear IRQ flag
                if (!$this->frameIRQEnabled) {
                    $this->irqPending = false;
                }
                break;
        }
    }

    /**
     * Read from APU register
     *
     * @param int $address $4000-$4017
     * @return int 0x00-0xFF
     */
    public function read(int $address): int
    {
        $register = $address - 0x4000;

        if ($register < 0 || $register >= 0x18) {
            return 0x00;
        }

        // Only $4015 is readable
        if ($address == 0x4015) {
            // Status register
            // Bit 0-4: Channel length counter > 0
            // Bit 6: Frame interrupt flag
            // Bit 7: DMC interrupt flag

            $value = $this->registers[$register] & 0x1F;

            if ($this->irqPending) {
                $value |= 0x40; // Frame interrupt
            }

            // Reading $4015 clears frame interrupt flag
            $this->irqPending = false;

            return $value;
        }

        // Other registers are write-only
        return 0x00;
    }

    /**
     * Clock the APU (called once per CPU cycle)
     */
    public function clock(): void
    {
        $this->cycles++;

        // Frame counter runs at ~240Hz (4-step) or ~192Hz (5-step)
        // For now, just a stub - we don't actually generate audio
    }

    /**
     * Check if APU has pending IRQ
     *
     * @return bool
     */
    public function hasIRQ(): bool
    {
        return $this->frameIRQEnabled && $this->irqPending;
    }

    /**
     * Clear IRQ flag
     */
    public function clearIRQ(): void
    {
        $this->irqPending = false;
    }

    /**
     * Get cycles count (for debugging)
     *
     * @return int
     */
    public function getCycles(): int
    {
        return $this->cycles;
    }
}
