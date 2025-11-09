# NES Emulator Web Viewer

## Quick Start

The PHP development server is already running at <http://localhost:8000>

**Open the viewer in your browser:**

```
http://localhost:8000/viewer.html
```

## Features

### Visual Display

- **256x240 NES resolution** scaled 2x for visibility (512x480 on screen)
- Real-time frame buffer rendering
- Pixel-perfect rendering with crisp edges

### Controls

- **Reset Button**: Reload ROM and start fresh
- **Step 1 Frame**: Execute one frame (~16.67ms of game time)
- **Step 10 Frames**: Execute 10 frames (~167ms of game time)
- **Step 100 Frames**: Execute 100 frames (~1.67s of game time)
- **Run Continuous**: Keep stepping frames automatically

### Information Panels

**PPU State:**

- Frame Count: Number of frames rendered by PPU
- Scanline: Current scanline (-1 to 260)
- Cycle: Current cycle within scanline (0-340)
- Unique Colors: Number of different colors visible

**Performance:**

- Render Time: How long the last frame took to render
- FPS: Frames per second (based on render time)
- Total Frames: Total frames stepped through
- Status: Idle or Running

**Activity Log:**

- Real-time log of emulator actions
- Shows timing information
- Error messages if any occur

## Performance Notes

- Each frame takes approximately **4-5 seconds** to render in PHP
- This is expected for an interpreter-based emulator
- "Step 1 Frame" will take ~4-5 seconds
- "Step 10 Frames" will take ~40-50 seconds
- "Step 100 Frames" will take ~400-500 seconds (~7-8 minutes)

The emulator maintains deterministic state, so stepping to frame 100 will always
produce the same output regardless of how you get there (1 step 100 times vs. 1
step of 100).

## Technical Details

### Architecture

- **Frontend**: HTML5 Canvas + Vanilla JavaScript
- **Backend**: PHP REST API with session management
- **Transport**: JSON for frame data (256x240x3 RGB array)

### API Endpoints

**Reset:**

```
GET /emulator_backend.php?action=reset
```

Reloads ROM and resets to initial state.

**Step:**

```
GET /emulator_backend.php?action=step&frames=N
```

Executes N frames and returns updated state.

**Status:**

```
GET /emulator_backend.php?action=status
```

Returns current state without stepping.

### Session Management

Frame count is tracked in PHP sessions. Each step action:

1. Loads ROM fresh
2. Runs emulation to target frame
3. Returns frame buffer and state

This approach avoids serialization issues with closures but means
each request re-executes all frames from the beginning. For performance,
use larger step counts rather than many small steps.

## Troubleshooting

### Server Not Running

If you get connection errors, restart the server:

```bash
php -S localhost:8000
```

### Slow Performance

This is normal for PHP! Each frame renders in ~4-5 seconds.

- Use larger step counts (10, 100) for faster progression
- Consider using continuous mode and letting it run in background
- Graphics should appear after 50-100 frames

### Blank/Gray Screen

After reset, the screen starts black. After the first frame, it will show gray.
Donkey Kong needs many frames (50-200) to fully initialize before graphics appear.

## What You're Seeing

The emulator renders exactly what the NES PPU outputs:

- **Initially**: Black screen (uninitialized frame buffer)
- **Frame 1+**: Gray screen (game fills nametable with empty tiles)
- **Frame 50-200**: Graphics should start appearing as game initializes palettes
and tile data

The gray color (RGB 84, 84, 84) is NES system palette color $00, which is the
default background color before the game writes palette data.

## Next Steps

Once you can see the gray screen rendering:

- Step through 100-200 frames to see when graphics initialize
- Watch the "Unique Colors" counter - when it jumps above 1-2, graphics are
starting to appear
- Monitor the log for any errors or interesting state changes

Enjoy watching Donkey Kong come to life!
