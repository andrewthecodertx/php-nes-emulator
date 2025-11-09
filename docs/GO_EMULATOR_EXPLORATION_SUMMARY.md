# Go NES Emulator Exploration Summary

## Overview

I have completed a comprehensive exploration of the Go NES emulator codebase at `/home/andrew/Projects/nes-emulator`. This analysis reveals why the Go version successfully renders Super Mario Bros frames while the PHP version struggles.

## Document Deliverables

Two detailed analysis documents have been created in the NES project directory:

### 1. `go_nes_analysis.md` (15 KB)
A complete architectural overview including:
- Project structure and file organization
- Component descriptions (NES, Bus, PPU, Cartridge, Controller)
- PPU rendering pipeline in detail
- Memory map and mirroring strategies
- Palette system (two-level indirection)
- Critical implementation details
- Available debug tools

**Key sections:**
- Overall architecture with class diagrams
- 3:1 PPU clock ratio explanation
- Frame buffer management
- Cycle-accurate timing relationships
- Super Mario Bros initialization sequence

### 2. `critical_implementation_patterns.md` (19 KB)
A practical guide for PHP implementation with:
- Side-by-side Go vs PHP patterns
- Frame buffer management patterns
- PPU clock hierarchy
- Scanline and cycle counter implementation
- Rendering flags and control
- Two-level palette system
- Pre-render scanline handling
- Shifter systems (background and sprites)
- Memory mirroring implementation
- Initialization flow

**Format:**
- Code examples in both Go and PHP
- Critical differences highlighted
- Implementation checklist

## Key Findings

### Architecture Overview

The Go emulator follows a clean 4-layer architecture:

```
CPU (external 6502 library)
  ↓
NES Coordinator
  ├── Bus (memory routing)
  ├── PPU (rendering engine)
  └── Cartridge (ROM/mapper)
```

### Why Go Version Shows Frames

**1. Proper Clock Hierarchy**
```
CPU.Step() → Bus.Clock() → PPU.Clock() × 3 → renderPixel()
```
- Each CPU instruction triggers **3 PPU clocks**
- Each PPU clock **immediately renders 1 pixel** to frame buffer

**2. Real-Time Frame Buffer Updates**
- Frame buffer contains **palette indices (0-63)**, not RGB
- Updated **every PPU cycle** (not deferred to frame end)
- Reflects **real-time PPU state**

**3. Accurate Timing**
- Scanline counter: -1 to 261 (supports pre-render)
- Cycle counter: 0-340 (341 cycles per line)
- 262 scanlines per frame
- Matches real hardware exactly

**4. Complete Feature Set**
- Background tile rendering with shifters
- Sprite evaluation and rendering (2-phase)
- Palette indirection (RAM → indices)
- Memory mirroring (nametables, palettes)
- PPU register handling
- NMI signaling

**5. Proper Initialization**
- Pre-runs 120 frames after Reset()
- Allows game to configure PPU
- Frame 121+ rendering is stable

### Critical Differences vs PHP

#### 1. Rendering Frequency
**Go**: Renders **every cycle** (61,440 writes/frame)
**PHP (likely)**: Renders only at **frame boundaries**
**Impact**: PHP frame buffer may not match game state

#### 2. Clock Architecture
**Go**: Clear 3:1 hierarchy with explicit calls
**PHP (likely)**: May use batch cycles instead of individual clocks
**Impact**: Timing sync between CPU/PPU breaks down

#### 3. Scanline Counter
**Go**: Uses `int16` to support -1 (pre-render)
**PHP (likely)**: Uses unsigned int, can't represent -1
**Impact**: Pre-render scanline logic skipped

#### 4. Frame Buffer Format
**Go**: Stores **palette indices** (0-63)
**PHP (likely)**: May store **RGB values directly**
**Impact**: Palette changes during rendering invisible

#### 5. Memory Mirroring
**Go**: Implements all mirroring types correctly
**PHP (likely)**: May have missing or incorrect mirroring
**Impact**: Memory access returns wrong data

#### 6. Initialization
**Go**: Pre-runs 120 frames explicitly
**PHP (likely)**: No initialization period
**Impact**: Game PPU config not applied before rendering

## Technical Deep Dives

### Frame Buffer Architecture

**Go Pattern (Correct)**:
```go
// Frame buffer: palette indices only
frameBuffer [ScreenWidth * ScreenHeight]uint8  // 0-63

// Per-cycle render
p.frameBuffer[y*ScreenWidth+x] = paletteIndex

// At display time: convert to RGB
color := HardwarePalette[paletteIndex]
```

**Key Insight**: Frame buffer is **independent of display format**. Same buffer works with SDL2, image files, network streaming, etc.

### PPU Rendering Pipeline

**Visible Scanline (0-239) Cycle Sequence**:
```
Cycle 0: Idle
Cycles 1-256: Render pixels + fetch tiles (8-cycle pattern)
Cycles 257-320: Sprite evaluation and fetching
Cycles 321-340: Extra nametable fetches
```

**Fetching Pattern (8 cycles = 8 pixels)**:
```
Cycle 0: Load shifters from previous fetch
Cycle 1: Fetch nametable tile ID
Cycle 2: Fetch attribute byte
Cycle 3: Fetch pattern low byte
Cycle 4: Fetch pattern high byte
Cycle 5: (Superfluous fetch)
Cycle 6: Increment X (if rendering enabled)
Cycle 7: (Superfluous fetch)
```

### Background Shifter System

**How it works**:
```
┌─────────────────────────────────────────┐
│ bgShifterPatternLo (16 bits)            │
│ ┌────────────┬──────────────────────┐   │
│ │ Current 8  │ Next 8 (being loaded)│   │
│ └────────────┴──────────────────────┘   │
└─────────────────────────────────────────┘
     Every cycle: Shift left by 1
     Every 8 cycles: Load new 8 pixels
```

