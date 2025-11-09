# NES Emulator Performance Optimization Plan

## Current Performance
- **2.6 FPS** (target: 60 FPS)
- **23x slower than real-time**
- **385ms per frame** (target: 16.7ms)

## Profiling Results

### Time Distribution
- **PPU clock: 204ms** (63% of frame time)
- **CPU step: 98ms** (30% of frame time)
- **Memory access: 564ns each** (called ~100,000 times)

### Hot Paths
1. `PPU::clock()` - Called 89,342 times per frame
2. `CPU::step()` - Called 29,780 times per frame
3. `Bus::read()` / `Bus::write()` - Called ~100,000+ times per frame

## Optimization Strategies

### 1. **Reduce Function Call Overhead** (HIGH IMPACT)

**Problem:** PHP function calls are expensive, and we're making millions per frame.

**Solutions:**
- Inline simple calculations directly in hot loops
- Combine multiple operations into single methods
- Cache method results where possible

**Expected gain:** 30-40% improvement

### 2. **Optimize Memory Access** (HIGH IMPACT)

**Problem:** PHP arrays are hash tables, not contiguous memory.

**Current:** Standard PHP arrays
```php
private array $ram; // Hash table lookup
```

**Better:** SplFixedArray (contiguous, typed)
```php
private SplFixedArray $ram; // Direct memory access
```

**Expected gain:** 20-30% improvement for memory-heavy operations

### 3. **Reduce Branch Predictions** (MEDIUM IMPACT)

**Problem:** Excessive if/else checks in hot loops.

**Solution:**
- Flatten nested conditions
- Use early returns
- Precompute boolean flags

**Expected gain:** 10-15% improvement

### 4. **Batch Non-Rendering Cycles** (MEDIUM IMPACT)

**Problem:** VBlank and post-render scanlines don't need cycle-by-cycle emulation.

**Solution:**
- Skip detailed emulation during VBlank (scanlines 241-260)
- Skip detailed emulation during post-render (scanline 240)
- Only run cycle-accurate for visible scanlines (0-239) and pre-render (-1)

**Expected gain:** 15-20% improvement

### 5. **Optimize PPU Rendering** (HIGH IMPACT)

**Current issues:**
- Accessing PPU registers through objects (`$this->mask->renderBackground()`)
- Multiple method calls per pixel
- Recalculating bit masks every cycle

**Solutions:**
- Cache rendering flags at start of scanline
- Inline shift operations
- Pre-calculate frequently used values

**Expected gain:** 25-35% improvement

### 6. **JIT Compilation** (REQUIRES PHP 8+)

**Check PHP version and enable JIT:**
```bash
php -v  # Check if PHP 8.0+
php -d opcache.enable_cli=1 -d opcache.jit_buffer_size=100M -d opcache.jit=tracing script.php
```

**Expected gain:** 50-100% improvement (if available)

## Quick Wins (Easy to Implement)

### A. Enable OPcache
```ini
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=100M
```

### B. Use SplFixedArray for RAM/ROM
Replace arrays with SplFixedArray for:
- PPU nametable (2KB)
- PPU palette RAM (32 bytes)
- CPU RAM (2KB)
- Cartridge ROM data

### C. Cache Rendering Flags
Instead of calling `$this->mask->renderBackground()` every cycle:
```php
// At start of scanline:
$renderingEnabled = $this->mask->isRenderingEnabled();
$renderBg = $this->mask->renderBackground();

// In hot loop:
if ($renderBg) {
    // Use cached value
}
```

### D. Inline getColorFromPalette()
This is called for every visible pixel (61,440 times per frame).

## Implementation Priority

1. **Enable PHP JIT** (0 code changes, huge gains)
2. **Cache rendering flags** (minimal code changes)
3. **Optimize memory access** (moderate effort, big gains)
4. **Inline hot path calculations** (high effort, significant gains)
5. **Batch non-rendering cycles** (medium effort, good gains)

## Expected Results

With all optimizations:
- **Target: 10-20 FPS** (6-8x faster)
- **Stretch goal: 30+ FPS** (with JIT)
- **Dream: 60 FPS** (would require major architectural changes or C extension)

## Measurement

After each optimization, run:
```bash
php tests/manual/benchmark.php
```

Compare FPS before/after to validate improvements.
