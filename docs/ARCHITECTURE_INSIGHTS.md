# NES Emulator Architecture Insights

Based on analysis of popular NES emulators (olcNES, LaiNES, JSNES, SimpleNES), here are key patterns and insights that can help improve our PHP implementation.

## 1. CPU-PPU Timing Synchronization (CRITICAL)

### How Other Emulators Do It

**olcNES Pattern** (`Bus.cpp:177-246`):
```cpp
bool Bus::clock()
{
    // PPU runs EVERY clock cycle
    ppu.clock();

    // CPU runs every 3rd clock cycle (PPU is 3x faster)
    if (nSystemClockCounter % 3 == 0)
    {
        if (dma_transfer) {
            // Handle DMA...
        } else {
            cpu.clock();
        }
    }

    // Check for NMI from PPU
    if (ppu.nmi) {
        ppu.nmi = false;
        cpu.nmi();
    }

    nSystemClockCounter++;
}
```

**LaiNES Pattern**:
```cpp
// For each CPU cycle, run PPU 3 times
cpu.step();
ppu.step();
ppu.step();
ppu.step();
```

### What We're Doing Wrong

Our current implementation in `src/NES.php`:
```php
public function runFrame(): void
{
    do {
        $this->cpu->tick();  // Run one CPU instruction
        // PPU doesn't tick during CPU execution!

        $cyclesThisInstruction = $this->cpu->getCycles();
        $ppuCycles = $cyclesThisInstruction * 3;

        for ($i = 0; $i < $ppuCycles; $i++) {
            $this->ppu->tick();  // PPU ticks AFTER CPU instruction completes
        }
    } while (!$this->ppu->isFrameComplete());
}
```

**Problem**: We run entire CPU instructions, then run PPU cycles. This breaks mid-instruction timing! The PPU should tick continuously, not in batches.

### Recommended Fix

**Option A: Match olcNES Pattern**
```php
// Run at PPU clock rate, divide for CPU
private int $clockCounter = 0;

public function clock(): bool
{
    // PPU runs every clock
    $this->ppu->tick();

    // CPU runs every 3rd clock
    if ($this->clockCounter % 3 === 0) {
        $this->cpu->tick();  // Run ONE CPU cycle (not instruction!)
    }

    // Check for NMI
    if ($this->ppu->hasNMI()) {
        $this->cpu->triggerNMI();
        $this->ppu->clearNMI();
    }

    $this->clockCounter++;
    return $this->ppu->isFrameComplete();
}

public function runFrame(): void
{
    $this->ppu->resetFrameComplete();
    while (!$this->clock()) {
        // Keep clocking until frame completes
    }
}
```

**Option B: Keep Current Pattern, Fix CPU Core**
- Modify the 6502 CPU core to support **cycle-level ticking** instead of instruction-level
- This is more invasive but maintains our current architecture

## 2. PPU Rendering Conditions

### When PPU Actually Renders

From `olc2C02.h:112-127`:
```cpp
union PPUMASK
{
    struct
    {
        uint8_t grayscale : 1;
        uint8_t render_background_left : 1;   // Bit 1
        uint8_t render_sprites_left : 1;      // Bit 2
        uint8_t render_background : 1;        // Bit 3 - CRITICAL!
        uint8_t render_sprites : 1;           // Bit 4 - CRITICAL!
        uint8_t enhance_red : 1;
        uint8_t enhance_green : 1;
        uint8_t enhance_blue : 1;
    };
    uint8_t reg;
} mask;
```

**Key Discovery**: Throughout olcNES PPU code, rendering only happens when:
```cpp
if (mask.render_background || mask.render_sprites)
{
    // Do rendering work
}
```

This explains why our games don't render - they're waiting for proper timing before setting these bits!

### Our Implementation Check

In `src/PPU/PPUMask.php`, we should verify:
1. Do we have `isBackgroundEnabled()` checking bit 3?
2. Do we have `isSpritesEnabled()` checking bit 4?
3. Do we check BOTH in our rendering code?

## 3. PPU Scanline and Cycle State Machine

### olcNES Pattern

The PPU operates as a state machine based on `(scanline, cycle)` coordinates:

```cpp
// PPU state
int16_t scanline = 0;    // -1 to 260
int16_t cycle = 0;       // 0 to 340
```

**Key Scanline Ranges**:
- `-1`: Pre-render scanline (prepare for frame)
- `0-239`: Visible scanlines (render pixels)
- `240`: Post-render (idle)
- `241-260`: VBlank (NMI triggered at start of 241)

**Key Cycle Ranges** (per scanline):
- `0`: Idle cycle
- `1-256`: Visible cycles (output pixels)
- `257-320`: Sprite evaluation for next scanline
- `321-336`: Fetch first two tiles for next scanline
- `337-340`: Dummy nametable reads

