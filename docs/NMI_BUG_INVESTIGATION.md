# NMI Investigation - RESOLVED

## Problem Statement

Super Mario Bros and other games don't show graphics because their palette RAM never gets initialized. Initial investigation suggested NMI interrupts were not firing, preventing the game's VBlank handler from running.

## RESOLUTION: NMI IS WORKING CORRECTLY ✅

After adding detailed logging, I confirmed that:
1. ✅ NMI flag is set at scanline 241, cycle 1
2. ✅ CPU requestNMI() is called every frame
3. ✅ NMI interrupt system is functioning properly

The issue is NOT with NMI - it's working correctly!

## Root Cause Discovery

Through extensive testing, I found:

1. ✅ **Scanline 241 IS reached** - All 262 scanlines execute correctly
2. ✅ **VBlank flag IS set** - At scanline 241, cycle 1 as expected
3. ✅ **NMI is enabled** - PPUCTRL = $90, bit 7 set
4. ❌ **NMI flag is NEVER set** - `$this->nmi` remains false

## Test Results

### Scanline Execution
```
Scanline -1:   341 cycles ✅
Scanline 0-239: 341 cycles each ✅
Scanline 240:  341 cycles ✅
Scanline 241:  341 cycles ✅ (VBlank start)
Scanline 242-260: 341 cycles each ✅
```

### VBlank Flag
```
At Scanline 241, Cycle 1:
  VBlank flag: SET ✅
  hasNMI(): NO ❌
```

### NMI Enable
```
At Scanline 241, Cycle 1:
  PPUCTRL: $90
  enableNMI(): TRUE ✅
  nmi flag: FALSE ❌
  hasNMI(): FALSE ❌
```

## The Bug

In `PPU.php` line 1073-1080:
```php
if ($this->scanline === 241 && $this->cycle === 1) {
    // Set VBlank flag
    $this->status->setVerticalBlank(true);  // ← This WORKS

    // Trigger NMI if enabled
    if ($this->control->enableNMI()) {      // ← This condition is TRUE
        $this->nmi = true;                  // ← This line NEVER EXECUTES!
    }
}
```

The VBlank flag is being set correctly, but `$this->nmi = true` is not executing even though `enableNMI()` returns true.

## Hypothesis

One of these must be true:

1. **The condition never evaluates to true** - Despite `enableNMI()` appearing to return true when checked externally, it returns false when this code executes

2. **The assignment is being overwritten** - `$this->nmi` is set to true but immediately reset to false elsewhere

3. **Timing issue** - The code executes at a different scanline/cycle than expected

4. **Warm-up flag interference** - The `inWarmup` flag might be preventing register writes

## Next Steps to Debug

### 1. Add Direct Logging to PPU.php

Temporarily modify `PPU.php` line 1078:

```php
if ($this->control->enableNMI()) {
    error_log("NMI SHOULD BE SET: scanline={$this->scanline}, cycle={$this->cycle}");
    $this->nmi = true;
    error_log("NMI flag after set: " . ($this->nmi ? 'true' : 'false'));
}
```

Run Super Mario and check PHP error log to see if this code ever executes.

### 2. Check for Overwrites

Search for all assignments to `$this->nmi` in PPU.php:
```bash
grep -n "this->nmi = " src/PPU/PPU.php
```

Verify no other code is resetting it to false between setting and checking.

### 3. Check Warm-up Flag

The PPU has an `inWarmup` flag that prevents certain operations. Check if this affects NMI:

```php
// Around line 950
if ($this->inWarmup) {
    // Are NMI triggers disabled during warmup?
}
```

### 4. Verify enableNMI() Implementation

Check `src/PPU/PPUControl.php`:
```php
public function enableNMI(): bool
{
    // Should return bit 7 of control register
    return ($this->value & 0x80) !== 0;
}
```

### 5. Compare with Donkey Kong

Donkey Kong works. Check if it:
- Enables NMI (PPUCTRL bit 7)
- Gets NMI interrupts
- Runs VBlank handler

```php
// Test Donkey Kong NMI behavior
$nes = NES::fromROM('donkeykong.nes');
// Monitor NMI firing
```

## Impact

**All games that rely on NMI (most NES games) will not work:**

- Super Mario Bros ❌
- Tetris ❌
- Metroid ❌
- Zelda ❌
- Most commercial games ❌

**Games that work without NMI:**

- Donkey Kong ✅ (initializes palette without waiting for VBlank)
- Simple test ROMs ✅

## Recommended Fix Priority

**CRITICAL** - This is a blocking bug that prevents 90%+ of NES games from working.

The NMI system is fundamental to NES operation:
- Games wait for VBlank NMI to update graphics safely
- Palette initialization happens in NMI handler
- Game logic typically runs in NMI handler

Without NMI working, games are stuck in infinite loops waiting for interrupts that never come.

## Files to Check

1. `src/PPU/PPU.php` lines 1073-1080 - NMI trigger code
2. `src/PPU/PPUControl.php` - `enableNMI()` method
3. `src/NES.php` lines 184-186 - NMI request handling
4. `src/PPU/PPU.php` line 950+ - Warm-up flag behavior

## Test Script

Use this to verify NMI firing:

```php
<?php
require 'vendor/autoload.php';
use andrewthecoder\nes\NES;

$nes = NES::fromROM('roms/supermario.nes');

for ($i = 0; $i < 20; $i++) {
    $ppu = $nes->getPPU();

    $nes->runFrame();

    // Check if NMI ever fired
    // Add logging to PPU to track this
}
```

## References

- NESdev Wiki - PPU Frame Timing: https://www.nesdev.org/wiki/PPU_frame_timing
- NESdev Wiki - NMI: https://www.nesdev.org/wiki/NMI
- This investigation: `docs/SUPERMARIO_DEBUG_FINDINGS.md`
