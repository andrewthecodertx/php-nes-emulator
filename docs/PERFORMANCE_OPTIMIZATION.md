# Performance Optimization Results

## Current Status

**Before optimization:**
- 2.6 FPS (23.1x slower than real-time)
- 385ms per frame (target: 16.7ms)
- 4.3% of real-time speed

**After caching optimization:**
- 2.8 FPS (21.6x slower than real-time)
- 361ms per frame
- 4.7% of real-time speed
- **~7-8% improvement**

## Optimizations Implemented

### 1. Cached Rendering Flags ✅

**Problem:** `$this->mask->renderBackground()` and `$this->mask->isRenderingEnabled()` were called tens of thousands of times per frame.

**Solution:** Added cached boolean flags that are updated when PPUMASK changes:
```php
private bool $cachedRenderingEnabled = false;
private bool $cachedRenderBackground = false;
```

**Impact:** 7-8% performance improvement

**Code changes:**
- Added cached flags in `src/PPU/PPU.php`
- Update cache when PPUMASK register is written
- Replaced all hot-path method calls with cached flags

## Bottleneck Analysis

### Current Time Distribution (per frame)
- **PPU clock: ~200ms** (55% of frame time)
- **CPU step: ~100ms** (28% of frame time)
- **Memory access: ~60ms** (17% of frame time)

### Why It's Still Slow

**PHP is interpreted:**
- Every method call has overhead
- Array access is slow (hash tables, not contiguous memory)
- No native code optimization
- Object property access slower than C struct fields

**The emulator is calling:**
- 89,342 PPU clock() calls per frame
- 29,780 CPU step() calls per frame
- ~100,000+ memory reads/writes per frame

**Each operation** that's microseconds in C becomes hundreds of nanoseconds in PHP.

## Remaining Optimization Opportunities

### High Impact (Difficult)

**1. Use PHP Extensions (C)**
- Write performance-critical parts in C
- Use PHP FFI to call native code
- Could achieve 10-20x speedup

**2. JIT Compilation**
- Tested, but minimal improvement (~0-5%)
- PHP JIT helps tight numeric loops, not object-oriented code

### Medium Impact (Moderate Effort)

**3. Use SplFixedArray**
- Replace PHP arrays with typed, contiguous arrays
- Expected: 10-15% improvement
- Requires moderate refactoring

**4. Inline Hot Methods**
- Inline small methods called millions of times
- Expected: 5-10% improvement per hot method
- Makes code less maintainable

**5. Batch Non-Rendering Cycles**
- Skip cycle-by-cycle emulation during VBlank
- Expected: 10-15% improvement
- May affect accuracy

### Low Impact (Easy)

**6. Reduce Object Creation**
- Reuse objects instead of creating new ones
- Expected: 2-5% improvement

**7. Profile-Guided Optimization**
- Use Xdebug or Blackfire to find more hot spots
- Expected: 5-10% cumulative improvement

## Realistic Performance Goals

### With All PHP Optimizations
- **Target: 10-15 FPS** (30-40% of real-time)
- **Requires:** SplFixedArray, inlining, batching, more caching
- **Effort:** High
- **Maintainability:** Lower

### With C Extension
- **Target: 30-60 FPS** (50-100% of real-time)
- **Requires:** Rewrite hot paths in C
- **Effort:** Very High
- **Maintainability:** Much lower

### Practical Recommendation

**For PHP-only:**
- Current 2.8 FPS is acceptable for **debugging and testing**
- Further optimizations have diminishing returns
- Focus on **accuracy and features** over speed

**For production:**
- Consider a C extension or different language for core emulation
- Keep PHP for tools, testing, and orchestration
- Or accept slower-than-real-time for educational purposes

## Benchmarking

To measure performance:
```bash
php tests/manual/benchmark.php
```

To get detailed profiling:
```bash
php tests/manual/profile_detailed.php
```

## Conclusion

The emulator is slow because PHP is inherently slow for this use case. The current optimizations have improved performance by ~8%, but fundamental architectural changes (C extension, different language) would be needed for real-time performance.

**However:** The emulator is **accurate** and **readable**, making it excellent for:
- Learning NES architecture
- Testing and debugging
- Educational purposes
- Prototyping

For these purposes, 2.8 FPS is sufficient.
