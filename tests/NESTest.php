<?php

declare(strict_types=1);

namespace tests;

use andrewthecoder\nes\NES;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for main NES emulator class
 */
class NESTest extends TestCase
{
    public function test_load_donkey_kong(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        $this->assertInstanceOf(NES::class, $nes);

        // Verify components are initialized
        $this->assertNotNull($nes->getCPU());
        $this->assertNotNull($nes->getPPU());
        $this->assertNotNull($nes->getBus());
        $this->assertNotNull($nes->getMapper());
    }

    public function test_invalid_rom_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NES::fromROM('nonexistent.nes');
    }

    public function test_unsupported_mapper_throws_exception(): void
    {
        // Create ROM with unsupported mapper (e.g., mapper 99)
        // Mapper number is: (flags7 & 0xF0) | (flags6 >> 4)
        // For mapper 99: 99 = 0x63 = 0110 0011
        // flags6 = 0x30 (bits 7:4 = 0011)
        // flags7 = 0x60 (bits 7:4 = 0110)
        $header = "NES\x1A\x01\x01\x30\x60" . str_repeat("\x00", 8);
        $prgRom = str_repeat("\x00", 16384);
        $chrRom = str_repeat("\x00", 8192);

        $romData = $header . $prgRom . $chrRom;

        // Save to temp file
        $tempFile = sys_get_temp_dir() . '/test_mapper99.nes';
        file_put_contents($tempFile, $romData);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('not supported');
            NES::fromROM($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_cpu_step(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        // Step CPU (execute one instruction) - should not throw
        $nes->step();

        // If we get here, step() worked without errors
        $this->assertTrue(true);
    }

    public function test_run_instructions(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        // Run 100 CPU instructions - should not throw
        $nes->runInstructions(100);

        // If we get here, runInstructions() worked without errors
        $this->assertTrue(true);
    }

    public function test_run_frame(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        // Run until one frame completes
        $nes->runFrame();

        // Frame should be marked as complete
        $this->assertTrue($nes->getPPU()->isFrameComplete());

        // Frame buffer should be populated
        $frameBuffer = $nes->getFrameBuffer();
        $this->assertCount(256 * 240, $frameBuffer);
    }

    public function test_frame_buffer_access(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        // Get frame buffer as array of pixels
        $frameBuffer = $nes->getFrameBuffer();
        $this->assertIsArray($frameBuffer);
        $this->assertCount(61440, $frameBuffer); // 256x240

        // First pixel should be [R, G, B]
        $this->assertIsArray($frameBuffer[0]);
        $this->assertCount(3, $frameBuffer[0]);

        // Get frame buffer as flat array
        $flatBuffer = $nes->getFrameBufferFlat();
        $this->assertIsArray($flatBuffer);
        $this->assertCount(184320, $flatBuffer); // 256x240x3
    }

    public function test_reset(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        // Run some instructions
        $nes->runInstructions(100);

        $clockBefore = $nes->getBus()->getSystemClock();

        // Reset
        $nes->reset();

        $clockAfter = $nes->getBus()->getSystemClock();

        // Clock should be reset to 0
        $this->assertEquals(0, $clockAfter);
        $this->assertLessThan($clockBefore, $clockAfter);
    }

    public function test_cpu_reads_from_rom(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        // CPU should be able to read from ROM at $8000
        $bus = $nes->getBus();
        $value = $bus->read(0x8000);

        $this->assertIsInt($value);
        $this->assertGreaterThanOrEqual(0, $value);
        $this->assertLessThanOrEqual(255, $value);
    }

    public function test_ppu_accesses_chr_rom(): void
    {
        $nes = NES::fromROM(__DIR__ . '/../roms/donkeykong_nes.rom');

        $ppu = $nes->getPPU();

        // PPU should be able to read from CHR-ROM
        $value = $ppu->ppuRead(0x0000);

        $this->assertIsInt($value);
        $this->assertGreaterThanOrEqual(0, $value);
        $this->assertLessThanOrEqual(255, $value);
    }
}
