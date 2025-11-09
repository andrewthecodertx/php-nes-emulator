# Critical Go Emulator Implementation Patterns for PHP Port

## 1. Frame Buffer Management

### Go Pattern (Immediate, Real-Time)
```go
// PPU.renderPixel() - called EVERY CYCLE during scanlines 0-239, cycles 1-256
func (p *PPU) renderPixel() {
    x := p.cycle - 1
    y := uint16(p.scanline)
    
    // Render pixel logic...
    
    // IMMEDIATELY write to frame buffer
    p.frameBuffer[y*ScreenWidth+x] = colorIndex  // palette index 0-63
}
```

**Frequency**: 256 pixels/scanline × 240 scanlines = 61,440 writes per frame

### PHP Equivalent Pattern Needed
```php
// Must call this EVERY cycle (or at least every pixel rendered)
public function renderPixel(int $x, int $y, int $paletteIndex): void {
    $this->frameBuffer[$y * self::SCREEN_WIDTH + $x] = $paletteIndex;
}
```

### Critical Difference
- **Go**: Renders immediately during Clock()
- **PHP**: May be deferring render until frame end
- **Impact**: Frame buffer reflects PPU state in real-time

---

## 2. PPU Clock Hierarchy

### Go Pattern (3:1 Ratio)
```go
// NES.Step() - CPU instruction
func (n *NES) Step() uint8 {
    n.cpu.Step()
    n.bus.Clock()  // Single call per CPU instruction
    // ...
}

// Bus.Clock() - called once per CPU instruction
func (b *NESBus) Clock() {
    b.ppu.Clock()  // Call 3 times
    b.ppu.Clock()
    b.ppu.Clock()
    // Handle DMA...
}

// PPU.Clock() - called 3 times per CPU instruction
func (p *PPU) Clock() {
    if p.scanline >= 0 && p.scanline < 240 {
        if p.cycle >= 1 && p.cycle <= 256 {
            p.renderPixel()  // Render during visible area
        }
    }
    p.cycle++
    // ... advance scanline, etc
}
```

### PHP Equivalent Pattern Needed
```php
public function step(): void {
    $this->cpu->step();
    $this->bus->clock();
}

public function busClockOnce(): void {
    $this->ppu->clock();
    $this->ppu->clock();
    $this->ppu->clock();
    // Handle DMA
}

public function ppuClockOnce(): void {
    if ($this->scanline >= 0 && $this->scanline < 240) {
        if ($this->cycle >= 1 && $this->cycle <= 256) {
            $this->renderPixel();
        }
    }
    $this->cycle++;
    if ($this->cycle >= 341) {
        $this->cycle = 0;
        $this->scanline++;
        // ... etc
    }
}
```

### Critical Difference
- **Go**: Clear 3:1 hierarchy with explicit calls
- **PHP**: May be missing proper Bus.Clock → PPU.Clock → renderPixel chain
- **Impact**: PPU may not advance correctly, rendering timing off

---

## 3. Scanline and Cycle Counters

### Go Pattern (Separate, Precise)
```go
type PPU struct {
    scanline int16  // -1 to 261
    cycle uint16    // 0 to 340
    frame uint64    // Frame counter
}

// Advance scanline at end of each line
if p.cycle >= CyclesPerScanline {  // >= 341
    p.cycle = 0
    p.scanline++
    
    if p.scanline >= ScanlinesPerFrame {  // >= 262
        p.scanline = -1  // Reset to pre-render line
        p.frameComplete = true
    }
}
```

### Key Values to Match
- Pre-render scanline: **-1**
- Visible scanlines: **0-239**
- Post-render: **240**
- VBlank: **241-260**
- Pre-render (actual): **261**
- Cycles per scanline: **341** (0-340)
- Scanlines per frame: **262**

### Critical Events by Scanline:
```
Scanline -1 (pre-render):
  - Cycle 1: Clear flags
  - Cycles 280-304: Transfer Y from temp register
  - Throughout: Fetch tiles (8-cycle pattern)
  
Scanlines 0-239 (visible):
  - Cycles 1-256: Render visible pixels
  - Cycles 257-320: Sprite evaluation and fetching
  - Throughout: Tile fetching (8-cycle pattern)
  
Scanline 241 (VBlank start):
  - Cycle 1: Set VBlank flag, trigger NMI
  
Scanlines 241-260 (VBlank):
  - No rendering
  
Scanline 261 (not used in our implementation):
  - Pre-render for next frame
```

