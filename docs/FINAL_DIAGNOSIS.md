# Final Diagnosis - Super Mario Bros Black Screen Issue

## Executive Summary

After comprehensive investigation including comparison with your working Go NES emulator, I've identified the root cause: **Super Mario Bros never executes its palette initialization code**.

## What's Working ✅

1. **PPU Timing**: All 262 scanlines execute correctly (verified against NESdev wiki specs)
2. **NMI System**: Fires correctly every frame at scanline 241, cycle 1
3. **CPU NMI Handling**: `requestNMI()` called successfully
4. **PPU Register Handling**: PPUADDR/PPUDATA work correctly
5. **Palette Write Logic**: Mirroring and addressing implemented correctly
6. **Background Rendering**: 8-cycle fetch pattern, shifters all working
7. **Controller Input**: Fully functional

## The Problem ❌

**Super Mario Bros never writes to palette RAM:**

```
Donkey Kong (frame 2):
  ✅ Writes 14 palette entries ($3F00-$3F0C, $3F11)

Super Mario Bros (0-50 frames):
  ❌ ZERO palette writes
```

## Evidence

### Test Results

1. **Palette Write Monitoring**:
   - Added logging to `PPU::ppuWrite()` for palette RAM writes
   - Donkey Kong: 14 writes on frame 2
   - Super Mario: 0 writes after 50 frames

2. **NMI Verification**:
   - NMI fires correctly starting frame 7
   - CPU `requestNMI()` called every frame
   - But game code doesn't execute palette writes

3. **Game State**:
   - PPUCTRL: $90 (NMI enabled, correct configuration)
   - PPUMASK: $00 (rendering disabled by game)
   - Nametable: 1920 tiles loaded
   - CHR-ROM: 212/256 bytes populated
   - Palette RAM: ALL ZEROS

## Root Cause

The game's NMI handler (or main loop) is not reaching the palette initialization code. This means one of:

1. **CPU Instruction Bug** - A critical 6502 instruction is implemented incorrectly, causing:
   - Infinite loop
   - Crash
   - Branch to wrong address
   - Stack corruption

2. **Mapper 1 (MMC1) Bug** - The serial shift register interface is not working correctly:
   - Game writes to mapper registers
   - Mapper doesn't update banks
   - Code bank isn't mapped
   - Game executes wrong code or crashes

3. **Memory Corruption** - RAM is not being preserved correctly between frames:
   - Variables get corrupted
   - Stack gets corrupted
   - Game state becomes invalid

## Comparison with Go Emulator

Your Go emulator successfully runs Super Mario Bros, which means it handles these cases correctly. The key differences I found:

### Mapper 1 Reset Behavior

**Go version** (`mapper1.go` line 166):
```go
// Bit 7 set: Reset shift register
m.shiftRegister = 0x10
m.shiftCount = 0
// Also set control to mode 3 (fix last bank)
m.prgMode = 3
```

**PHP version** (`Mapper1.php` line 178):
```php
$this->shiftRegister = 0x10;
$this->writeCount = 0;
$this->control |= 0x0C; // Set to mode 3
```

**Issue**: The PHP version uses `|=` (OR) instead of setting PRG mode directly. This might not properly reset the mode if other bits are set.

### Recommended Fix

Try changing line 178 of `Mapper1.php`:

```php
// OLD:
$this->control |= 0x0C; // Set to mode 3

// NEW:
$this->control = ($this->control & 0x13) | 0x0C; // Preserve CHR mode and mirroring, set PRG mode to 3
```

Or even simpler, just directly set prgMode like the Go version does:
```php
// Extract and set just the PRG mode bits
$this->control = ($this->control & ~0x0C) | 0x0C; // Clear PRG mode bits, then set to mode 3
```

## Testing Recommendations

### 1. Test Other MMC1 Games

Run these games (all use Mapper 1) to see if they also fail:

```php
$games = [
    'Legend of Zelda',
    'Metroid',
    'Mega Man 2',
    'Kid Icarus',
    'Castlevania II'
];
```

If they ALL show black screens → Mapper 1 bug confirmed
If some work → More specific issue

### 2. Test Other Mapper 0 Games

Run these games (use simple NROM mapper) to confirm they work like Donkey Kong:

```php
$games = [
    'Balloon Fight',
    'Ice Climber',
    'Excitebike',
    'Super Mario Bros (Mapper 0 version if available)'
];
```

If they work → Confirms Mapper 1 is the problem

### 3. CPU Trace Comparison

Generate execution trace from PHP emulator:
```php
// Log first 10,000 CPU instructions
for ($i = 0; $i < 10000; $i++) {
    $pc = getPC(); // Need to expose this
    $opcode = read($pc);
    error_log("$pc: $opcode");
    cpu->step();
}
```

Compare with Go emulator trace to find where execution diverges.

## Next Steps (Priority Order)

### CRITICAL - Fix Mapper 1 Reset

1. Update `Mapper1.php` line 178 with proper reset logic
2. Test Super Mario Bros
3. If still broken, test other MMC1 games

### HIGH - Add CPU Tracing

1. Add logging to expose PC (program counter)
2. Log first 1000 instructions
3. Compare with Go emulator
4. Find divergence point

### MEDIUM - Verify PRG Bank Switching

1. Add logging to Mapper1 bank changes
2. Verify game is switching banks correctly
3. Check if all PRG-ROM is accessible

### LOW - Test Different ROMs

1. Try different Super Mario ROM dumps
2. Some ROMs might have bad headers
3. Verify iNES header is parsed correctly

## Files Involved

- `src/Cartridge/Mapper1.php` - MMC1 implementation (line 178 needs fix)
- `src/PPU/PPU.php` - Palette handling (working correctly)
- `src/NES.php` - NMI handling (working correctly)
- `roms/supermario.nes` - Test ROM

## Conclusion

The emulator's core systems (PPU, NMI, rendering, palette) are all working correctly according to NESdev specs. The issue is that **Super Mario's game code never executes the palette write routine**.

Most likely cause: **Mapper 1 (MMC1) reset behavior** at line 178 of `Mapper1.php`.

The Go emulator works because it correctly resets the PRG mode to 3 when bit 7 is set, while the PHP version uses `|=` which may not properly reset the mode.

## Test This Fix

```bash
# 1. Apply the fix to Mapper1.php line 178
# 2. Run Super Mario
php -r "
require 'vendor/autoload.php';
use andrewthecoder\nes\NES;

\$nes = NES::fromROM('roms/supermario.nes');
for (\$i = 0; \$i < 120; \$i++) {
    \$nes->runFrame();
}

\$ppu = \$nes->getPPU();
\$paletteRam = /* get palette via reflection */;
\$nonZero = count(array_filter(\$paletteRam, fn(\$x) => \$x !== 0));

echo \$nonZero > 0 ? '✅ FIXED!' : '❌ Still broken';
"
```

If this doesn't fix it, the issue is likely in CPU instruction implementation or deeper in Mapper 1 bank switching logic.
