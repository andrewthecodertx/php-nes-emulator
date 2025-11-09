# NESdev Wiki Comparison - Implementation Status

## Summary

Compared PHP NES emulator implementation against official NESdev wiki documentation for PPU rendering and frame timing. Found that the **core timing and NMI systems are implemented correctly**.

## PPU Timing - ✅ CORRECT

### Scanlines Per Frame
- **Spec**: 262 scanlines (NTSC)
  - Scanline -1 (261): Pre-render
  - Scanlines 0-239: Visible
  - Scanline 240: Post-render
  - Scanlines 241-260: VBlank

- **PHP Implementation**: ✅ Correct
  - All 262 scanlines execute
  - 341 cycles per scanline
  - Total: 89,341-89,342 cycles per frame (matches spec)

### VBlank Timing
- **Spec**: VBlank flag sets at scanline 241, cycle 1
- **PHP Implementation**: ✅ Correct
  - Confirmed via logging: VBlank sets at exactly scanline 241, cycle 1

### NMI Timing
- **Spec**: NMI triggers when VBlank sets and PPUCTRL bit 7 is enabled
- **PHP Implementation**: ✅ Correct
  - NMI flag sets at scanline 241, cycle 1 when enabled
  - CPU requestNMI() called correctly

### Pre-render Scanline
- **Spec**: Scanline -1 (261) clears flags at cycle 1
- **PHP Implementation**: ✅ Correct
  - VBlank, sprite 0 hit, sprite overflow cleared at scanline -1, cycle 1

### Odd Frame Skip
- **Spec**: Odd frames skip cycle 0 of scanline 0 when rendering enabled
- **PHP Implementation**: ✅ Correct
  - Code at line 938 implements this correctly

## Background Rendering - ✅ CORRECT

### 8-Cycle Fetch Pattern
- **Spec**: 4 fetches over 8 cycles (nametable, attribute, pattern low, pattern high)
- **PHP Implementation**: ✅ Correct
  - Cycles 1-256 and 321-336 perform tile fetches
  - 8-cycle pattern implemented with switch statement

### Shifter System
- **Spec**: 16-bit shifters for pattern and attribute data
- **PHP Implementation**: ✅ Correct
  - `bgShifterPatternLo/Hi`: 16-bit pattern shifters
  - `bgShifterAttribLo/Hi`: 16-bit attribute shifters
  - Load on cycle 0 of each 8-cycle window
  - Shift left every cycle

### Scroll Increment
- **Spec**: Increment X at end of each tile fetch (cycle 7 of 8-cycle pattern)
- **PHP Implementation**: ✅ Correct
  - incrementX() called at case 7

### Y Transfer
- **Spec**: Transfer Y from temp to active at cycles 280-304 of pre-render scanline
- **PHP Implementation**: ✅ Correct
  - Code at line 1058 implements this

## What's Working vs Not Working

### ✅ Working Games
- **Donkey Kong**: Full functionality
  - Initializes palette automatically
  - Rendering works
  - Controller input works
  - NMI fires correctly

### ❌ Not Working Games
- **Super Mario Bros**: Black screen
  - NMI fires correctly ✅
  - Nametable has data ✅
  - CHR-ROM has data ✅
  - **Palette RAM: all zeros** ❌

## Root Cause Analysis

Since the PPU timing, NMI system, and background rendering are all correct, the issue with Super Mario must be:

1. **CPU Emulation Issue**
   - Game's NMI handler runs but doesn't write to palette
   - Possible CPU instruction bug preventing writes
   - Mapper 1 (MMC1) might have bugs preventing code execution

2. **PPU Register Handling**
   - PPUADDR/PPUDATA might not route writes correctly to palette RAM
   - Address increment might be wrong
   - Write latch might be stuck

3. **Mapper 1 (MMC1) Implementation**
   - Super Mario uses MMC1, Donkey Kong uses NROM
   - Serial write interface might have bugs
   - Bank switching might prevent game code from running

## Recommendations

### Immediate Testing

1. **Test Other MMC1 Games**
   ```php
   // Try these ROMs (all use Mapper 1):
   - Metroid
   - Legend of Zelda
   - Mega Man 2
   ```
   If they ALL show black screens, it's an MMC1 bug.

2. **Test Other NROM Games** (Mapper 0)
   ```php
   // Try these ROMs:
   - Balloon Fight
   - Ice Climber
   - Excitebike
   ```
   If they work like Donkey Kong, confirms MMC1 is the issue.

3. **Add Palette Write Logging**
   ```php
   // In PPU::ppuWrite()
   if ($address >= 0x3F00 && $address <= 0x3F1F) {
       error_log(sprintf("Palette write: \$%04X = \$%02X", $address, $data));
   }
   ```
   Check if Super Mario EVER attempts to write to palette RAM.

### Long-term Fixes

1. **CPU Instruction Test ROM**
   - Run nestest.nes and verify all instructions pass
   - Compare execution trace with known-good emulator

2. **Mapper 1 Verification**
   - Review MMC1 serial write protocol
   - Verify bank switching logic
   - Test with simpler MMC1 games

3. **PPU Register Test ROM**
   - Run blargg's PPU tests
   - Verify PPUADDR/PPUDATA behavior

## Conclusion

**The NES emulator's core PPU timing and NMI systems are correctly implemented** according to NESdev wiki specifications. The issue preventing Super Mario from working is likely in:

1. Mapper 1 (MMC1) implementation
2. CPU instruction accuracy
3. PPU register (PPUADDR/PPUDATA) handling

**Donkey Kong works perfectly** because it uses simpler Mapper 0 (NROM) and doesn't rely on complex mapper features.

## References

- ✅ PPU Rendering: https://www.nesdev.org/wiki/PPU_rendering
- ✅ PPU Frame Timing: https://www.nesdev.org/wiki/PPU_frame_timing
- Analysis documents:
  - `NMI_BUG_INVESTIGATION.md` - NMI system verification
  - `SUPERMARIO_DEBUG_FINDINGS.md` - Super Mario palette issue
  - `CONTROLLER_IMPLEMENTATION.md` - Controller system (working)