### Our Implementation

Check `src/PPU/PPU2C02.php` - do we have proper scanline/cycle tracking?

## 4. Frame Completion Signal

### olcNES Pattern

```cpp
// In PPU header
bool frame_complete = false;

// In PPU clock function (around scanline 241, cycle 1)
if (scanline == 241 && cycle == 1) {
    status.vertical_blank = 1;
    if (control.enable_nmi) {
        nmi = true;  // Signal NMI to CPU
    }
    frame_complete = true;  // Signal frame done for emulator
}
```

Frame completion is tied to **entering VBlank** (scanline 241, cycle 1).

### Our Check

Does our `PPU2C02::isFrameComplete()` trigger at the right moment?

## 5. Sprite (OAM) Memory and DMA

### olcNES DMA Pattern

**Key Insight**: DMA (Direct Memory Access) is how games quickly copy sprite data to OAM.

```cpp
// Writing to $4014 triggers DMA
if (addr == 0x4014)
{
    dma_page = data;      // High byte of source address
    dma_addr = 0x00;      // Start at low byte 0
    dma_transfer = true;  // Flag DMA active
}

// During DMA in Bus::clock()
if (dma_transfer)
{
    // Wait for even cycle
    if (dma_dummy && nSystemClockCounter % 2 == 1) {
        dma_dummy = false;
    }

    // Read on even cycles, write on odd cycles
    if (nSystemClockCounter % 2 == 0) {
        dma_data = cpuRead(dma_page << 8 | dma_addr);
    } else {
        ppu.pOAM[dma_addr] = dma_data;
        dma_addr++;
        if (dma_addr == 0x00) {  // Wrapped around after 256 bytes
            dma_transfer = false;
            dma_dummy = true;
        }
    }
}
```

**Important**: DMA suspends CPU for 513-514 cycles! This is critical for timing accuracy.

### Our Check

Do we implement $4014 DMA register? This is essential for sprite rendering in real games.

## 6. Background Rendering with Shifters

### olcNES Pattern

The PPU uses **16-bit shift registers** for smooth scrolling:

```cpp
uint16_t bg_shifter_pattern_lo = 0x0000;
uint16_t bg_shifter_pattern_hi = 0x0000;
uint16_t bg_shifter_attrib_lo  = 0x0000;
uint16_t bg_shifter_attrib_hi  = 0x0000;

// Load next 8 pixels into bottom byte
auto LoadBackgroundShifters = [&]()
{
    bg_shifter_pattern_lo = (bg_shifter_pattern_lo & 0xFF00) | bg_next_tile_lsb;
    bg_shifter_pattern_hi = (bg_shifter_pattern_hi & 0xFF00) | bg_next_tile_msb;
    // ... attribute shifters too
};

// Each cycle, shift left by 1 bit
auto UpdateShifters = [&]()
{
    if (mask.render_background) {
        bg_shifter_pattern_lo <<= 1;
        bg_shifter_pattern_hi <<= 1;
        bg_shifter_attrib_lo <<= 1;
        bg_shifter_attrib_hi <<= 1;
    }
};
```

**Why**: Top 8 bits = current tile being rendered, bottom 8 bits = next tile. Allows seamless scrolling at pixel precision.

### Our Check

Does `src/PPU/PPU2C02.php` use shifters, or does it render tile-by-tile?

## 7. Palette Color Lookup

### olcNES Exact Palette

```cpp
// From olc2C02.cpp:61-80
palScreen[0x00] = olc::Pixel(84, 84, 84);
palScreen[0x01] = olc::Pixel(0, 30, 116);
// ... etc
palScreen[0x12] = olc::Pixel(48, 50, 236);  // This is the BLUE we saw!
```

**This matches our rendered color!** RGB(48, 50, 236) is NES palette color $12.

Our palette in `src/PPU/Palette.php` appears correct.

## 8. Performance Considerations

### Why Is Our Emulator Slow?

**Observation**: ~1 second per frame = 67× slower than real hardware.

**Likely causes**:
1. **PHP interpreter overhead** - Compiled C++ is much faster
2. **Array access costs** - PHP arrays are slower than C arrays
3. **Function call overhead** - We may be making too many small function calls
4. **Memory allocations** - Creating new objects/arrays each frame

### Optimization Strategies

**From JSNES** (JavaScript emulator):
- Delegate timing to host application
- Use typed arrays where possible
- Minimize object creation in hot paths

**From LaiNES** (C++ compact emulator):
- Use templates for instruction dispatch (compile-time optimization)
- Inline critical functions
- Keep state in primitive types, not objects

