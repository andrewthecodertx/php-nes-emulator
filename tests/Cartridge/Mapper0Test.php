<?php

declare(strict_types=1);

namespace tests\Cartridge;

use andrewthecoder\nes\Cartridge\Cartridge;
use andrewthecoder\nes\Cartridge\Mapper0;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Mapper 0 (NROM)
 */
class Mapper0Test extends TestCase
{
    public function test_donkey_kong_mapper(): void
    {
        $cartridge = Cartridge::fromFile(__DIR__ . '/../../roms/donkeykong_nes.rom');
        $mapper = new Mapper0($cartridge);

        // Test that mapper returns correct mirroring
        $this->assertEquals(0, $mapper->getMirroring()); // Horizontal

        // Test that PRG-ROM is readable from $8000-$FFFF
        // Donkey Kong has 16KB, so it should be mirrored at $C000
        $value1 = $mapper->cpuRead(0x8000);
        $value2 = $mapper->cpuRead(0xC000); // Should be same as $8000 (mirrored)
        $this->assertEquals($value1, $value2);

        // Test CHR-ROM is readable
        $chrValue = $mapper->ppuRead(0x0000);
        $this->assertIsInt($chrValue);
        $this->assertGreaterThanOrEqual(0, $chrValue);
        $this->assertLessThanOrEqual(255, $chrValue);
    }

    public function test_16kb_prg_rom_mirroring(): void
    {
        // Create cartridge with 16KB PRG-ROM
        $header = "NES\x1A\x01\x01\x00\x00" . str_repeat("\x00", 8);
        $prgRom = '';
        for ($i = 0; $i < 16384; $i++) {
            $prgRom .= chr($i & 0xFF);
        }
        $chrRom = str_repeat("\x00", 8192);

        $cartridge = Cartridge::fromData($header . $prgRom . $chrRom);
        $mapper = new Mapper0($cartridge);

        // First bank: $8000-$BFFF
        $this->assertEquals(0x00, $mapper->cpuRead(0x8000));
        $this->assertEquals(0x01, $mapper->cpuRead(0x8001));
        $this->assertEquals(0xFF, $mapper->cpuRead(0x80FF));

        // Second bank: $C000-$FFFF (mirrored)
        $this->assertEquals(0x00, $mapper->cpuRead(0xC000));
        $this->assertEquals(0x01, $mapper->cpuRead(0xC001));
        $this->assertEquals(0xFF, $mapper->cpuRead(0xC0FF));

        // Verify mirror
        $this->assertEquals($mapper->cpuRead(0x8000), $mapper->cpuRead(0xC000));
        $this->assertEquals($mapper->cpuRead(0x8100), $mapper->cpuRead(0xC100));
    }

    public function test_32kb_prg_rom_no_mirroring(): void
    {
        // Create cartridge with 32KB PRG-ROM (2 banks)
        $header = "NES\x1A\x02\x01\x00\x00" . str_repeat("\x00", 8);

        // Bank 0: 0x00-0xFF pattern
        $prgRomBank0 = '';
        for ($i = 0; $i < 16384; $i++) {
            $prgRomBank0 .= chr($i & 0xFF);
        }

        // Bank 1: 0xAA pattern
        $prgRomBank1 = str_repeat("\xAA", 16384);

        $chrRom = str_repeat("\x00", 8192);

        $cartridge = Cartridge::fromData($header . $prgRomBank0 . $prgRomBank1 . $chrRom);
        $mapper = new Mapper0($cartridge);

        // First bank: $8000-$BFFF
        $this->assertEquals(0x00, $mapper->cpuRead(0x8000));
        $this->assertEquals(0x01, $mapper->cpuRead(0x8001));

        // Second bank: $C000-$FFFF (NOT mirrored, different data)
        $this->assertEquals(0xAA, $mapper->cpuRead(0xC000));
        $this->assertEquals(0xAA, $mapper->cpuRead(0xC001));

        // Verify NOT mirrored
        $this->assertNotEquals($mapper->cpuRead(0x8000), $mapper->cpuRead(0xC000));
    }

    public function test_chr_rom_read(): void
    {
        $header = "NES\x1A\x01\x01\x00\x00" . str_repeat("\x00", 8);
        $prgRom = str_repeat("\x00", 16384);

        // Create CHR-ROM with pattern
        $chrRom = '';
        for ($i = 0; $i < 8192; $i++) {
            $chrRom .= chr(($i >> 4) & 0xFF);
        }

        $cartridge = Cartridge::fromData($header . $prgRom . $chrRom);
        $mapper = new Mapper0($cartridge);

        // Test reading from CHR-ROM
        $this->assertEquals(0x00, $mapper->ppuRead(0x0000));
        $this->assertEquals(0x00, $mapper->ppuRead(0x000F));
        $this->assertEquals(0x01, $mapper->ppuRead(0x0010));
        $this->assertEquals(0x01, $mapper->ppuRead(0x001F));
        $this->assertEquals(0x02, $mapper->ppuRead(0x0020));
    }

    public function test_chr_ram_when_no_chr_rom(): void
    {
        // Create cartridge with no CHR-ROM (uses CHR-RAM instead)
        $header = "NES\x1A\x01\x00\x00\x00" . str_repeat("\x00", 8);
        $prgRom = str_repeat("\x00", 16384);

        $cartridge = Cartridge::fromData($header . $prgRom);
        $mapper = new Mapper0($cartridge);

        // CHR-RAM should be initialized to 0
        $this->assertEquals(0x00, $mapper->ppuRead(0x0000));

        // Should be writable
        $mapper->ppuWrite(0x0000, 0x42);
        $this->assertEquals(0x42, $mapper->ppuRead(0x0000));

        $mapper->ppuWrite(0x1FFF, 0xAA);
        $this->assertEquals(0xAA, $mapper->ppuRead(0x1FFF));
    }

    public function test_cpu_writes_are_ignored(): void
    {
        $cartridge = Cartridge::fromFile(__DIR__ . '/../../roms/donkeykong_nes.rom');
        $mapper = new Mapper0($cartridge);

        $originalValue = $mapper->cpuRead(0x8000);

        // Try to write (should be ignored)
        $mapper->cpuWrite(0x8000, 0xFF);

        // Value should not change
        $this->assertEquals($originalValue, $mapper->cpuRead(0x8000));
    }

    public function test_mirroring_modes(): void
    {
        // Horizontal mirroring
        $header1 = "NES\x1A\x01\x01\x00\x00" . str_repeat("\x00", 8);
        $rom1 = str_repeat("\x00", 16384) . str_repeat("\x00", 8192);
        $cart1 = Cartridge::fromData($header1 . $rom1);
        $mapper1 = new Mapper0($cart1);
        $this->assertEquals(0, $mapper1->getMirroring());

        // Vertical mirroring
        $header2 = "NES\x1A\x01\x01\x01\x00" . str_repeat("\x00", 8);
        $rom2 = str_repeat("\x00", 16384) . str_repeat("\x00", 8192);
        $cart2 = Cartridge::fromData($header2 . $rom2);
        $mapper2 = new Mapper0($cart2);
        $this->assertEquals(1, $mapper2->getMirroring());
    }
}
