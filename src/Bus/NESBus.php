<?php

declare(strict_types=1);

namespace andrewthecoder\nes\Bus;

use andrewthecoder\Core\BusInterface;
use andrewthecoder\MOS6502\CPU;
use andrewthecoder\nes\PPU\PPU;
use andrewthecoder\nes\APU\APU;
use andrewthecoder\nes\Cartridge\MapperInterface;
use andrewthecoder\nes\Input\Controller;

/**
 * NES Bus
 *
 * Connects CPU, RAM, PPU, and Cartridge
 *
 * Memory Map:
 * $0000-$07FF: 2KB internal RAM
 * $0800-$1FFF: Mirrors of RAM (3x)
 * $2000-$2007: PPU registers
 * $2008-$3FFF: Mirrors of PPU registers
 * $4000-$4017: APU and I/O registers (not implemented yet)
 * $4020-$FFFF: Cartridge space (mapper)
 */
class NESBus implements BusInterface
{
    /**
     * 2KB internal RAM
     *
     * @var array<int>
     */
    private array $ram = [];

    /**
     * PPU (Picture Processing Unit)
     *
     * @var PPU
     */
    private PPU $ppu;

    /**
     * APU (Audio Processing Unit)
     *
     * @var APU
     */
    private APU $apu;

    /**
     * Cartridge mapper
     *
     * @var MapperInterface
     */
    private MapperInterface $mapper;

    /**
     * Controller 1 (Player 1)
     *
     * @var Controller
     */
    private Controller $controller1;

    /**
     * Controller 2 (Player 2)
     *
     * @var Controller
     */
    private Controller $controller2;

    /**
     * CPU instance (for triggering NMI)
     *
     * @var CPU|null
     */
    private ?CPU $cpu = null;

    /**
     * System cycle counter
     *
     * @var int
     */
    private int $systemClock = 0;

    /**
     * Construct NES Bus
     *
     * @param PPU $ppu
     * @param APU $apu
     * @param MapperInterface $mapper
     */
    public function __construct(PPU $ppu, APU $apu, MapperInterface $mapper)
    {
        $this->ppu = $ppu;
        $this->apu = $apu;
        $this->mapper = $mapper;
        $this->controller1 = new Controller();
        $this->controller2 = new Controller();
        $this->reset();
    }

    /**
     * Reset bus and all connected devices
     */
    public function reset(): void
    {
        // Initialize RAM to zero
        $this->ram = array_fill(0, 2048, 0x00);

        $this->ppu->reset();
        $this->apu->reset();
        $this->mapper->reset();
        $this->controller1->reset();
        $this->controller2->reset();
        $this->systemClock = 0;
    }

    /**
     * Read byte from address
     *
     * @param int $address 0x0000-0xFFFF
     * @return int 0x00-0xFF
     */
    public function read(int $address): int
    {
        $address &= 0xFFFF;

        // RAM: $0000-$1FFF (2KB mirrored 4 times)
        if ($address < 0x2000) {
            return $this->ram[$address & 0x07FF];
        }

        // PPU Registers: $2000-$3FFF (8 bytes mirrored)
        if ($address < 0x4000) {
            return $this->ppu->cpuRead($address & 0x2007);
        }

        // APU and I/O: $4000-$4017
        if ($address < 0x4020) {
            // APU registers: $4000-$4015
            if ($address >= 0x4000 && $address <= 0x4015) {
                return $this->apu->read($address);
            }

            // Controller 1: $4016
            if ($address == 0x4016) {
                return $this->controller1->read();
            }

            // Controller 2 / APU Frame Counter: $4017
            if ($address == 0x4017) {
                return $this->controller2->read();
            }

            // Other registers
            return 0x00;
        }

        // Cartridge space: $4020-$FFFF
        return $this->mapper->cpuRead($address);
    }

    /**
     * Read 16-bit word (little-endian)
     *
     * @param int $address 0x0000-0xFFFF
     * @return int 0x0000-0xFFFF
     */
    public function readWord(int $address): int
    {
        $low = $this->read($address);
        $high = $this->read($address + 1);
        return ($high << 8) | $low;
    }

    /**
     * Write byte to address
     *
     * @param int $address 0x0000-0xFFFF
     * @param int $value 0x00-0xFF
     */
    public function write(int $address, int $value): void
    {
        $address &= 0xFFFF;
        $value &= 0xFF;

        // RAM: $0000-$1FFF (2KB mirrored 4 times)
        if ($address < 0x2000) {
            $this->ram[$address & 0x07FF] = $value;
            return;
        }

        // PPU Registers: $2000-$3FFF (8 bytes mirrored)
        if ($address < 0x4000) {
            $this->ppu->cpuWrite($address & 0x2007, $value);
            return;
        }

        // APU and I/O: $4000-$4017
        if ($address < 0x4020) {
            // APU registers: $4000-$4013, $4015, $4017
            if (($address >= 0x4000 && $address <= 0x4013) || $address == 0x4015 || $address == 0x4017) {
                $this->apu->write($address, $value);
                return;
            }

            // $4014: OAMDMA (special handling)
            if ($address === 0x4014) {
                $this->dmaTransfer($value);
                return;
            }

            // $4016: Controller strobe (affects both controllers)
            if ($address == 0x4016) {
                $this->controller1->write($value);
                $this->controller2->write($value);
                return;
            }

            // Other registers
            return;
        }

        // Cartridge space: $4020-$FFFF
        $this->mapper->cpuWrite($address, $value);
    }

    /**
     * OAM DMA Transfer
     * Copies 256 bytes from CPU memory to OAM
     *
     * @param int $page High byte of source address (0x00-0xFF)
     */
    private function dmaTransfer(int $page): void
    {
        $baseAddress = $page << 8;

        for ($i = 0; $i < 256; $i++) {
            $data = $this->read($baseAddress + $i);
            $this->ppu->writeOAM($i, $data);
        }

        // DMA takes 513 or 514 CPU cycles (depending on odd/even alignment)
        // For now, we'll just advance the system clock
        $this->systemClock += 513;
    }

    /**
     * Update all peripherals (PPU)
     * Called once per CPU cycle
     */
    public function tick(): void
    {
        // PPU runs at 3x CPU speed
        $this->ppu->clock();
        $this->ppu->clock();
        $this->ppu->clock();

        // APU runs at CPU speed
        $this->apu->clock();

        // Check for PPU NMI and deliver to CPU
        if ($this->ppu->hasNMI()) {
            if ($this->cpu !== null) {
                $this->cpu->requestNMI();
            }
            $this->ppu->clearNMI();
        }

        // Check for APU IRQ (rare, but some games use it)
        if ($this->apu->hasIRQ()) {
            if ($this->cpu !== null) {
                $this->cpu->requestIRQ();
            }
            $this->apu->clearIRQ();
        }

        $this->systemClock++;
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
     * Get APU instance
     *
     * @return APU
     */
    public function getAPU(): APU
    {
        return $this->apu;
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
     * Get system clock cycle count
     *
     * @return int
     */
    public function getSystemClock(): int
    {
        return $this->systemClock;
    }

    /**
     * Get controller 1 instance
     *
     * @return Controller
     */
    public function getController1(): Controller
    {
        return $this->controller1;
    }

    /**
     * Get controller 2 instance
     *
     * @return Controller
     */
    public function getController2(): Controller
    {
        return $this->controller2;
    }

    /**
     * Set CPU instance (for NMI delivery)
     *
     * @param CPU $cpu
     */
    public function setCPU(CPU $cpu): void
    {
        $this->cpu = $cpu;
    }
}
