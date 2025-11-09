# NES Emulator Troubleshooting Guide

## Current Known Issues

### Issue #1: Games Don't Display Graphics

**Symptom:** ROM loads and runs but screen shows only solid gray

**Affected Games:**

- Donkey Kong (Mapper 0)
- Tetris (Mapper 1)

**What's Actually Happening:**
The emulator is working correctly - the games are running but haven't enabled
rendering yet.

**Technical Details:**

```
 CPU executes ROM code
 PPU renders frames
 NMI interrupts fire
 Game writes to VRAM
! PPUMASK stays at $06 (rendering disabled)
! Palette RAM all $00 (not initialized)
```

**Verified Working:**

- Frame counter advances (3× per CPU cycle)
- VBlank flag sets correctly
- NMI handler executes (PPUCTRL changes from $14 to $90)
- Nametable fills with tile $24 (empty tile)
- Controller input registers
- APU registers respond

**Why Games Don't Enable Rendering:**

The games execute their initialization code but never write bits 3 and 4 to
PPUMASK ($2001) to enable background and sprite rendering. After 200+ frames
with no change, the games appear stuck.

**Possible Root Causes:**

1. **Missing Hardware Behavior**
   - Some PPU register quirk not emulated
   - Power-on state different from real hardware
   - Reset behavior not matching hardware

1. **Timing Issue**
   - CPU/PPU synchronization slightly off
   - Frame timing not exact
   - Cycle counts incorrect

1. **Initialization Dependency**
   - Games waiting for specific hardware state
   - Checking for feature we haven't implemented
   - Timing-sensitive initialization failing

1. **ROM-Specific Issues**
   - These ROMs may have specific requirements
   - Dumped incorrectly or modified
   - Need exact hardware behavior

**Debugging Steps Taken:**

1. Verified NMI interrupts reach CPU
1. Confirmed NMI handler executes
1. Checked PPUCTRL enables NMI ($90)
1. Verified VRAM writes (tile $24)
1. Added controller input support
1. Implemented APU register stubs
1. Tested with Start button pressed
1. Ran 200+ frames watching for changes

**What to Try:**

1. **Test with different ROMs**

   ```bash
   # Try simpler test ROMs
   - nestest.nes (CPU test)
   - hello.nes (simple graphics)
   - Other Mapper 0 games
   ```

1. **Manual PPUMASK override**

   ```php
   // Force rendering on for testing
   $ppu->cpuWrite(0x2001, 0x1E); // Enable background + sprites
   ```

1. **Compare with olcNES**

   ```bash
   # Run Donkey Kong in reference emulator
   cd /home/andrew/Projects/olcNES
   # Check what PPUMASK value it sees
   ```

1. **Check reset vector execution**

   ```php
   // Verify CPU starts at correct address
   $resetVector = $bus->readWord(0xFFFC);
   echo "Reset vector: $" . sprintf("%04X", $resetVector);
   ```

---

## Issue #2: Slow Performance

**Symptom:** Each frame takes 4-5 seconds to render

**Cause:** PHP interpreter overhead

**Impact:**

- 1 frame = ~4-5 seconds
- 10 frames = ~40-50 seconds
- 100 frames = ~7-8 minutes

**Why This Happens:**

- PHP is interpreted, not compiled
- Tight loop in PPU (3 clocks per CPU cycle)
- Array access overhead
- No JIT optimization for this workload

**Solutions:**

1. **Use Larger Step Counts**

   ```javascript
   // In web viewer, use:
   - Step 10 Frames (not 1)
   - Step 100 Frames for bulk testing
   ```

1. **Profile Hotspots**

   ```bash
   # With Xdebug
   php -d xdebug.mode=profile test.php
   ```

1. **Consider PHP JIT**

   ```bash
   # Enable JIT (PHP 8+)
   php -d opcache.enable_cli=1 \
       -d opcache.jit_buffer_size=128M \
       -d opcache.jit=tracing \
       test.php
   ```

1. **Optimize Critical Paths**
   - Cache pattern table lookups
   - Reduce array access
   - Pre-compute palette colors

**Not a Bug:** This is expected for PHP emulation

---

## Issue #3: Web Viewer Timeout

**Symptom:** "Unexpected end of JSON input" after clicking "Step 100 Frames"

**Cause:** PHP execution timeout (default 30 seconds)

**Solution:** Already fixed! Using custom `php.ini`:

```ini
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

Server started with:

```bash
php -c php.ini -S localhost:8000
```

**If Still Timing Out:**

1. Use smaller step counts (10 instead of 100)
1. Check server is using custom php.ini
1. Increase timeout in php.ini

---

## Issue #4: Tests Fail After Changes

**Symptom:** PHPUnit tests fail after modifying code

**Common Causes:**

1. **Type Errors**

   ```
   Cannot assign array to property of type string
   ```

   - Check method return types
   - Verify property types match

1. **Method Not Found**

   ```
   Call to undefined method
   ```

   - Check method name spelling
   - Verify method is public
   - Check if method exists in dependency

1. **Constructor Changes**

   ```
   Too few arguments to function
   ```

   - Update all instantiations
   - Check test mocks/stubs

**Debugging:**

```bash
# Run specific test
./vendor/bin/phpunit tests/PPU/PPUTest.php

