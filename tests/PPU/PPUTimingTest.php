<?php

declare(strict_types=1);

namespace tests\PPU;

use andrewthecoder\nes\PPU\PPU;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PPU timing system (scanline/cycle advancement)
 *
 * The PPU operates at 3x CPU speed with the following frame structure:
 * - 262 scanlines per frame (NTSC)
 * - 341 cycles per scanline
 * - Total: 89,342 cycles (89,341 on odd frames with rendering enabled)
 *
 * Scanline breakdown:
 * -1 (261): Pre-render
 * 0-239: Visible
 * 240: Post-render
 * 241-260: VBlank
 */
class PPUTimingTest extends TestCase
{
    private PPU $ppu;

    protected function setUp(): void
    {
        $this->ppu = new PPU();
    }

    // ========================================================================
    // Basic Timing Tests
    // ========================================================================

    public function test_ppu_starts_at_pre_render_scanline(): void
    {
        // PPU starts at pre-render scanline (-1), beginning of frame
        $this->assertEquals(-1, $this->ppu->getScanline());
        $this->assertEquals(0, $this->ppu->getCycle());
        $this->assertEquals(0, $this->ppu->getFrameCount());
    }

    public function test_clock_advances_cycle(): void
    {
        $this->ppu->clock();
        $this->assertEquals(-1, $this->ppu->getScanline());
        $this->assertEquals(1, $this->ppu->getCycle());
    }

    public function test_cycle_wraps_to_next_scanline_at_341(): void
    {
        // Advance to end of scanline
        for ($i = 0; $i < 341; $i++) {
            $this->ppu->clock();
        }

        // Should be on scanline 0 (after pre-render), cycle 0
        $this->assertEquals(0, $this->ppu->getScanline());
        $this->assertEquals(0, $this->ppu->getCycle());
    }

    public function test_scanline_advances_through_frame(): void
    {
        // Start at scanline -1 (pre-render)
        $this->assertEquals(-1, $this->ppu->getScanline());

        // Advance one full scanline
        for ($i = 0; $i < 341; $i++) {
            $this->ppu->clock();
        }

        // Should be on scanline 0 (first visible)
        $this->assertEquals(0, $this->ppu->getScanline());
    }

    // ========================================================================
    // Frame Boundary Tests
    // ========================================================================

    public function test_frame_wraps_to_pre_render_scanline(): void
    {
        // Advance one complete frame (262 scanlines: -1 to 260)
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        // Should wrap back to scanline -1 (pre-render)
        $this->assertEquals(-1, $this->ppu->getScanline());
        $this->assertEquals(0, $this->ppu->getCycle());
    }

    public function test_frame_complete_flag_set_at_end_of_frame(): void
    {
        $this->assertFalse($this->ppu->isFrameComplete());

        // Run one complete frame (262 scanlines * 341 cycles)
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        // Frame should be complete
        $this->assertTrue($this->ppu->isFrameComplete());
    }

    public function test_frame_complete_flag_can_be_cleared(): void
    {
        // Complete a frame
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        $this->assertTrue($this->ppu->isFrameComplete());

        $this->ppu->clearFrameComplete();

        $this->assertFalse($this->ppu->isFrameComplete());
    }

    public function test_frame_counter_increments(): void
    {
        $this->assertEquals(0, $this->ppu->getFrameCount());

        // Complete one frame (262 scanlines)
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        $this->assertEquals(1, $this->ppu->getFrameCount());

        // Complete another frame
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        $this->assertEquals(2, $this->ppu->getFrameCount());
    }

    // ========================================================================
    // VBlank Tests
    // ========================================================================

    public function test_vblank_flag_set_at_scanline_241_cycle_1(): void
    {
        // Advance from -1 to scanline 241, cycle 0 (242 scanlines total)
        for ($scanline = 0; $scanline < 242; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        // VBlank should not be set yet
        $vblankBeforeCycle1 = $this->ppu->cpuRead(0x02) & 0x80;
        $this->assertEquals(0, $vblankBeforeCycle1);

        // Advance to cycle 1
        $this->ppu->clock();

        // VBlank should now be set
        $vblankAfterCycle1 = $this->ppu->cpuRead(0x02) & 0x80;
        $this->assertEquals(0x80, $vblankAfterCycle1);
    }

    public function test_nmi_triggered_when_vblank_starts_and_nmi_enabled(): void
    {
        // Enable NMI
        $this->ppu->cpuWrite(0x00, 0x80); // PPUCTRL bit 7 = NMI enable

        $this->assertFalse($this->ppu->hasNMI());

        // Advance to scanline 241, cycle 1
        for ($scanline = 0; $scanline < 242; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }
        $this->ppu->clock();

        // NMI should be triggered
        $this->assertTrue($this->ppu->hasNMI());
    }

    public function test_nmi_not_triggered_when_nmi_disabled(): void
    {
        // NMI disabled (default)
        $this->assertFalse($this->ppu->hasNMI());

        // Advance to scanline 241, cycle 1
        for ($scanline = 0; $scanline < 242; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }
        $this->ppu->clock();

        // NMI should NOT be triggered
        $this->assertFalse($this->ppu->hasNMI());
    }

    public function test_nmi_flag_can_be_cleared(): void
    {
        // Enable NMI and trigger it
        $this->ppu->cpuWrite(0x00, 0x80);

        for ($scanline = 0; $scanline < 242; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }
        $this->ppu->clock();

        $this->assertTrue($this->ppu->hasNMI());

        $this->ppu->clearNMI();

        $this->assertFalse($this->ppu->hasNMI());
    }

    // ========================================================================
    // Pre-render Scanline Tests
    // ========================================================================

    public function test_pre_render_clears_vblank_flag(): void
    {
        // Set vblank by advancing to scanline 241
        for ($scanline = 0; $scanline < 242; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }
        $this->ppu->clock();

        // VBlank should be set
        $this->assertNotEquals(0, $this->ppu->cpuRead(0x02) & 0x80);

        // Advance to pre-render scanline, cycle 1 (complete rest of frame)
        for ($scanline = 242; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }
        $this->ppu->clock(); // Cycle 1 of scanline -1

        // VBlank should be cleared
        $this->assertEquals(0, $this->ppu->cpuRead(0x02) & 0x80);
    }

    public function test_pre_render_clears_sprite_flags(): void
    {
        // We can't easily set these flags without rendering,
        // but we can test that the clear operation happens at the right time

        // Advance to pre-render scanline, cycle 1 (complete one frame)
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }
        $this->ppu->clock(); // Cycle 1 of scanline -1

        // Read status
        $status = $this->ppu->cpuRead(0x02);

        // Bits 5-7 should all be clear
        $this->assertEquals(0, $status & 0xE0);
    }