### PHP Equivalent Pattern Needed
```php
class PPU {
    private int $scanline = 0;    // Must support -1
    private int $cycle = 0;       // 0-340
    private int $frame = 0;
    
    public function clock(): void {
        // Render pixel first (before updating counters)
        if ($this->scanline >= 0 && $this->scanline < 240) {
            if ($this->cycle >= 1 && $this->cycle <= 256) {
                $this->renderPixel();
            }
        }
        
        // Then update state
        $this->cycle++;
        if ($this->cycle >= 341) {
            $this->cycle = 0;
            $this->scanline++;
            
            if ($this->scanline >= 262) {
                $this->scanline = -1;
                $this->frameComplete = true;
                $this->frame++;
            }
        }
    }
}
```

### Critical Difference
- **Go**: Uses int16 for scanline to allow -1
- **PHP**: May be using unsigned int, preventing -1
- **Impact**: Pre-render scanline not handled correctly

---

## 4. Rendering Flags and Control

### Go Pattern (Register-Based Control)
```go
// PPUMASK register controls rendering
type PPUMask struct {
    register uint8
}

func (m *PPUMask) IsRenderingEnabled() bool {
    return m.RenderBackground() || m.RenderSprites()
}

func (m *PPUMask) RenderBackground() bool {
    return (m.register>>3)&0x01 != 0  // Bit 3
}

func (m *PPUMask) RenderSprites() bool {
    return (m.register>>4)&0x01 != 0  // Bit 4
}

// In renderPixel()
if !p.mask.IsRenderingEnabled() {
    // Rendering disabled - output backdrop color
    backdropColor := p.ppuRead(0x3F00) & 0x3F
    p.frameBuffer[y*ScreenWidth+x] = backdropColor
    return
}
```

### Critical Behavior
1. When rendering is **enabled**: Composite background + sprites
2. When rendering is **disabled**: Output backdrop color (palette[$3F00])
3. Rendering can be **toggled mid-frame**

### PHP Equivalent Pattern Needed
```php
public function renderPixel(): void {
    // Check if rendering enabled
    if (!$this->mask->isRenderingEnabled()) {
        // Output backdrop color from palette[0]
        $backdropIndex = $this->paletteRAM[0] & 0x3F;
        $this->frameBuffer[$y * self::SCREEN_WIDTH + $x] = $backdropIndex;
        return;
    }
    
    // Full rendering...
}
```

### Critical Difference
- **Go**: Explicitly checks rendering flags
- **PHP**: May assume rendering is always on
- **Impact**: Games with disabled rendering show wrong colors

---

## 5. Palette Access Pattern

### Go Pattern (Two-Level Palette System)
```go
// Level 1: Palette RAM (32 bytes)
paletteRAM [32]uint8  // Game writes colors here

// Level 2: Hardware palette (64 RGB colors)
var HardwarePalette = [64]Color{ ... }

// To render a pixel:
// 1. Calculate palette address from pixel data
address := uint16((finalPalette << 2) | (finalPixel & 0x03))

// 2. Read from palette RAM
colorIndex := p.ppuRead(0x3F00+address) & 0x3F

// 3. Write palette index (not RGB!) to frame buffer
p.frameBuffer[y*ScreenWidth+x] = colorIndex

// Later, when displaying:
// 4. Convert palette index to RGB for display
color := ppu.HardwarePalette[colorIndex]
```

### Key Pattern
1. **Frame buffer stores palette indices (0-63)**, not RGB
2. **PPU palette RAM holds indirect color references**
3. **Hardware palette maps indices to RGB**
4. **Conversion happens at display time**

### PHP Equivalent Pattern Needed
```php
public function renderPixel(): void {
    // Calculate palette address
    $address = ($finalPalette << 2) | ($finalPixel & 0x03);
    
    // Read color index from palette RAM
    $colorIndex = $this->paletteRAM[$address] & 0x3F;
    
    // Write palette index to frame buffer
    $this->frameBuffer[$y * self::SCREEN_WIDTH + $x] = $colorIndex;
}

// When displaying to screen:
public function getFrameBufferAsRGB(): array {
    $rgb = [];
    foreach ($this->frameBuffer as $paletteIndex) {
        $color = self::HARDWARE_PALETTE[$paletteIndex];
        $rgb[] = [$color['r'], $color['g'], $color['b']];
    }
    return $rgb;
}
```