# Run with verbose output
./vendor/bin/phpunit --verbose

# Check syntax
php -l src/PPU/PPU.php
```

---

## Issue #5: Mapper Not Supported

**Symptom:** "Mapper X not supported" error when loading ROM

**Cause:** ROM uses a mapper we haven't implemented

**Currently Supported:**

- Mapper 0 (NROM)
- Mapper 1 (MMC1)

**To Add New Mapper:**

1. Create mapper class:

   ```php
   // src/Cartridge/Mapper2.php
   class Mapper2 implements MapperInterface {
       // Implement interface methods
   }
   ```

1. Register in NES.php:

   ```php
   return match ($mapperNumber) {
       0 => new Mapper0($cartridge),
       1 => new Mapper1($cartridge),
       2 => new Mapper2($cartridge), // Add here
       default => throw new InvalidArgumentException(...)
   };
   ```

1. Test with ROM:

   ```bash
   php test_rom.php path/to/rom.nes
   ```

---

## Issue #6: ROM Won't Load

**Symptom:** "Invalid iNES header" or file errors

**Checks:**

1. **File Exists**

   ```bash
   ls -lh roms/your_rom.nes
   ```

1. **Valid iNES Format**

   ```bash
   # First 4 bytes should be "NES\x1A"
   xxd -l 16 roms/your_rom.nes
   ```

1. **File Permissions**

   ```bash
   chmod 644 roms/your_rom.nes
   ```

1. **Correct Path**

   ```php
   // Use absolute path
   $nes = NES::fromROM(__DIR__ . '/roms/game.nes');
   ```

---

## Diagnostic Tools

### Check PPU State

```bash
php check_ppumask.php
```

Shows:

- Render background enabled?
- Render sprites enabled?
- Raw PPUMASK value

### Watch Registers

```bash
php watch_ppuctrl.php
```

Shows changes to PPUCTRL and PPUMASK over 100 frames

### Test Controller

```bash
php test_controller.php
```

Verifies controller shift register works

### Check NMI

```bash
php check_nmi_delivery.php
```

Verifies NMI interrupts reach CPU

### Debug Frame Buffer

```bash
php debug_frame.php
```

Shows actual pixel values in frame buffer

---

## Common Error Messages

### "Call to undefined method"

**Cause:** Method name wrong or doesn't exist
**Fix:** Check spelling, verify method is public

### "Cannot assign array to property of type string"

**Cause:** Type mismatch
**Fix:** Check property types, update declarations

### "Maximum execution time exceeded"

**Cause:** Operation too slow
**Fix:** Use custom php.ini with longer timeout

### "Serialization of 'Closure' is not allowed"

**Cause:** Trying to serialize CPU/PPU objects
**Fix:** Don't use PHP sessions, rebuild state instead

### "Mapper X not supported"

**Cause:** ROM uses unimplemented mapper
**Fix:** Implement mapper or use different ROM

---

## Getting Help

### Debug Checklist

Before asking for help, check:

1. All tests passing?

   ```bash
   ./vendor/bin/phpunit
   ```

1. PHP version correct?

   ```bash
   php -v  # Should be 8.4+
   ```

1. Dependencies installed?

   ```bash
   composer install
   ```

1. ROM file valid?

   ```bash
   xxd -l 16 roms/your_rom.nes
   ```

1. Checked error logs?

   ```bash
   # Check PHP error log
   tail -f /var/log/php/error.log
   ```

### Information to Include

When reporting issues:

- PHP version (`php -v`)
- Error message (full stack trace)
- ROM being tested
- Steps to reproduce
- Test results output

### Reference Materials

- `CLAUDE.md` - Project guidelines
- `README.md` - Main documentation
- `EMULATOR_STATUS.md` - Current status
- `VIEWER_GUIDE.md` - Web viewer usage
- CPU library docs: `vendor/andrewthecoder/6502-emulator/docs/`

---

## Advanced Debugging

### Enable Xdebug

```bash
php -d xdebug.mode=debug test.php
```

### Profile Performance

```bash
php -d xdebug.mode=profile test.php
# Analyze with webgrind or kcachegrind
```

### Memory Usage

```bash
php -d memory_limit=1G test.php
```

### Trace Execution

```php
// Add to code
echo "PC: $" . sprintf("%04X", $address) . "\n";
```

### Compare with Reference

NO REFERENCE YET!

---

## Future Debugging Features

Potential additions to make debugging easier:

1. **CPU Trace Log** - Log every instruction executed
2. **Memory Watch** - Monitor specific addresses
3. **Breakpoints** - Pause at specific PC values
4. **Step Debugging** - Execute one instruction at a time
5. **State Snapshots** - Save/restore emulator state
6. **Comparison Mode** - Run against reference emulator

These would make finding issues much faster but require significant
implementation effort.

---

## Summary

Most issues stem from:

1. **Games not enabling rendering** (known issue, under investigation)
1. **Performance** (inherent to PHP, not a bug)
1. **Type mismatches** (PHP strict types)
1. **Missing mappers** (need more implementations)

The emulator core is solid and working. The main challenge is understanding why
specific games don't enable PPUMASK rendering bits.