    public function test_frame_complete_cleared_on_pre_render(): void
    {
        // Complete a frame
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        $this->assertTrue($this->ppu->isFrameComplete());

        // Advance to cycle 1 of pre-render (new frame)
        $this->ppu->clock();

        // Frame complete should be cleared
        $this->assertFalse($this->ppu->isFrameComplete());
    }

    // ========================================================================
    // Odd Frame Skip Tests
    // ========================================================================

    public function test_odd_frame_skips_cycle_0_when_rendering_enabled(): void
    {
        // Enable rendering
        $this->ppu->cpuWrite(0x01, 0x08); // PPUMASK bit 3 = show background

        // Complete first frame (frame 0 - even, 262 scanlines)
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        // Now on frame 1 (odd), scanline -1
        $this->assertEquals(1, $this->ppu->getFrameCount());
        $this->assertEquals(-1, $this->ppu->getScanline());

        // Complete pre-render scanline
        for ($cycle = 0; $cycle < 341; $cycle++) {
            $this->ppu->clock();
        }

        // Should be on scanline 0, but cycle should be 1 (skipped cycle 0)
        $this->assertEquals(0, $this->ppu->getScanline());
        $this->assertEquals(1, $this->ppu->getCycle());
    }

    public function test_odd_frame_does_not_skip_when_rendering_disabled(): void
    {
        // Rendering disabled (default)

        // Complete first frame (262 scanlines)
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        // Now on frame 1 (odd)
        $this->assertEquals(1, $this->ppu->getFrameCount());

        // Complete pre-render scanline
        for ($cycle = 0; $cycle < 341; $cycle++) {
            $this->ppu->clock();
        }

        // Should be on scanline 0, cycle 0 (no skip when rendering disabled)
        $this->assertEquals(0, $this->ppu->getScanline());
        $this->assertEquals(0, $this->ppu->getCycle());
    }

    public function test_even_frame_never_skips_cycle_0(): void
    {
        // Enable rendering
        $this->ppu->cpuWrite(0x01, 0x08);

        // Start on frame 0 (even), complete pre-render scanline
        for ($cycle = 0; $cycle < 341; $cycle++) {
            $this->ppu->clock();
        }

        // Should be on scanline 0, cycle 0 (no skip on even frames)
        $this->assertEquals(0, $this->ppu->getScanline());
        $this->assertEquals(0, $this->ppu->getCycle());
    }

    // ========================================================================
    // Frame Cycle Count Tests
    // ========================================================================

    public function test_even_frame_has_89342_cycles(): void
    {
        $cycleCount = 0;

        // Count cycles in frame 0 (even)
        do {
            $this->ppu->clock();
            $cycleCount++;
        } while (!$this->ppu->isFrameComplete());

        // Even frame: 262 scanlines * 341 cycles = 89,342 cycles
        $this->assertEquals(89342, $cycleCount);
    }

    public function test_odd_frame_with_rendering_has_89341_cycles(): void
    {
        // Enable rendering
        $this->ppu->cpuWrite(0x01, 0x08);

        // Complete frame 0 (even, 262 scanlines)
        for ($scanline = 0; $scanline < 262; $scanline++) {
            for ($cycle = 0; $cycle < 341; $cycle++) {
                $this->ppu->clock();
            }
        }

        $this->ppu->clearFrameComplete();

        $cycleCount = 0;

        // Count cycles in frame 1 (odd)
        do {
            $this->ppu->clock();
            $cycleCount++;
        } while (!$this->ppu->isFrameComplete());

        // Odd frame with rendering: 89,341 cycles (1 cycle skipped)
        $this->assertEquals(89341, $cycleCount);
    }
}