### Critical Difference
- **Go**: Frame buffer holds palette indices, converts at display time
- **PHP**: May be storing RGB directly in frame buffer
- **Impact**: Palette changes don't affect already-rendered pixels

---

## 6. Pre-Render Scanline Handling

### Go Pattern (Exact Hardware Emulation)
```go
// Scanline -1 is the pre-render scanline
if p.scanline == -1 {
    // Cycle 1: Clear flags
    if p.cycle == 1 {
        p.status.SetVBlank(false)
        p.status.SetSprite0Hit(false)
        p.status.SetSpriteOverflow(false)
        p.frameComplete = false
    }
    
    // Cycles 280-304: Transfer Y (vertical position)
    if p.cycle >= 280 && p.cycle < 305 {
        if p.mask.IsRenderingEnabled() {
            p.vramAddress.TransferY(&p.tempVRAMAddress)
        }
    }
    
    // Same tile fetching as visible scanlines
    // (8-cycle fetch pattern)
}

// Odd frame cycle skip
if p.scanline == 0 && (p.frame&1) == 1 && p.mask.IsRenderingEnabled() {
    p.cycle = 1  // Skip cycle 0 on odd frames
}
```

### Critical Behavior
1. Pre-render scanline executes **full tile fetching**
2. Vertical position **transferred at cycles 280-304**
3. Odd frames **skip one cycle** (timing quirk)
4. Flags cleared **at cycle 1**

### PHP Equivalent Pattern Needed
```php
public function clock(): void {
    // Handle pre-render scanline
    if ($this->scanline == -1) {
        if ($this->cycle == 1) {
            $this->status->clearVBlank();
            $this->status->clearSprite0Hit();
            $this->status->clearSpriteOverflow();
            $this->frameComplete = false;
        }
        
        // Transfer Y address
        if ($this->cycle >= 280 && $this->cycle < 305) {
            if ($this->mask->isRenderingEnabled()) {
                $this->vramAddress->transferY($this->tempVramAddress);
            }
        }
    }
    
    // Odd frame skip
    if ($this->scanline == 0 && ($this->frame & 1) == 1 && 
        $this->mask->isRenderingEnabled()) {
        $this->cycle = 1;
    }
    
    // ... rest of cycle handling
}
```

---

## 7. Shifter System (Background Rendering)

### Go Pattern (16-bit Pattern Shifters)
```go
// Shifters hold 16 bits: top 8 = current, bottom 8 = next
bgShifterPatternLo uint16
bgShifterPatternHi uint16
bgShifterAttribLo uint16
bgShifterAttribHi uint16

// Load shifters (every 8 cycles)
func (p *PPU) loadBackgroundShifters() {
    p.bgShifterPatternLo = (p.bgShifterPatternLo & 0xFF00) | uint16(p.bgNextTileLSB)
    p.bgShifterPatternHi = (p.bgShifterPatternHi & 0xFF00) | uint16(p.bgNextTileMSB)
    
    // Inflate 2-bit palette to 8 bits
    if p.bgNextTileAttrib&0x01 != 0 {
        p.bgShifterAttribLo = (p.bgShifterAttribLo & 0xFF00) | 0x00FF
    } else {
        p.bgShifterAttribLo = (p.bgShifterAttribLo & 0xFF00)
    }
    // Similar for attrib high...
}

// Update shifters (every cycle)
func (p *PPU) updateShifters() {
    if p.mask.RenderBackground() {
        p.bgShifterPatternLo <<= 1
        p.bgShifterPatternHi <<= 1
        p.bgShifterAttribLo <<= 1
        p.bgShifterAttribHi <<= 1
    }
}

// Extract pixel during rendering
bitMux := uint16(0x8000 >> p.fineX)  // Start from MSB
p0 := uint8(0)
if p.bgShifterPatternLo&bitMux != 0 {
    p0 = 1
}
p1 := uint8(0)
if p.bgShifterPatternHi&bitMux != 0 {
    p1 = 1
}
bgPixel = (p1 << 1) | p0
```

