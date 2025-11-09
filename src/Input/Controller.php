<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Input;

/**
 * NES Controller (Standard Joypad)
 *
 * Emulates the standard NES controller with 8 buttons:
 * A, B, Select, Start, Up, Down, Left, Right
 *
 * The controller uses a shift register that outputs button states
 * one bit at a time when read via $4016 (controller 1) or $4017 (controller 2)
 */
class Controller
{
    /**
     * Button bit positions in the shift register
     */
    public const BUTTON_A      = 0x01;  // Bit 0
    public const BUTTON_B      = 0x02;  // Bit 1
    public const BUTTON_SELECT = 0x04;  // Bit 2
    public const BUTTON_START  = 0x08;  // Bit 3
    public const BUTTON_UP     = 0x10;  // Bit 4
    public const BUTTON_DOWN   = 0x20;  // Bit 5
    public const BUTTON_LEFT   = 0x40;  // Bit 6
    public const BUTTON_RIGHT  = 0x80;  // Bit 7

    /**
     * Current button states (1 = pressed, 0 = released)
     * Stored as a bitmask
     *
     * @var int
     */
    private int $buttonStates = 0x00;

    /**
     * Shift register that holds button states during serial read
     *
     * @var int
     */
    private int $shiftRegister = 0x00;

    /**
     * Strobe state (when high, continuously reload shift register)
     *
     * @var bool
     */
    private bool $strobe = false;

    /**
     * Set button state
     *
     * @param int $button Button constant (BUTTON_A, BUTTON_B, etc.)
     * @param bool $pressed True if pressed, false if released
     */
    public function setButton(int $button, bool $pressed): void
    {
        if ($pressed) {
            $this->buttonStates |= $button;
        } else {
            $this->buttonStates &= ~$button;
        }
    }

    /**
     * Set multiple button states from a bitmask
     *
     * @param int $states Bitmask of button states
     */
    public function setButtonStates(int $states): void
    {
        $this->buttonStates = $states & 0xFF;
    }

    /**
     * Get current button states as bitmask
     *
     * @return int
     */
    public function getButtonStates(): int
    {
        return $this->buttonStates;
    }

    /**
     * Check if a specific button is pressed
     *
     * @param int $button Button constant
     * @return bool
     */
    public function isButtonPressed(int $button): bool
    {
        return ($this->buttonStates & $button) !== 0;
    }

    /**
     * Write to controller (strobe)
     *
     * Writing 1 then 0 to $4016 causes the controller to latch
     * the current button states into the shift register
     *
     * @param int $value Value written to $4016
     */
    public function write(int $value): void
    {
        $strobeNew = ($value & 0x01) !== 0;

        // Detect falling edge (strobe goes from 1 to 0)
        if ($this->strobe && !$strobeNew) {
            // Latch button states into shift register
            $this->shiftRegister = $this->buttonStates;
        }

        $this->strobe = $strobeNew;

        // While strobe is high, continuously reload shift register
        if ($this->strobe) {
            $this->shiftRegister = $this->buttonStates;
        }
    }

    /**
     * Read from controller (shift register)
     *
     * Returns the current bit from the shift register (bit 0),
     * then shifts the register right for the next read
     *
     * Format: 0x40 | (button_state & 0x01)
     * Bits 7-1 are open bus, bit 0 is the button state
     *
     * @return int Value to return when reading $4016/$4017
     */
    public function read(): int
    {
        // Get current bit (LSB)
        $bit = $this->shiftRegister & 0x01;

        // Shift register right for next read
        if (!$this->strobe) {
            $this->shiftRegister >>= 1;
            // After 8 reads, register becomes 0, reads return 1 (open bus)
            $this->shiftRegister |= 0x80;
        }

        // Return bit 0 (other bits are open bus, typically 0x40)
        return 0x40 | $bit;
    }

    /**
     * Reset controller to initial state
     */
    public function reset(): void
    {
        $this->buttonStates = 0x00;
        $this->shiftRegister = 0x00;
        $this->strobe = false;
    }
}