**Rendering**: Extract MSB at each cycle based on fine X position.

### Sprite Rendering Pipeline

**Two Phases**:

1. **Evaluation** (cycle 257):
   - Scan all 64 sprites in OAM
   - Find up to 8 visible on current scanline
   - Copy to secondary OAM
   - Set overflow flag if >8

2. **Fetching** (cycle 320):
   - Load pattern data for each visible sprite
   - Handle 8x8 or 8x16 sizes
   - Support horizontal/vertical flip
   - Store in shifters

**Rendering**: During pixel output, check each sprite's X position, extract pixel from shifters, composite with background.

## Implementation Checklist for PHP Port

### Must Have (Non-Negotiable)
1. ✓ Frame buffer: 256×240 bytes (palette indices 0-63)
2. ✓ PPU.Clock() called 3x per CPU instruction
3. ✓ Scanline counter: signed int, range -1 to 261
4. ✓ Cycle counter: 0-340 per scanline
5. ✓ renderPixel() called during cycles 1-256 of scanlines 0-239
6. ✓ 16-bit background shifters with load/update operations
7. ✓ Sprite evaluation: secondary OAM, count, overflow
8. ✓ Sprite fetching: load pattern data, handle flips
9. ✓ Rendering disabled: output backdrop color
10. ✓ Palette indirection: RAM → indices → RGB (at display time)
11. ✓ Memory mirroring: CPU RAM, PPU reg, nametables, palettes
12. ✓ Pre-render scanline: flag clearing, Y transfer
13. ✓ VBlank and NMI signaling
14. ✓ 120-frame pre-initialization

### Most Common Failure Points
1. Frame buffer updated only at frame end (not per cycle)
2. PPU clock not 3:1 ratio with CPU
3. Scanline counter can't represent -1
4. Pre-render scanline skipped
5. Shifters not implemented (using pixel-by-pixel instead)
6. Palette stored as RGB (not indices)
7. Rendering flag always enabled
8. No initialization period

## Mapping Go Code to PHP Structure

### File Organization Recommendation
```
PHP Project Structure
├── src/NES/
│   ├── NES.php              ← Main coordinator
│   ├── Bus.php              ← Memory routing
│   ├── PPU/
│   │   ├── PPU.php          ← Main PPU (~600 lines)
│   │   ├── Rendering.php    ← renderPixel, shifters
│   │   ├── Sprites.php      ← Sprite evaluation/fetching
│   │   └── Registers.php    ← Register implementations
│   ├── Cartridge/
│   │   ├── Cartridge.php
│   │   ├── Mapper.php
│   │   └── Mapper0.php
│   ├── Controller.php
│   └── Palette.php          ← Hardware palette
└── tests/
    ├── NESTest.php
    └── PPUTest.php
```

### Class Mapping
| Go Package | Go Type | PHP Class |
|-----------|---------|-----------|
| nes | NES | NES |
| bus | NESBus | Bus |
| ppu | PPU | PPU |
| ppu | PPUControl | PPUControl |
| ppu | PPUMask | PPUMask |
| ppu | PPUStatus | PPUStatus |
| ppu | LoopyRegister | LoopyRegister |
| cartridge | Mapper (interface) | Mapper (interface) |
| cartridge | Mapper0 | Mapper0 |
| controller | Controller | Controller |

## Testing Strategy

### Unit Tests to Write
1. **PPU Timing**: Verify clock counters advance correctly
2. **Frame Buffer**: Verify pixels written at correct addresses
3. **Shifters**: Verify load/shift operations
4. **Palette**: Verify indirection (palette → indices)
5. **Memory Mirroring**: Verify all mirroring modes
6. **Sprite Evaluation**: Verify secondary OAM correctness
7. **NMI Signaling**: Verify VBlank triggers NMI

### Integration Tests
1. **Simple ROM**: Run test ROM, verify frame buffer contents
2. **Super Mario**: Run 120 frames, verify stable rendering
3. **Donkey Kong**: Test different mapper/mirroring
4. **Scanline Timing**: Verify PPU scanline interrupts at correct times

## References to Go Implementation

When porting to PHP, refer to these Go files for exact behavior:

| Task | Go File | Line Range |
|------|---------|-----------|
| Clock hierarchy | pkg/nes/nes.go | 64-81 |
| Bus Clock | pkg/bus/bus.go | 112-148 |
| PPU Clock | pkg/ppu/ppu.go | 230-392 |
| Render pixel | pkg/ppu/rendering.go | 43-143 |
| Background shifters | pkg/ppu/rendering.go | 5-41 |
| Sprite evaluation | pkg/ppu/sprites.go | 7-61 |
| Sprite fetching | pkg/ppu/sprites.go | 63-138 |
| Registers | pkg/ppu/registers.go | 1-373 |
| Palette mirroring | pkg/ppu/ppu.go | 594-604 |
| Memory map | pkg/bus/bus.go | 11-21 |

## Path Forward

1. **Immediate**: Use `critical_implementation_patterns.md` as a checklist
2. **Short-term**: Implement frame buffer and PPU clock hierarchy
3. **Medium-term**: Add shifter systems and sprite rendering
4. **Long-term**: Test with multiple ROMs, optimize performance

The Go implementation provides a reference architecture that works correctly. Every pattern shown can be directly translated to PHP with careful attention to clock timing and frame buffer updates.

---

**Documentation Created**: November 5, 2025
**Go Emulator Location**: `/home/andrew/Projects/nes-emulator`
**Analysis Documents**: `/mnt/internalssd/Projects/NES/go_nes_analysis.md` and `critical_implementation_patterns.md`