### PHP Equivalent Pattern Needed
```php
private int $bgShifterPatternLo = 0;
private int $bgShifterPatternHi = 0;
private int $bgShifterAttribLo = 0;
private int $bgShifterAttribHi = 0;

public function loadBackgroundShifters(): void {
    // Load new 8 pixels into low byte
    $this->bgShifterPatternLo = ($this->bgShifterPatternLo & 0xFF00) | 
                                 $this->bgNextTileLSB;
    $this->bgShifterPatternHi = ($this->bgShifterPatternHi & 0xFF00) | 
                                 $this->bgNextTileMSB;
    
    // Inflate palette bits
    if ($this->bgNextTileAttrib & 0x01) {
        $this->bgShifterAttribLo = ($this->bgShifterAttribLo & 0xFF00) | 0x00FF;
    } else {
        $this->bgShifterAttribLo &= 0xFF00;
    }
    // ... similar for high byte
}

public function updateShifters(): void {
    if ($this->mask->renderBackground()) {
        $this->bgShifterPatternLo = ($this->bgShifterPatternLo << 1) & 0xFFFF;
        $this->bgShifterPatternHi = ($this->bgShifterPatternHi << 1) & 0xFFFF;
        $this->bgShifterAttribLo = ($this->bgShifterAttribLo << 1) & 0xFFFF;
        $this->bgShifterAttribHi = ($this->bgShifterAttribHi << 1) & 0xFFFF;
    }
}

public function getBackgroundPixel(): int {
    $bitMux = 0x8000 >> $this->fineX;
    
    $p0 = ($this->bgShifterPatternLo & $bitMux) ? 1 : 0;
    $p1 = ($this->bgShifterPatternHi & $bitMux) ? 1 : 0;
    
    return ($p1 << 1) | $p0;
}
```

---

## 8. Sprite Evaluation and Rendering

### Go Pattern (Two-Phase: Evaluation + Fetching)
```go
// Phase 1: Evaluation (cycles 257 for next scanline)
func (p *PPU) spriteEvaluation() {
    // Clear secondary OAM
    p.spriteCount = 0
    
    // Scan all 64 sprites
    for i := uint8(0); i < 64; i++ {
        spriteY := uint16(p.oam[i*4])
        diff := uint16(p.scanline) - spriteY
        
        if diff < spriteHeight {  // 8 or 16
            if p.spriteCount >= 8 {
                p.status.SetSpriteOverflow(true)
                break
            }
            // Copy to secondary OAM
            p.spriteCount++
        }
    }
}

// Phase 2: Fetching (cycle 320)
func (p *PPU) spriteFetching() {
    for i := uint8(0); i < p.spriteCount; i++ {
        // Fetch pattern data for each sprite
        patternLow := p.ppuRead(patternAddress)
        patternHigh := p.ppuRead(patternAddress + 8)
        
        // Handle flips
        if attributes&0x40 != 0 {
            patternLow = reverseByte(patternLow)
            patternHigh = reverseByte(patternHigh)
        }
        
        p.spriteShifterPatternLo[i] = patternLow
        p.spriteShifterPatternHi[i] = patternHigh
    }
}
```

### PHP Equivalent Pattern Needed
```php
private array $secondaryOAM = [];
private int $spriteCount = 0;

public function spriteEvaluation(): void {
    $this->secondaryOAM = [];
    $this->spriteCount = 0;
    
    $spriteHeight = $this->control->spriteSize() ? 16 : 8;
    
    for ($i = 0; $i < 64; $i++) {
        $spriteY = $this->oam[$i * 4];
        $diff = $this->scanline - $spriteY;
        
        if ($diff >= 0 && $diff < $spriteHeight) {
            if ($this->spriteCount >= 8) {
                $this->status->setSpriteOverflow(true);
                break;
            }
            // Copy to secondary OAM
            $this->secondaryOAM[] = [
                $this->oam[$i * 4],
                $this->oam[$i * 4 + 1],
                $this->oam[$i * 4 + 2],
                $this->oam[$i * 4 + 3],
            ];
            $this->spriteCount++;
        }
    }
}
```

---

## 9. Memory Mirroring

