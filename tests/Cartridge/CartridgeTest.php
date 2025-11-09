<?php

declare(strict_types=1);

namespace tests\Cartridge;

use andrewthecoder\nes\Cartridge\Cartridge;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Cartridge (ROM loader)
 */
class CartridgeTest extends TestCase
{
    public function test_load_donkey_kong_rom(): void
    {
        $cartridge = Cartridge::fromFile(__DIR__ . '/../../roms/donkeykong_nes.rom');

        // Donkey Kong should have 16KB PRG-ROM
        $this->assertEquals(16384, $cartridge->getPrgRomSize());

        // Donkey Kong should have 8KB CHR-ROM
        $this->assertEquals(8192, $cartridge->getChrRomSize());

        // Donkey Kong uses Mapper 0 (NROM)
        $this->assertEquals(0, $cartridge->getMapperNumber());

        // Donkey Kong uses horizontal mirroring
        $this->assertTrue($cartridge->isHorizontalMirroring());
        $this->assertFalse($cartridge->isVerticalMirroring());
        $this->assertEquals(0, $cartridge->getMirroring());
    }

    public function test_invalid_file_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ROM file not found');
        Cartridge::fromFile('nonexistent.nes');
    }

    public function test_invalid_magic_throws_exception(): void
    {
        $invalidData = "INVALID_MAGIC_NUMBER";
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid iNES header');
        Cartridge::fromData($invalidData);
    }

    public function test_file_too_small_throws_exception(): void
    {
        $tooSmall = "NES\x1A";
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ROM too small');
        Cartridge::fromData($tooSmall);
    }

    public function test_parse_minimal_valid_rom(): void
    {
        // Create minimal valid iNES ROM
        // Header: 16 bytes
        // PRG-ROM: 1 x 16KB = 16384 bytes
        // CHR-ROM: 0 x 8KB = 0 bytes

        $header = "NES\x1A" .     // Magic
            "\x01" .               // 1 x 16KB PRG-ROM
            "\x00" .               // 0 x 8KB CHR-ROM
            "\x00" .               // Flags 6: mapper 0, horizontal mirroring
            "\x00" .               // Flags 7: mapper 0
            str_repeat("\x00", 8); // Padding

        $prgRom = str_repeat("\xFF", 16384); // Dummy PRG-ROM data

        $romData = $header . $prgRom;

        $cartridge = Cartridge::fromData($romData);

        $this->assertEquals(16384, $cartridge->getPrgRomSize());
        $this->assertEquals(0, $cartridge->getChrRomSize());
        $this->assertEquals(0, $cartridge->getMapperNumber());
        $this->assertTrue($cartridge->isHorizontalMirroring());
    }

    public function test_parse_rom_with_vertical_mirroring(): void
    {
        $header = "NES\x1A" .
            "\x01" .               // 1 x 16KB PRG-ROM
            "\x01" .               // 1 x 8KB CHR-ROM
            "\x01" .               // Flags 6: mapper 0, vertical mirroring (bit 0 = 1)
            "\x00" .               // Flags 7
            str_repeat("\x00", 8);

        $prgRom = str_repeat("\xAA", 16384);
        $chrRom = str_repeat("\x55", 8192);

        $romData = $header . $prgRom . $chrRom;

        $cartridge = Cartridge::fromData($romData);

        $this->assertTrue($cartridge->isVerticalMirroring());
        $this->assertFalse($cartridge->isHorizontalMirroring());
        $this->assertEquals(1, $cartridge->getMirroring());
    }

    public function test_parse_mapper_number(): void
    {
        // Test mapper 1 (MMC1)
        // Lower nibble in flags6[7:4], upper nibble in flags7[7:4]
        // Mapper 1 = 0x01, so flags6[7:4] = 0x1, flags7[7:4] = 0x0

        $header = "NES\x1A" .
            "\x01" .               // 1 x 16KB PRG-ROM
            "\x01" .               // 1 x 8KB CHR-ROM
            "\x10" .               // Flags 6: mapper lower nibble = 1
            "\x00" .               // Flags 7: mapper upper nibble = 0
            str_repeat("\x00", 8);

        $prgRom = str_repeat("\x00", 16384);
        $chrRom = str_repeat("\x00", 8192);

        $romData = $header . $prgRom . $chrRom;

        $cartridge = Cartridge::fromData($romData);

        $this->assertEquals(1, $cartridge->getMapperNumber());
    }

    public function test_prg_rom_data_integrity(): void
    {
        $header = "NES\x1A" .
            "\x01" .               // 1 x 16KB PRG-ROM
            "\x00" .               // 0 CHR-ROM
            "\x00" .
            "\x00" .
            str_repeat("\x00", 8);

        // Create PRG-ROM with specific pattern
        $prgRom = '';
        for ($i = 0; $i < 16384; $i++) {
            $prgRom .= chr($i & 0xFF);
        }

        $romData = $header . $prgRom;

        $cartridge = Cartridge::fromData($romData);
        $loadedPrgRom = $cartridge->getPrgRom();

        // Verify first few bytes
        $this->assertEquals(0x00, $loadedPrgRom[0]);
        $this->assertEquals(0x01, $loadedPrgRom[1]);
        $this->assertEquals(0xFF, $loadedPrgRom[255]);
        $this->assertEquals(0x00, $loadedPrgRom[256]); // Wraps around
    }

    public function test_chr_rom_data_integrity(): void
    {
        $header = "NES\x1A" .
            "\x01" .               // 1 x 16KB PRG-ROM
            "\x01" .               // 1 x 8KB CHR-ROM
            "\x00" .
            "\x00" .
            str_repeat("\x00", 8);

        $prgRom = str_repeat("\x00", 16384);

        // Create CHR-ROM with specific pattern
        $chrRom = '';
        for ($i = 0; $i < 8192; $i++) {
            $chrRom .= chr(($i >> 4) & 0xFF);
        }

        $romData = $header . $prgRom . $chrRom;

        $cartridge = Cartridge::fromData($romData);
        $loadedChrRom = $cartridge->getChrRom();

        $this->assertEquals(8192, count($loadedChrRom));
        $this->assertEquals(0x00, $loadedChrRom[0]);
        $this->assertEquals(0x01, $loadedChrRom[16]);
    }
}
