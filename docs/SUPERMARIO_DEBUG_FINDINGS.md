# Super Mario Bros Debug Findings

## Problem Statement

Super Mario Bros shows only a black screen in the PHP NES emulator, while Donkey Kong works correctly. Both games are tested with the same emulator code, same initialization sequence, and same "forced rendering" approach.

## Investigation Summary

### What Works: Donkey Kong ✅

After 121 frames (120 initialization + 1 with forced rendering):
- **Palette RAM**: 14 non-zero entries
- **PPUMASK**: Rendering enabled automatically
- **Nametable**: Contains tile data
- **CHR-ROM**: Contains pattern data
- **Frame Buffer**: Multiple colors, valid graphics
- **Result**: Game displays correctly

### What Doesn't Work: Super Mario Bros ❌

After 121 frames (120 initialization + 1 with forced rendering):
- **Palette RAM**: ALL ZEROS (0/32 non-zero)
- **PPUMASK**: $00 or forced to $1E (doesn't matter - palette is empty)
- **Nametable**: 1920 non-zero tiles (has data!)
- **CHR-ROM**: 212/256 bytes non-zero (has data!)
- **Frame Buffer**: Single color RGB(0,0,0) - black
- **Result**: Black screen

### The Core Issue

**Super Mario's palette RAM never gets initialized.**

When the PPU tries to render tiles, it looks up colors in palette RAM. With all zeros:
```
Tile $24 with palette 0 → palette RAM[$00] = $00 → hardware palette[$00] = RGB(0,0,0) = BLACK
```

## Tests Performed

### Test 1: Basic Initialization
```bash
Run 11 frames → Force PPUMASK=$1E → Run 1 frame
Result: Palette all zeros ❌
```

### Test 2: Extended Initialization (Matching Go Emulator)
```bash
Run 120 frames → Force PPUMASK=$1E → Run 1 frame
Result: Palette all zeros ❌
```

### Test 3: START Button Held During Initialization
```bash
Press START → Run 120 frames → Check palette
Result: Palette all zeros ❌
```

### Test 4: START Button with Extended Hold
```bash
Press START → Run 100 frames → Release → Run 10 frames
Result: Palette all zeros ❌
```

### Test 5: No Forced Rendering
```bash
Run 120 frames → Don't force PPUMASK → Check palette
Result: Palette all zeros, PPUMASK=$00 ❌
```

## Comparison with Go Emulator

The Go NES emulator (at `/home/andrew/Projects/nes-emulator`) was analyzed and found to:

1. **Run 120 initialization frames** - Matches what PHP now does
2. **Support "forced rendering"** - Optional toggle (F key)
3. **Not require controller input** - Games initialize automatically
4. **Work with Super Mario Bros** - (Presumably, based on it being reference implementation)

The Go version's initialization:
```go
emulator.Reset()
for i := 0; i < 120; i++ {
    emulator.RunFrame()
}
// Optional: force rendering with ppuUnit.WriteCPURegister(0x2001, 0x1E)
```

This is identical to what the PHP version now does, yet PHP still shows black screen.

## Controller Polling Evidence

The controller hardware IS working:

**Frame 3 Controller Read**:
```
Strobe: Write $01, Write $00
Read 8 bits: 00010000
             ^^^^^^^^
             ABDSURLR

Bit 3 (START) = 1 ✅ Correct!
```

But after frame 3, the controller is never polled again. The game read START=1 but didn't respond to it.

## Data Inspection

### Super Mario Nametable (Frame 121)
```
Address $2000-$203F (first 64 bytes):
24 24 24 24 24 24 24 24 24 24 24 24 24 24 24 24
24 24 24 24 24 24 24 24 24 24 24 24 24 24 24 24
...

Tile ID $24 appears 1920 times → Game has loaded nametable data ✅
```

### Super Mario CHR-ROM
```
Address $0000-$003F (first 64 bytes):
03 0F 1F 1F 1C 24 26 66 00 00 00 00 1F 3F 3F 7F
E0 C0 80 FC 80 C0 00 20 00 20 60 00 F0 FC FE FE
...

212/256 bytes non-zero → Pattern data exists ✅
```

### Super Mario Palette RAM
```
Address $3F00-$3F0F (background palettes):
00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00

ALL ZEROS ❌
```

### Donkey Kong Palette RAM (for comparison)
```
Address $3F00-$3F0F (background palettes):
0F 2C 38 12 0F 27 27 27 0F 30 30 30 0F 00 00 00

14 non-zero entries ✅
```

## Possible Root Causes

### 1. Missing or Incorrect CPU Instruction Behavior
If certain 6502 instructions are implemented incorrectly, the game's initialization code might not execute properly. The CPU could be:
- Skipping writes to $3F00-$3F1F (palette RAM)
- Branching incorrectly during initialization
- Not handling flags correctly

**Test**: Run CPU test ROM (nestest.nes) and verify all instructions pass.

### 2. PPU Register Access Issues
The game writes to palette RAM via:
```
LDA #$3F
STA $2006  ; PPUADDR high byte
LDA #$00
STA $2006  ; PPUADDR low byte
LDA #$0F
STA $2007  ; PPUDATA (write to $3F00)
```

If PPUADDR isn't being set correctly, writes could go to wrong addresses.

**Test**: Log all $2006/$2007 writes and verify $3F00-$3F1F range is accessed.

### 3. PPU Internal State Machine
The PPU has internal registers (v, t, x, w) that control addressing. If these aren't implemented correctly:
- PPUADDR might not increment properly
- Address latch toggle (w) might be stuck
- Writes to $3F00+ might be misdirected

**Test**: Add logging to PPU::cpuWrite() for addresses $2006 and $2007.

### 4. Mapper Issues (MMC1)
Super Mario uses Mapper 1 (MMC1), while Donkey Kong uses Mapper 0 (NROM).

MMC1 features:
- Serial write interface (5 writes to configure)
- PRG/CHR bank switching
- Mirroring control

If the mapper isn't handling writes correctly, the game might not be running its initialization code at all.

**Test**: Log all writes to $8000-$FFFF and verify mapper state changes.

### 5. NMI Timing
The game might be waiting for VBlank NMI to trigger palette writes. If NMI timing is off:
- Game's VBlank handler might not run
- Palette writes might never occur

**Test**: Log NMI triggers and verify they occur at scanline 241, cycle 1.

### 6. Controller Polling Behavior
Even though the controller reads correctly, if the game's logic depends on:
- Button press transitions (edge detection)
- Specific timing of button holds
- Multiple button combinations

The game might not recognize the START button press.

**Test**: Try different button patterns (press+release, hold for specific frames, etc.)

## Recommended Next Steps

### Immediate Actions

1. **Add PPU Register Logging**
   ```php
   public function cpuWrite(int $address, int $data): void {
       if ($address === 0x2006 || $address === 0x2007) {
           error_log(sprintf("PPU Write: $%04X = $%02X", $address, $data));
       }
       // ...
   }
   ```

2. **Verify Palette RAM Writes**
   ```php
   private function ppuWrite(int $address, int $data): void {
       if ($address >= 0x3F00 && $address <= 0x3F1F) {
           error_log(sprintf("Palette write: $%04X = $%02X", $address, $data));
       }
       // ...
   }
   ```

3. **Test with Simpler MMC1 Games**
   - Try other Mapper 1 games (Metroid, Zelda, Mega Man 2)
   - See if they also show black screens
   - Isolates whether it's mapper or Super Mario specific

### Long-term Investigation

1. **CPU Trace Comparison**
   - Generate execution trace from PHP emulator
   - Generate execution trace from Go emulator
   - Compare first 10,000 instructions
   - Look for divergence point

2. **Reference Implementation Study**
   - Port Go PPU implementation patterns to PHP
   - Especially focus on PPUADDR/PPUDATA handling
   - Verify register state machine matches

3. **Test ROM Suite**
   - Run blargg's PPU tests
   - Run palette RAM access tests
   - Verify PPU register behavior

## Conclusion

**Current Status**: Donkey Kong works, Super Mario Bros doesn't.

**Root Cause**: Palette RAM never gets initialized in Super Mario.

**Most Likely Issue**: Either:
1. PPU register access (PPUADDR/PPUDATA) not working correctly for palette writes
2. Mapper 1 (MMC1) implementation preventing game code from running
3. CPU instruction bug preventing palette write code from executing

**Next Step**: Add logging to track $2006/$2007 writes and verify if game is even attempting to write to palette RAM.

## Files for Reference

- Analysis docs: `GO_EMULATOR_EXPLORATION_SUMMARY.md`, `go_nes_analysis.md`, `critical_implementation_patterns.md`
- Go emulator: `/home/andrew/Projects/nes-emulator`
- Test scripts: `tests/manual/diagnose_supermario_data.php`
- Controller tests: `tests/manual/test_controller_*.php`