### Go Pattern (Masking and Mirroring Functions)
```go
// CPU RAM mirroring (2KB RAM, accessible at $0000-$1FFF)
if addr < 0x2000 {
    return b.cpuRAM[addr&0x07FF]  // 11-bit mask
}

// PPU register mirroring (8 registers, mirrored every 8 bytes)
if addr < 0x4000 {
    return b.ppu.ReadCPURegister(0x2000 + (addr & 0x0007))
}

// Nametable mirroring (depends on mode)
func (p *PPU) mirrorNametableAddress(addr uint16) uint16 {
    addr = (addr - 0x2000) % 0x1000
    table := addr / 0x0400
    offset := addr % 0x0400
    
    switch p.mirroringMode {
    case MirrorVertical:
        return addr % 0x0800
    case MirrorHorizontal:
        return (table/2)*0x0400 + offset
    case MirrorSingleLow:
        return offset
    case MirrorSingleHigh:
        return 0x0400 + offset
    case MirrorFourScreen:
        return addr
    }
    return 0
}

// Palette mirroring
func (p *PPU) mirrorPaletteAddress(addr uint16) uint16 {
    addr = (addr - 0x3F00) % 32
    // Mirror first colors of sprite palettes to background
    if addr >= 16 && addr%4 == 0 {
        addr -= 16
    }
    return addr
}
```

### Critical Mirroring Rules
1. **CPU RAM**: $0000-$07FF (2KB) repeated 4 times to $0000-$1FFF
2. **PPU registers**: 8 registers at $2000-$2007, repeated every 8 bytes
3. **Nametables**: 2KB RAM, can mirror horizontal, vertical, single, or 4-screen
4. **Palette**: 32 bytes, sprite palette first colors mirror background

### PHP Equivalent Pattern Needed
```php
public function cpuRead(int $addr): int {
    if ($addr < 0x2000) {
        return $this->cpuRAM[$addr & 0x07FF];
    }
    if ($addr < 0x4000) {
        return $this->ppu->readRegister(0x2000 + ($addr & 0x0007));
    }
    // ... etc
}

public function ppuRead(int $addr): int {
    $addr &= 0x3FFF;  // 14-bit address space
    
    if ($addr < 0x2000) {
        return $this->mapper->readCHR($addr);
    }
    if ($addr < 0x3F00) {
        return $this->nametable[$this->mirrorNametableAddress($addr)];
    }
    return $this->paletteRAM[$this->mirrorPaletteAddress($addr)];
}
```

---

## 10. NES Initialization Flow

### Go Pattern (Explicit Initialization)
```go
// Load ROM
emulator, err := nes.New(romPath)

// Reset to power-on state
emulator.Reset()

// Run 120 frames of initialization
// This allows game code to configure PPU
for i := 0; i < 120; i++ {
    emulator.RunFrame()
}

// Now rendering is stable
```

### Key Points
1. **Reset()** clears all PPU state
2. **First ~120 frames**: Game configures PPU
3. **Frame 121+**: Rendering becomes stable

### PHP Equivalent Pattern Needed
```php
// Load ROM and create emulator
$emulator = new NES($romPath);

// Reset
$emulator->reset();

// Run initialization frames
for ($i = 0; $i < 120; $i++) {
    $emulator->runFrame();
}

// Now safe to use frame buffer
```

### Critical for Super Mario Bros
- Game writes palette data during frames 1-30
- Sets nametables during frames 30-60
- Positions sprites during frames 60-120
- Rendering becomes visible around frame 121

---

## Summary: Key Implementation Checklist

### Must Have
1. ✓ Frame buffer with 256×240×1 byte (palette indices 0-63)
2. ✓ PPU clock 3x per CPU instruction
3. ✓ Scanline counter supporting -1 (pre-render)
4. ✓ Cycle counter 0-340 per scanline
5. ✓ Immediate pixel rendering to frame buffer
6. ✓ Background shifter system (16-bit shift registers)
7. ✓ Sprite evaluation and fetching (two phases)
8. ✓ Rendering flag checks (enable/disable)
9. ✓ Palette RAM (32 bytes, indexed)
10. ✓ Hardware palette lookup (64 colors)
11. ✓ Memory mirroring (CPU RAM, PPU reg, nametable, palette)
12. ✓ Pre-render scanline handling
13. ✓ VBlank and NMI signaling
14. ✓ 120-frame pre-initialization

### Most Likely Issues in PHP Version
- [ ] Frame buffer not updated every cycle
- [ ] PPU clock not 3:1 ratio
- [ ] Scanline counter doesn't support -1
- [ ] Pre-render scanline not handled
- [ ] Shifters not implemented correctly
- [ ] Palette indirection missing (storing RGB instead)
- [ ] Rendering not disabled when flag clear
- [ ] No initialization frame pre-run

