# Web Viewer Status

## Current State

The web viewer at `viewer/index.html` **works** but is **very slow** (~0.4 seconds per frame, or 2.5 FPS).

### What Changed with Timing Fix

**Before timing fix**:
- ~1.0 second per frame
- Rendering never enabled naturally

**After timing fix**:
- ~0.4 seconds per frame (**2.5× faster!**)
- Rendering enabled naturally at frame 4
- Viewer starts at frame 4 automatically

## Why It's Still Slow

### The Fundamental Problem

PHP cannot serialize the NES emulator object because it contains **Closures** (anonymous functions) in the CPU core. This means:

1. ❌ Cannot store NES state in session
2. ❌ Must recreate entire NES instance on each request
3. ❌ Must re-run ALL frames from beginning every time

### Performance Impact

```
Request for frame 10:
  - Load ROM: ~0.05s
  - Run frames 1-10: 10 × 0.4s = ~4.0s
  - Total: ~4.05s per request

Request for frame 20:
  - Load ROM: ~0.05s
  - Run frames 1-20: 20 × 0.4s = ~8.0s
  - Total: ~8.05s per request
```

The viewer gets **progressively slower** as you step forward because it re-runs more frames each time.

### What We Tried

1. **Serialize/unserialize NES object** ❌
   - Failed: "Serialization of 'Closure' is not allowed"
   - The CPU core uses anonymous functions for instruction handlers

2. **Store state in session** ❌
   - Same issue - can't serialize

3. **Accept the limitation** ✓
   - Viewer works but is slow
   - At least 2.5× faster than before timing fix

## How to Use the Viewer

### Starting the Viewer

```bash
# Terminal 1: Start PHP server
cd /mnt/internalssd/Projects/NES/viewer
php -S localhost:8080

# Terminal 2: Open in browser
firefox http://localhost:8080
# or
google-chrome http://localhost:8080
```

### What to Expect

1. **Reset**: Takes ~1.6 seconds (loads ROM + runs 4 frames to enable rendering)
2. **Step 1 frame**: Adds ~0.4 seconds per frame
3. **Step 10 frames**: Each step request gets slower as frame count increases
4. **Frame 4+**: Graphics should be visible (nestest.nes renders gray text)

### Tips for Testing

- **Be patient** - Each action takes several seconds
- **Don't click multiple times** - Wait for response before next click
- **Reset periodically** - Keeps frame count low
- **Use single-frame steps** - Faster than multi-frame steps
- **Check browser console** - Shows frame buffer data

## Solutions (Future Work)

### Option 1: Remove Closures from CPU Core

**Difficulty**: Hard
**Impact**: Would allow serialization

Refactor the CPU core to not use Closures:
```php
// BEFORE (not serializable)
$this->instructionHandlers['LDA'] = function($opcode) { ... };

// AFTER (serializable)
class InstructionHandlers {
    public function LDA($opcode) { ... }
}
```

This would require changes to the `andrewthecoder/6502-emulator` dependency.

### Option 2: Daemon Process with WebSockets

**Difficulty**: Medium
**Impact**: Real-time performance

Create a long-running PHP daemon that:
- Maintains NES state in memory
- Communicates via WebSockets
- No serialization needed

```
Browser <-- WebSocket --> PHP Daemon (keeps NES in RAM)
```

### Option 3: Client-Side JavaScript Emulator

**Difficulty**: High
**Impact**: Best performance

Port the emulator to JavaScript/WebAssembly:
- Runs directly in browser
- No server round-trips
- Could achieve 60 FPS

### Option 4: Accept Limitation

**Difficulty**: None
**Impact**: Viewer remains slow

The viewer works for **demonstration and debugging** purposes:
- Shows rendering is working
- Allows visual inspection of frame buffer
- Useful for development, not end users

## Current Viewer Improvements

### What Was Fixed

1. **Starts at frame 4** - Where rendering is enabled
2. **No forced rendering** - Uses natural game behavior
3. **Faster per-frame** - 2.5× faster than before
4. **Actually shows graphics** - nestest.nes renders gray pixels

### What It Shows

**Frame 4** (initial display):
```
Colors:
  RGB(  0,  0,  0):  55,760 pixels (90.76%) ← Black background
  RGB( 84, 84, 84):   5,680 pixels (9.24%)  ← Gray text
```

Proves the rendering pipeline works correctly!

## Comparison: Old vs New Timing

### Old Timing (Instruction-Level)

```
Frame 1:  ~1.0s  | PPUMASK=$00 BG=OFF SPR=OFF | Manual forcing needed
Frame 2:  ~1.0s  | PPUMASK=$00 BG=OFF SPR=OFF | Manual forcing needed
...
```

### New Timing (Cycle-Level)

```
Frame 1:  ~0.4s  | PPUMASK=$00 BG=OFF SPR=OFF | Natural behavior
Frame 2:  ~0.4s  | PPUMASK=$00 BG=OFF SPR=OFF | Natural behavior
Frame 3:  ~0.4s  | PPUMASK=$00 BG=OFF SPR=OFF | Natural behavior
Frame 4:  ~0.4s  | PPUMASK=$0E BG=ON  SPR=OFF | ← NATURALLY ENABLED!
Frame 5+: ~0.4s  | PPUMASK=$0E BG=ON  SPR=OFF | Rendering works
```

## Conclusion

✓ **Viewer works** - Shows graphics, proves emulation works
✗ **Viewer is slow** - Fundamental PHP serialization limitation
⚠️ **Good for dev** - Not suitable for end users

The timing fix was a success - rendering works naturally and is 2.5× faster. The remaining slowness is a PHP architecture limitation, not an emulation problem.

For serious gaming performance, we'd need:
- Option 2 (daemon) for PHP server-side
- Option 3 (JavaScript/WASM) for client-side

But for development and testing, the current viewer is adequate!

## Test Results

You can verify the viewer works by:

1. Open: `http://localhost:8080`
2. Click "Reset" - Wait ~1.6 seconds
3. Should see graphics (gray pixels on black background)
4. Click "Step" - Wait ~0.4 seconds per frame
5. Frame counter increments

The fact that frames render faster (~0.4s vs ~1.0s) shows the timing fix helps even without serialization!
