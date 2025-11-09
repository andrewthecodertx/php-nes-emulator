<?php

declare(strict_types=1);

namespace tests\Bus;

use andrewthecoder\nes\Bus\NESBus;
use andrewthecoder\nes\PPU\PPU;
use andrewthecoder\nes\Cartridge\Cartridge;
use andrewthecoder\nes\Cartridge\Mapper0;
use PHPUnit\Framework\TestCase;

/**
 * Tests for NES Bus
 */
class NESBusTest extends TestCase
{
    private NESBus $bus;

    protected function setUp(): void
    {
        $ppu = new PPU();
        $cartridge = Cartridge::fromFile(__DIR__ . '/../../roms/donkeykong_nes.rom');
        $mapper = new Mapper0($cartridge);
        $this->bus = new NESBus($ppu, $mapper);
    }

    public function test_ram_read_write(): void
    {
        // Write to RAM
        $this->bus->write(0x0000, 0x42);
        $this->assertEquals(0x42, $this->bus->read(0x0000));

        $this->bus->write(0x07FF, 0xAA);
        $this->assertEquals(0xAA, $this->bus->read(0x07FF));
    }

    public function test_ram_mirroring(): void
    {
        // Write to $0000
        $this->bus->write(0x0000, 0x12);

        // Should be mirrored at $0800, $1000, $1800
        $this->assertEquals(0x12, $this->bus->read(0x0800));
        $this->assertEquals(0x12, $this->bus->read(0x1000));
        $this->assertEquals(0x12, $this->bus->read(0x1800));

        // Write to mirror should affect original
        $this->bus->write(0x0800, 0x34);
        $this->assertEquals(0x34, $this->bus->read(0x0000));
    }

    public function test_ppu_register_access(): void
    {
        // PPUSTATUS at $2002
        $status = $this->bus->read(0x2002);
        $this->assertIsInt($status);
        $this->assertGreaterThanOrEqual(0, $status);
        $this->assertLessThanOrEqual(255, $status);

        // PPUADDR at $2006 (write-only)
        $this->bus->write(0x2006, 0x20);
        $this->bus->write(0x2006, 0x00);

        // PPUDATA at $2007
        $this->bus->write(0x2007, 0x55);
        // Reading back would require proper PPUADDR setup
    }

    public function test_ppu_register_mirroring(): void
    {
        // PPU registers mirror every 8 bytes
        // Write to $2000 (PPUCTRL)
        $this->bus->write(0x2000, 0x80);

        // Writes to mirrors should affect the same register
        // But since registers are write-only, we can't verify by reading
        // Just verify no errors occur
        $this->bus->write(0x2008, 0x00); // Mirror of $2000
        $this->bus->write(0x3FF8, 0x00); // Another mirror

        $this->assertTrue(true); // No exceptions thrown
    }

    public function test_cartridge_read(): void
    {
        // Read from PRG-ROM at $8000
        $value = $this->bus->read(0x8000);
        $this->assertIsInt($value);
        $this->assertGreaterThanOrEqual(0, $value);
        $this->assertLessThanOrEqual(255, $value);

        // Read from $C000 (should be mirrored for 16KB ROM)
        $value2 = $this->bus->read(0xC000);
        $this->assertEquals($value, $value2); // Donkey Kong has 16KB, so it's mirrored
    }

    public function test_read_word(): void
    {
        // Write little-endian word
        $this->bus->write(0x0000, 0x34); // Low byte
        $this->bus->write(0x0001, 0x12); // High byte

        $word = $this->bus->readWord(0x0000);
        $this->assertEquals(0x1234, $word);
    }

    public function test_tick_advances_ppu(): void
    {
        $ppu = $this->bus->getPPU();

        $initialCycle = $ppu->getCycle();
        $initialScanline = $ppu->getScanline();

        // Tick the bus (PPU advances by 3 cycles per bus tick)
        $this->bus->tick();

        // PPU should have advanced by 3 cycles
        $newCycle = $ppu->getCycle();

        // Cycle should have increased (or wrapped to next scanline)
        $this->assertTrue(
            $newCycle == ($initialCycle + 3) ||
            ($newCycle < $initialCycle && $ppu->getScanline() == $initialScanline + 1)
        );
    }

    public function test_system_clock_increments(): void
    {
        $clock1 = $this->bus->getSystemClock();
        $this->bus->tick();
        $clock2 = $this->bus->getSystemClock();

        $this->assertEquals($clock1 + 1, $clock2);
    }

    public function test_reset_clears_ram(): void
    {
        // Write some data to RAM
        $this->bus->write(0x0000, 0xFF);
        $this->bus->write(0x0100, 0xAA);

        // Reset
        $this->bus->reset();

        // RAM should be cleared
        $this->assertEquals(0x00, $this->bus->read(0x0000));
        $this->assertEquals(0x00, $this->bus->read(0x0100));
    }

    public function test_oam_dma_transfer(): void
    {
        $ppu = $this->bus->getPPU();

        // Write test pattern to RAM at $0200
        for ($i = 0; $i < 256; $i++) {
            $this->bus->write(0x0200 + $i, $i & 0xFF);
        }

        // Trigger DMA transfer from $0200 to OAM
        $this->bus->write(0x4014, 0x02);

        // Verify OAM was updated
        for ($i = 0; $i < 256; $i++) {
            $this->assertEquals($i & 0xFF, $ppu->readOAM($i));
        }
    }
}