**For PHP**:
1. **Use arrays over objects** for performance-critical state (scanline counter, cycle counter)
2. **Cache method results** where possible
3. **Reduce function calls** in the hot path (PPU tick, CPU tick)
4. **Profile with Xdebug** to find bottlenecks
5. **Consider opcache optimization**
6. **JIT compilation** (PHP 8.0+) might help

## 9. Key Architectural Decisions

### What All Emulators Do

✅ **Separate CPU and PPU clock rates** (3:1 ratio)
✅ **Bus-based memory architecture** (all reads/writes go through bus)
✅ **Mapper abstraction** (cartridge hardware varies)
✅ **NMI on VBlank** (PPU signals CPU)
✅ **Frame completion flag** (when to stop emulating)
✅ **Scanline/cycle state machine** (PPU rendering)

### Where Implementations Differ

**Timing granularity**:
- olcNES: Clock at PPU rate, divide for CPU
- LaiNES: Clock at CPU rate, multiply for PPU
- JSNES: Let host manage timing

**Rendering approach**:
- olcNES: Pixel-by-pixel with shifters (most accurate)
- Some emulators: Tile-by-tile (faster but less accurate)

**Memory representation**:
- C++: Raw arrays (`uint8_t cpuRam[2048]`)
- PHP: Array or `SplFixedArray`

## 10. Recommended Action Items

### High Priority (Fixes Rendering Issues)

1. **Fix CPU-PPU timing synchronization**
   - Implement cycle-level ticking instead of instruction-level
   - See "Option A" pattern above

2. **Verify PPUMASK rendering checks**
   - Ensure we check bits 3 & 4 before rendering
   - Add internal getter methods

3. **Implement $4014 DMA register**
   - Required for sprite rendering in real games
   - Must suspend CPU during transfer

4. **Add scanline/cycle state machine**
   - Track exact position in frame
   - Trigger NMI at scanline 241, cycle 1

### Medium Priority (Improves Accuracy)

5. **Implement background shifters**
   - 16-bit shift registers for smooth scrolling
   - Essential for pixel-perfect rendering

6. **Add sprite evaluation logic**
   - Happens during cycles 257-320
   - Determines which 8 sprites visible on next scanline

7. **Implement sprite-0 hit detection**
   - Games use this for timing/synchronization
   - Check collision between sprite 0 and background

### Low Priority (Optimization)

8. **Profile and optimize hot paths**
   - Use Xdebug profiler
   - Focus on PPU::tick() and CPU::tick()

9. **Consider architecture refactor**
   - Use arrays instead of objects where performance-critical
   - Reduce function call depth

10. **Add frame skip option**
    - For faster testing (render every Nth frame)
    - Trade accuracy for speed during development

## 11. Testing Strategy from olcNES

### What olcNES Tests

The repository has example ROMs for testing:
- **Part #2**: CPU instruction tests
- **Part #3**: Cartridge loading, mapper 0
- **Part #4**: Background rendering
- **Part #5**: Sprite rendering
- **Part #7**: Multiple mappers, audio

### Incremental Testing Approach

1. **CPU**: Run nestest.nes, verify instruction accuracy
2. **PPU Registers**: Test read/write behavior
3. **Background**: Load simple ROM with BG only
4. **Sprites**: Load ROM with sprites
5. **Scrolling**: Test games that scroll
6. **Mappers**: Test different mapper chips

This matches our current approach!

## 12. Why Games Don't Enable Rendering

### Root Cause Analysis

Games don't enable PPUMASK rendering bits because:

1. **Waiting for VBlank** - They expect predictable NMI timing
2. **Waiting for proper bootup** - Some games wait ~2 frames for PPU warmup
3. **Waiting for controller input** - Some games wait for START
4. **Checking PPUSTATUS** - Games may be polling bit 7 (VBlank) before enabling rendering

### Our Timing is Wrong

If our NMI timing is off, games will hang waiting for VBlank! This could explain:
- Donkey Kong showing PPUMASK = $06 (rendering disabled)
- Tetris showing PPUMASK = $00 (rendering disabled)
- nestest.nes showing PPUMASK = $00 (rendering disabled)

**Fix**: Implement proper scanline/cycle timing and NMI triggering.

## References

- **olcNES**: `/home/andrew/Projects/olcNES/` (local)
- **NESDev Wiki**: https://www.nesdev.org/wiki/
- **LaiNES**: https://github.com/AndreaOrru/LaiNES
- **JSNES**: https://github.com/bfirsh/jsnes

## Conclusion

The most critical issue is **CPU-PPU timing synchronization**. Our current instruction-level batching breaks the tight timing requirements that games expect. Once we fix this, NMI timing should work correctly, and games should enable rendering naturally.

The second issue is ensuring we properly implement the **scanline/cycle state machine** in the PPU, triggering NMI exactly at scanline 241, cycle 1.

These two fixes should get games rendering without forcing PPUMASK manually!
