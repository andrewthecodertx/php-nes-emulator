<?php

declare(strict_types=1);

namespace andrewthecoder\nes\PPU;

use andrewthecoder\nes\PPU\PPUControl;
use andrewthecoder\nes\PPU\PPUMask;
use andrewthecoder\nes\PPU\PPUStatus;
use andrewthecoder\nes\PPU\LoopyRegister;
use andrewthecoder\nes\Cartridge\MapperInterface;

/**
 * NES Picture Processing Unit (2C02) Emulator
 *
 * The PPU is the graphics processor for the NES. It generates video signals
 * at 256x240 resolution by rendering background tiles and sprites.
 *
 * Hardware Specifications:
 * - Clock speed: ~5.37 MHz (NTSC) / ~5.32 MHz (PAL)
 * - Runs 3x faster than CPU (~1.79 MHz)
 * - 341 PPU cycles per scanline
 * - 262 scanlines per frame (NTSC) / 312 (PAL)
 * - Output: 256 pixels wide x 240 pixels tall
 *
 * Memory Map:
 * - $0000-$0FFF: Pattern Table 0 (4KB, CHR-ROM/RAM)
 * - $1000-$1FFF: Pattern Table 1 (4KB, CHR-ROM/RAM)
 * - $2000-$23FF: Nametable 0 (1KB)
 * - $2400-$27FF: Nametable 1 (1KB)
 * - $2800-$2BFF: Nametable 2 (1KB)
 * - $2C00-$2FFF: Nametable 3 (1KB)
 * - $3000-$3EFF: Mirrors of $2000-$2EFF
 * - $3F00-$3F1F: Palette RAM (32 bytes)
 * - $3F20-$3FFF: Mirrors of $3F00-$3F1F
 */
class PPU
{
    // ========================================================================
    // Memory Banks
    // ========================================================================

    /**
     * Nametable RAM (2KB internal)
     *
     * The NES has 2KB of internal VRAM for nametables. The full 4KB nametable
     * space ($2000-$2FFF) is mapped to this 2KB using mirroring modes.
     *
     * @var array<int> 2048 bytes
     */
    private array $nametable = [];

    /**
     * Palette RAM (32 bytes)
     *
     * Memory map:
     * $3F00-$3F0F: Background palettes (4 palettes x 4 colors)
     * $3F10-$3F1F: Sprite palettes (4 palettes x 4 colors)
     *
     * Note: $3F10, $3F14, $3F18, $3F1C are mirrored to $3F00, $3F04, $3F08, $3F0C
     * (transparent color is shared between background and sprites)
     *
     * @var array<int> 32 bytes (0x00-0xFF each)
     */
    private array $paletteRam = [];

    /**
     * Pattern Table RAM (8KB, for testing)
     *
     * On real hardware, this would be CHR-ROM on the cartridge.
     * For testing purposes, we provide writable RAM.
     *
     * Memory map:
     * $0000-$0FFF: Pattern Table 0 (4KB)
     * $1000-$1FFF: Pattern Table 1 (4KB)
     *
     * Each tile is 16 bytes (8 bytes low plane + 8 bytes high plane)
     *
     * @var array<int> 8192 bytes
     */
    private array $patternTable = [];

    /**
     * Object Attribute Memory (256 bytes)
     *
     * Contains sprite data for 64 sprites (4 bytes each):
     * Byte 0: Y position (top of sprite)
     * Byte 1: Tile index
     * Byte 2: Attributes (palette, priority, flip flags)
     * Byte 3: X position (left of sprite)
     *
     * @var array<int> 256 bytes
     */
    private array $oam = [];

    /**
     * OAM Address register ($2003)
     * Points to current position in OAM for CPU read/write
     *
     * @var int 0x00-0xFF
     */
    private int $oamAddress = 0x00;

    /**
     * Cartridge mapper for CHR-ROM/CHR-RAM access
     *
     * @var MapperInterface|null
     */
    private ?MapperInterface $mapper = null;

    /**
     * Nametable mirroring mode
     * 0 = Horizontal (vertical arrangement)
     * 1 = Vertical (horizontal arrangement)
     *
     * @var int
     */
    private int $mirroringMode = 0;

    // ========================================================================
    // PPU Registers (CPU-visible at $2000-$2007)
    // ========================================================================

    /**
     * PPUCTRL ($2000) - Control Register
     */
    private PPUControl $control;

    /**
     * PPUMASK ($2001) - Mask Register
     */
    private PPUMask $mask;

    /**
     * PPUSTATUS ($2002) - Status Register
     */
    private PPUStatus $status;

    /**
     * VRAM Address Register (set via $2006)
     * This is the current address the PPU will read/write
     * Also known as "v" in Loopy's documentation
     *
     * @var LoopyRegister
     */
    private LoopyRegister $vramAddress;

    /**
     * Temporary VRAM Address Register
     * Also used for scroll position (Loopy register)
     * Also known as "t" in Loopy's documentation
     *
     * @var LoopyRegister
     */
    private LoopyRegister $tempVramAddress;

    /**
     * Fine X scroll (3 bits)
     * Horizontal pixel offset within a tile
     *
     * @var int 0-7
     */
    private int $fineX = 0x00;

    /**
     * Address latch for dual-write registers
     * Toggles between first and second write for $2005 and $2006
     *
     * @var bool false = first write, true = second write
     */
    private bool $addressLatch = false;

    /**
     * Data buffer for PPUDATA reads ($2007)
     * Reads from PPUDATA are buffered by one cycle
     *
     * @var int 0x00-0xFF
     */
    private int $dataBuffer = 0x00;

    /**
     * NMI interrupt flag
     * Set when vblank starts and NMI is enabled
     * CPU should poll this and call nmi() when true
     *
     * @var bool
     */
    private bool $nmi = false;

    /**
     * PPU warm-up flag
     * Writes to PPUCTRL, PPUMASK, PPUSCROLL, PPUADDR are ignored
     * until the end of the first VBlank period after reset
     *
     * @var bool
     */
    private bool $inWarmup = true;

    // ========================================================================
    // Performance Optimization - Cached Flags
    // ========================================================================

    /**
     * Cached rendering enabled flag (updated when PPUMASK changes)
     * @var bool
     */
    private bool $cachedRenderingEnabled = false;

    /**
     * Cached render background flag (updated when PPUMASK changes)
     * @var bool
     */
    private bool $cachedRenderBackground = false;

    // ========================================================================
    // Background Rendering State
    // ========================================================================

    /**
     * Next background tile ID from nametable
     * Fetched during background rendering pipeline
     *
     * @var int 0x00-0xFF
     */
    private int $bgNextTileId = 0x00;

    /**
     * Next background tile attribute
     * Contains palette selection (2 bits)
     *
     * @var int 0x00-0x03
     */
    private int $bgNextTileAttrib = 0x00;

    /**
     * Next background tile pattern low byte
     * Contains low bit plane of 8 pixels
     *
     * @var int 0x00-0xFF
     */
    private int $bgNextTileLsb = 0x00;

    /**
     * Next background tile pattern high byte
     * Contains high bit plane of 8 pixels
     *
     * @var int 0x00-0xFF
     */
    private int $bgNextTileMsb = 0x00;

    /**
     * Background pattern shifter low (16-bit)
     * Top 8 bits = current 8 pixels, bottom 8 bits = next 8 pixels
     * Shifts left by 1 each cycle to output one pixel
     *
     * @var int 0x0000-0xFFFF
     */
    private int $bgShifterPatternLo = 0x0000;

    /**
     * Background pattern shifter high (16-bit)
     * Top 8 bits = current 8 pixels, bottom 8 bits = next 8 pixels
     * Shifts left by 1 each cycle to output one pixel
     *
     * @var int 0x0000-0xFFFF
     */
    private int $bgShifterPatternHi = 0x0000;

    /**
     * Background attribute shifter low (16-bit)
     * Holds low bit of palette selection for 16 pixels
     *
     * @var int 0x0000-0xFFFF
     */
    private int $bgShifterAttribLo = 0x0000;

    /**
     * Background attribute shifter high (16-bit)
     * Holds high bit of palette selection for 16 pixels
     *
     * @var int 0x0000-0xFFFF
     */
    private int $bgShifterAttribHi = 0x0000;

    // ========================================================================
    // Hardware Palette (64 colors)
    // ========================================================================

    /**
     * NES hardware color palette (64 colors)
     *
     * These are the actual RGB colors the NES can display. The palette RAM
     * contains indices (0x00-0x3F) that map to these colors.
     *
     * Each entry is [R, G, B] with values 0-255
     *
     * @var array<array{int, int, int}>
     */
    private array $hardwarePalette = [
        [84, 84, 84],    [0, 30, 116],    [8, 16, 144],    [48, 0, 136],
        [68, 0, 100],    [92, 0, 48],     [84, 4, 0],      [60, 24, 0],
        [32, 42, 0],     [8, 58, 0],      [0, 64, 0],      [0, 60, 0],
        [0, 50, 60],     [0, 0, 0],       [0, 0, 0],       [0, 0, 0],

        [152, 150, 152], [8, 76, 196],    [48, 50, 236],   [92, 30, 228],
        [136, 20, 176],  [160, 20, 100],  [152, 34, 32],   [120, 60, 0],
        [84, 90, 0],     [40, 114, 0],    [8, 124, 0],     [0, 118, 40],
        [0, 102, 120],   [0, 0, 0],       [0, 0, 0],       [0, 0, 0],

        [236, 238, 236], [76, 154, 236],  [120, 124, 236], [176, 98, 236],
        [228, 84, 236],  [236, 88, 180],  [236, 106, 100], [212, 136, 32],
        [160, 170, 0],   [116, 196, 0],   [76, 208, 32],   [56, 204, 108],
        [56, 180, 204],  [60, 60, 60],    [0, 0, 0],       [0, 0, 0],

        [236, 238, 236], [168, 204, 236], [188, 188, 236], [212, 178, 236],
        [236, 174, 236], [236, 174, 212], [236, 180, 176], [228, 196, 144],
        [204, 210, 120], [180, 222, 120], [168, 226, 144], [152, 226, 180],
        [160, 214, 228], [160, 162, 160], [0, 0, 0],       [0, 0, 0],
    ];

    /**
     * Frame buffer (256 x 240 pixels, RGB format)
     *
     * This is the final rendered output. Each pixel is [R, G, B].
     * JavaScript will read this buffer to render to HTML5 Canvas.
     *
     * @var array<array{int, int, int}>
     */
    private array $frameBuffer = [];

    /**
     * Current scanline being rendered
     * Range: -1 (pre-render) to 260 (vblank)
     *
     * Scanline layout:
     * -1: Pre-render scanline (prepare for next frame)
     * 0-239: Visible scanlines (actual rendering)
     * 240: Post-render scanline (idle)
     * 241-260: VBlank period (CPU can update VRAM)
     *
     * @var int
     */
    private int $scanline = 0;

    /**
     * Current cycle within the scanline
     * Range: 0 to 340
     *
     * Each scanline is 341 PPU cycles (including cycle 0)
     *
     * @var int
     */
    private int $cycle = 0;

    /**
     * Frame counter (odd/even frame)
     * Used for frame skip on odd frames
     * Odd frames skip cycle 0 of scanline 0 when rendering is enabled
     *
     * @var int
     */
    private int $frameCount = 0;

    /**
     * Frame complete flag
     * Set to true when a frame finishes rendering
     * Should be polled and cleared by external code
     *
     * @var bool
     */
    private bool $frameComplete = false;

    // ========================================================================
    // Initialization
    // ========================================================================

    public function __construct()
    {
        $this->control = new PPUControl();
        $this->mask = new PPUMask();
        $this->status = new PPUStatus();
        $this->vramAddress = new LoopyRegister();
        $this->tempVramAddress = new LoopyRegister();
        $this->reset();
    }

    /**
     * Set the cartridge mapper for CHR-ROM/CHR-RAM access
     *
     * @param MapperInterface $mapper
     */
    public function setMapper(MapperInterface $mapper): void
    {
        $this->mapper = $mapper;
    }

    /**
     * Set nametable mirroring mode
     *
     * @param int $mode 0 = horizontal, 1 = vertical
     */
    public function setMirroring(int $mode): void
    {
        $this->mirroringMode = $mode;
    }

    /**
     * Apply nametable mirroring to convert 4KB address space to 2KB physical RAM
     *
     * Nametable layout (logical):
     * $2000-$23FF: Nametable 0
     * $2400-$27FF: Nametable 1
     * $2800-$2BFF: Nametable 2
     * $2C00-$2FFF: Nametable 3
     *
     * Horizontal mirroring (vertical arrangement):
     * [ A ] [ a ]
     * [ B ] [ b ]
     * NT0 and NT1 map to first 1KB (A), NT2 and NT3 map to second 1KB (B)
     *
     * Vertical mirroring (horizontal arrangement):
     * [ A ] [ B ]
     * [ a ] [ b ]
     * NT0 and NT2 map to first 1KB (A), NT1 and NT3 map to second 1KB (B)
     *
     * @param int $address Address in nametable space (0x0000-0x0FFF)
     * @return int Physical address in 2KB VRAM (0x0000-0x07FF)
     */
    private function mirrorNametableAddress(int $address): int
    {
        $address &= 0x0FFF; // Ensure 12-bit address (0-4095)

        // Determine which nametable (0-3)
        $nametable = ($address >> 10) & 0x03; // Bits 10-11
        $offset = $address & 0x03FF;           // Bits 0-9 (offset within nametable)

        if ($this->mirroringMode === 0) {
            // Horizontal mirroring: NT0/NT1 -> 0x0000, NT2/NT3 -> 0x0400
            $physicalNametable = ($nametable & 0x02) >> 1; // 0 or 1
        } else {
            // Vertical mirroring: NT0/NT2 -> 0x0000, NT1/NT3 -> 0x0400
            $physicalNametable = $nametable & 0x01; // 0 or 1
        }

        return ($physicalNametable << 10) | $offset;
    }

    /**
     * Reset the PPU to initial state
     */
    public function reset(): void
    {
        // Initialize memory banks
        $this->nametable = array_fill(0, 2048, 0x00);
        $this->paletteRam = array_fill(0, 32, 0x00);
        $this->patternTable = array_fill(0, 8192, 0x00);
        $this->oam = array_fill(0, 256, 0x00);

        // Initialize frame buffer (256x240 black pixels)
        $this->frameBuffer = [];
        for ($y = 0; $y < 240; $y++) {
            for ($x = 0; $x < 256; $x++) {
                $this->frameBuffer[$y * 256 + $x] = [0, 0, 0];
            }
        }

        // Reset registers
        $this->control->set(0x00);
        $this->mask->set(0x00);

        // Reset cached flags
        $this->cachedRenderingEnabled = false;
        $this->cachedRenderBackground = false;

        // Note: VBlank flag is random at power-on in real hardware
        // For now, always start with VBlank clear for simplicity
        $this->status->set(0x00);

        // Reset internal registers
        $this->vramAddress->set(0x0000);
        $this->tempVramAddress->set(0x0000);
        $this->fineX = 0x00;
        $this->addressLatch = false;
        $this->dataBuffer = 0x00;

        // Reset timing
        // Start at pre-render scanline (beginning of a frame)
        $this->scanline = -1;
        $this->cycle = 0;
        $this->frameCount = 0;
        $this->frameComplete = false;
        $this->oamAddress = 0x00;
        $this->nmi = false;

        // Reset warm-up flag
        // PPU ignores writes to certain registers until end of first VBlank
        $this->inWarmup = true;

        // Reset background rendering state
        $this->bgNextTileId = 0x00;
        $this->bgNextTileAttrib = 0x00;
        $this->bgNextTileLsb = 0x00;
        $this->bgNextTileMsb = 0x00;
        $this->bgShifterPatternLo = 0x0000;
        $this->bgShifterPatternHi = 0x0000;
        $this->bgShifterAttribLo = 0x0000;
        $this->bgShifterAttribHi = 0x0000;
    }

    // ========================================================================
    // PPU Bus Interface (Internal)
    // ========================================================================

    /**
     * Read from PPU address space ($0000-$3FFF)
     *
     * This is used internally by the PPU to access VRAM during rendering.
     * Not to be confused with CPU reads to PPU registers.
     *
     * @param int $address 0x0000-0x3FFF (14-bit address space)
     * @return int Byte value (0x00-0xFF)
     */
    public function ppuRead(int $address): int
    {
        $address &= 0x3FFF; // Mirror address space

        // Pattern Tables ($0000-$1FFF)
        if ($address < 0x2000) {
            // Read from mapper (CHR-ROM/CHR-RAM) if available
            if ($this->mapper !== null) {
                return $this->mapper->ppuRead($address);
            }
            // Fallback to internal RAM for testing
            return $this->patternTable[$address];
        }

        // Nametables ($2000-$2FFF)
        if ($address < 0x3F00) {
            $mirroredAddress = $this->mirrorNametableAddress($address);
            return $this->nametable[$mirroredAddress];
        }

        // Palette RAM ($3F00-$3FFF)
        $address &= 0x001F; // Palette RAM is 32 bytes, mirrored

        // Mirror transparent color ($3F10, $3F14, $3F18, $3F1C -> $3F00, $3F04, $3F08, $3F0C)
        if (($address & 0x0003) === 0x0000 && $address >= 0x0010) {
            $address &= 0x000F;
        }

        return $this->paletteRam[$address];
    }

    /**
     * Write to PPU address space ($0000-$3FFF)
     *
     * @param int $address 0x0000-0x3FFF
     * @param int $data Byte value (0x00-0xFF)
     */
    public function ppuWrite(int $address, int $data): void
    {
        $address &= 0x3FFF;
        $data &= 0xFF;

        // Pattern Tables ($0000-$1FFF)
        if ($address < 0x2000) {
            // Write to mapper (CHR-RAM) if available
            if ($this->mapper !== null) {
                $this->mapper->ppuWrite($address, $data);
                return;
            }
            // Fallback to internal RAM for testing
            $this->patternTable[$address] = $data;
            return;
        }

        // Nametables ($2000-$2FFF)
        if ($address < 0x3F00) {
            $mirroredAddress = $this->mirrorNametableAddress($address);
            $this->nametable[$mirroredAddress] = $data;
            return;
        }

        // Palette RAM ($3F00-$3FFF)
        $address &= 0x001F;

        // Mirror transparent color
        if (($address & 0x0003) === 0x0000 && $address >= 0x0010) {
            $address &= 0x000F;
        }

        $this->paletteRam[$address] = $data;
    }

    // ========================================================================
    // CPU Interface (Registers $2000-$2007)
    // ========================================================================

    /**
     * CPU reads from PPU registers ($2000-$2007)
     *
     * @param int $address Register address (0-7), will be masked
     * @return int Byte value (0x00-0xFF)
     */
    public function cpuRead(int $address): int
    {
        $address &= 0x07; // Only 3 bits matter, registers repeat every 8 bytes

        $data = 0x00;

        switch ($address) {
            case 0x00: // $2000 PPUCTRL - not readable
                break;

            case 0x01: // $2001 PPUMASK - not readable
                break;

            case 0x02: // $2002 PPUSTATUS - read only
                // Return top 3 bits of status, bottom 5 bits are open bus (last value on bus)
                $data = ($this->status->get() & 0xE0) | ($this->dataBuffer & 0x1F);

                // Reading status clears vblank flag
                $this->status->clearVerticalBlank();

                // Reading status resets address latch
                $this->addressLatch = false;
                break;

            case 0x03: // $2003 OAMADDR - not readable
                break;

            case 0x04: // $2004 OAMDATA - read/write
                $data = $this->oam[$this->oamAddress];
                break;

            case 0x05: // $2005 PPUSCROLL - not readable
                break;

            case 0x06: // $2006 PPUADDR - not readable
                break;

            case 0x07: // $2007 PPUDATA - read/write
                // PPUDATA reads are buffered (delayed by one read)
                $data = $this->dataBuffer;
                $this->dataBuffer = $this->ppuRead($this->vramAddress->get());

                // Palette RAM reads are NOT buffered (immediate)
                if ($this->vramAddress->get() >= 0x3F00) {
                    $data = $this->dataBuffer;
                }

                // Increment VRAM address
                $this->vramAddress->set($this->vramAddress->get() + $this->control->incrementMode());
                break;
        }

        return $data;
    }

    /**
     * CPU writes to PPU registers ($2000-$2007)
     *
     * @param int $address Register address (0-7), will be masked
     * @param int $data Byte value to write
     */
    public function cpuWrite(int $address, int $data): void
    {
        $address &= 0x07;
        $data &= 0xFF;

        // Store data for open bus behavior
        $this->dataBuffer = $data;

        // During warm-up period, ignore writes to PPUCTRL, PPUMASK, PPUSCROLL, PPUADDR
        // This mimics real hardware behavior where these registers are held in reset
        // Warm-up ends at the end of the first frame (end of first VBlank)

        switch ($address) {
            case 0x00: // $2000 PPUCTRL
                // TODO: Warm-up period temporarily disabled for debugging
                // if ($this->inWarmup) break; // Ignore during warm-up

                $this->control->set($data);

                // Bits 0-1 also go into temp VRAM address (nametable select)
                $this->tempVramAddress->setNametableX($data & 0x01);
                $this->tempVramAddress->setNametableY(($data >> 1) & 0x01);
                break;

            case 0x01: // $2001 PPUMASK
                // TODO: Warm-up period temporarily disabled for debugging
                // if ($this->inWarmup) break; // Ignore during warm-up

                $this->mask->set($data);

                // Update cached flags for performance
                $this->cachedRenderingEnabled = $this->mask->isRenderingEnabled();
                $this->cachedRenderBackground = $this->mask->renderBackground();
                break;

            case 0x02: // $2002 PPUSTATUS - read only, write does nothing
                break;

            case 0x03: // $2003 OAMADDR
                $this->oamAddress = $data;
                break;

            case 0x04: // $2004 OAMDATA
                $this->oam[$this->oamAddress] = $data;
                $this->oamAddress = ($this->oamAddress + 1) & 0xFF; // Auto-increment
                break;

            case 0x05: // $2005 PPUSCROLL - write twice (X, then Y)
                // TODO: Warm-up period temporarily disabled for debugging
                // if ($this->inWarmup) break; // Ignore during warm-up

                if (!$this->addressLatch) {
                    // First write: X scroll
                    $this->fineX = $data & 0x07;
                    $this->tempVramAddress->setCoarseX($data >> 3);
                    $this->addressLatch = true;
                } else {
                    // Second write: Y scroll
                    $this->tempVramAddress->setFineY($data & 0x07);
                    $this->tempVramAddress->setCoarseY($data >> 3);
                    $this->addressLatch = false;
                }
                break;

            case 0x06: // $2006 PPUADDR - write twice (high byte, then low byte)
                // TODO: Warm-up period temporarily disabled for debugging
                // if ($this->inWarmup) break; // Ignore during warm-up

                if (!$this->addressLatch) {
                    // First write: high byte
                    $temp = $this->tempVramAddress->get();
                    $temp = ($temp & 0x00FF) | (($data & 0x3F) << 8);
                    $this->tempVramAddress->set($temp);
                    $this->addressLatch = true;
                } else {
                    // Second write: low byte
                    $temp = $this->tempVramAddress->get();
                    $temp = ($temp & 0xFF00) | $data;
                    $this->tempVramAddress->set($temp);

                    // Copy temp to actual VRAM address
                    $this->vramAddress->set($this->tempVramAddress->get());
                    $this->addressLatch = false;
                }
                break;

            case 0x07: // $2007 PPUDATA
                $this->ppuWrite($this->vramAddress->get(), $data);

                // Increment VRAM address
                $this->vramAddress->set($this->vramAddress->get() + $this->control->incrementMode());
                break;
        }
    }

    // ========================================================================
    // Public Interface
    // ========================================================================

    /**
     * Get the current frame buffer
     *
     * Returns a flat array of RGB values suitable for rendering to Canvas
     * Format: [R, G, B, R, G, B, ...] for 256x240 pixels = 184,320 values
     *
     * @return array<int>
     */
    /**
     * Get frame buffer as array of RGB pixels
     * Returns 256x240 array where each element is [R, G, B]
     *
     * @return array<array{int, int, int}>
     */
    public function getFrameBuffer(): array
    {
        return $this->frameBuffer;
    }

    /**
     * Get frame buffer as flat array of RGB values
     * Returns array of [R, G, B, R, G, B, ...] for direct use in canvas
     *
     * @return array<int>
     */
    public function getFrameBufferFlat(): array
    {
        $flat = [];
        foreach ($this->frameBuffer as $pixel) {
            $flat[] = $pixel[0]; // R
            $flat[] = $pixel[1]; // G
            $flat[] = $pixel[2]; // B
        }
        return $flat;
    }

    /**
     * Get color from palette RAM
     *
     * @param int $paletteIndex Palette number (0-7)
     * @param int $pixelValue Pixel value (0-3, 2-bit color)
     * @return array{int, int, int} RGB color [R, G, B]
     */
    public function getColorFromPalette(int $paletteIndex, int $pixelValue): array
    {
        // Calculate palette RAM address
        $address = ($paletteIndex << 2) | ($pixelValue & 0x03);

        // Read palette index from palette RAM
        $colorIndex = $this->ppuRead(0x3F00 + $address) & 0x3F;

        // Return RGB color from hardware palette
        return $this->hardwarePalette[$colorIndex];
    }

    /**
     * Write to OAM (Object Attribute Memory)
     * Used for DMA transfers from CPU
     *
     * @param int $address 0x00-0xFF
     * @param int $data Byte value
     */
    public function writeOAM(int $address, int $data): void
    {
        $this->oam[$address & 0xFF] = $data & 0xFF;
    }

    /**
     * Read from OAM
     *
     * @param int $address 0x00-0xFF
     * @return int Byte value
     */
    public function readOAM(int $address): int
    {
        return $this->oam[$address & 0xFF];
    }

    /**
     * Set OAM address register
     *
     * @param int $address 0x00-0xFF
     */
    public function setOAMAddress(int $address): void
    {
        $this->oamAddress = $address & 0xFF;
    }

    /**
     * Get OAM address register
     *
     * @return int
     */
    public function getOAMAddress(): int
    {
        return $this->oamAddress;
    }

    // ========================================================================
    // Background Rendering Helpers
    // ========================================================================

    /**
     * Load background shifters with next tile data
     * Called every 8 cycles to prime shifters with next 8 pixels
     */
    private function loadBackgroundShifters(): void
    {
        // Load pattern shifters
        // Load new tile data into HIGH bits (bits 15-8) which are currently being rendered
        // The LOW bits (bits 7-0) contain data that will shift into HIGH bits over next 8 cycles
        $this->bgShifterPatternLo = ($this->bgShifterPatternLo & 0x00FF) | ($this->bgNextTileLsb << 8);
        $this->bgShifterPatternHi = ($this->bgShifterPatternHi & 0x00FF) | ($this->bgNextTileMsb << 8);

        // Load attribute shifters
        // Attribute doesn't change per pixel, so "inflate" 2-bit palette to fill high 8 bits
        $this->bgShifterAttribLo = ($this->bgShifterAttribLo & 0x00FF) | (($this->bgNextTileAttrib & 0x01) ? 0xFF00 : 0x0000);
        $this->bgShifterAttribHi = ($this->bgShifterAttribHi & 0x00FF) | (($this->bgNextTileAttrib & 0x02) ? 0xFF00 : 0x0000);
    }

    /**
     * Update (shift) background shifters by 1 bit
     * Called every cycle during rendering to advance pixel output
     */
    private function updateShifters(): void
    {
        if ($this->cachedRenderBackground) {
            // Shift pattern shifters left by 1
            $this->bgShifterPatternLo = ($this->bgShifterPatternLo << 1) & 0xFFFF;
            $this->bgShifterPatternHi = ($this->bgShifterPatternHi << 1) & 0xFFFF;

            // Shift attribute shifters left by 1
            $this->bgShifterAttribLo = ($this->bgShifterAttribLo << 1) & 0xFFFF;
            $this->bgShifterAttribHi = ($this->bgShifterAttribHi << 1) & 0xFFFF;
        }
    }

    // ========================================================================
    // Timing System
    // ========================================================================

    /**
     * Advance PPU by one cycle
     *
     * This is the main rendering loop. It advances the PPU by one cycle,
     * handling all timing-critical operations.
     *
     * Frame structure (NTSC):
     * - 262 scanlines total
     * - 341 cycles per scanline
     * - Total: 89,342 cycles per frame (89,341 on odd frames due to skip)
     *
     * Scanline breakdown:
     * -1 (261): Pre-render - prepare for next frame
     * 0-239: Visible scanlines - actual rendering
     * 240: Post-render - idle
     * 241-260: VBlank - CPU can safely update VRAM
     */
    public function clock(): void
    {
        // ====================================================================
        // Advance Timing FIRST
        // ====================================================================

        $this->cycle++;

        // End of scanline
        if ($this->cycle >= 341) {
            $this->cycle = 0;
            $this->scanline++;

            // Odd frame skip: On odd frames, when rendering is enabled,
            // cycle 0 of scanline 0 is skipped (advance directly to cycle 1)
            if ($this->scanline === 0 && ($this->frameCount & 1) === 1 && $this->cachedRenderingEnabled) {
                $this->cycle = 1;
            }

            // End of frame
            if ($this->scanline >= 261) {
                $this->scanline = -1;
                $this->frameComplete = true;
                $this->frameCount++;

                // Clear warm-up flag after first frame completes
                // After this, PPUCTRL/PPUMASK/PPUSCROLL/PPUADDR writes are accepted
                if ($this->inWarmup) {
                    $this->inWarmup = false;
                }
            }
        }

        // ====================================================================
        // Now execute logic for the CURRENT cycle
        // ====================================================================

        // ====================================================================
        // Pre-render and Visible Scanlines (-1, 0-239)
        // ====================================================================
        if ($this->scanline >= -1 && $this->scanline < 240) {

            // Clear flags at start of pre-render scanline
            if ($this->scanline === -1 && $this->cycle === 1) {
                $this->status->setVerticalBlank(false);
                $this->status->setSpriteZeroHit(false);
                $this->status->setSpriteOverflow(false);
                $this->frameComplete = false;
            }

            // Background rendering cycles
            if (($this->cycle >= 2 && $this->cycle < 258) || ($this->cycle >= 321 && $this->cycle < 338)) {

                // Update shifters every cycle
                $this->updateShifters();

                // 8-cycle fetching pattern
                switch (($this->cycle - 1) % 8) {
                    case 0:
                        // Load shifters with data from previous fetch
                        $this->loadBackgroundShifters();

                        // Fetch next tile ID from nametable
                        // Use bottom 12 bits of vram address as nametable offset
                        $this->bgNextTileId = $this->ppuRead(0x2000 | ($this->vramAddress->get() & 0x0FFF));
                        break;

                    case 2:
                        // Fetch attribute byte
                        // Address calculation: 0x23C0 | (nametable_y << 11) | (nametable_x << 10) | ((coarse_y >> 2) << 3) | (coarse_x >> 2)
                        $address = 0x23C0
                            | ($this->vramAddress->nametableY() << 11)
                            | ($this->vramAddress->nametableX() << 10)
                            | (($this->vramAddress->coarseY() >> 2) << 3)
                            | ($this->vramAddress->coarseX() >> 2);

                        $this->bgNextTileAttrib = $this->ppuRead($address);

                        // Extract the 2 bits for this 2x2 tile quadrant
                        if ($this->vramAddress->coarseY() & 0x02) {
                            $this->bgNextTileAttrib >>= 4;
                        }
                        if ($this->vramAddress->coarseX() & 0x02) {
                            $this->bgNextTileAttrib >>= 2;
                        }
                        $this->bgNextTileAttrib &= 0x03;
                        break;

                    case 4:
                        // Fetch tile pattern low byte
                        $address = $this->control->backgroundPatternTable()
                            | ($this->bgNextTileId << 4)
                            | $this->vramAddress->fineY();
                        $this->bgNextTileLsb = $this->ppuRead($address);
                        break;

                    case 6:
                        // Fetch tile pattern high byte (same as low + 8)
                        $address = $this->control->backgroundPatternTable()
                            | ($this->bgNextTileId << 4)
                            | $this->vramAddress->fineY()
                            | 0x08;
                        $this->bgNextTileMsb = $this->ppuRead($address);
                        break;

                    case 7:
                        // Increment horizontal scroll
                        if ($this->cachedRenderingEnabled) {
                            $this->vramAddress->incrementX();
                        }
                        break;
                }
            }

            // End of visible scanline: increment vertical scroll
            if ($this->cycle === 256) {
                if ($this->cachedRenderingEnabled) {
                    $this->vramAddress->incrementY();
                }
            }

            // Reset horizontal position
            if ($this->cycle === 257) {
                $this->loadBackgroundShifters();
                if ($this->cachedRenderingEnabled) {
                    $this->vramAddress->transferX($this->tempVramAddress);
                }
            }

            // Superfluous nametable fetches at end of scanline
            if ($this->cycle === 338 || $this->cycle === 340) {
                $this->bgNextTileId = $this->ppuRead(0x2000 | ($this->vramAddress->get() & 0x0FFF));
            }

            // Pre-render scanline: restore vertical position
            if ($this->scanline === -1 && $this->cycle >= 280 && $this->cycle < 305) {
                if ($this->cachedRenderingEnabled) {
                    $this->vramAddress->transferY($this->tempVramAddress);
                }
            }
        }

        // ====================================================================
        // Post-render Scanline (240)
        // ====================================================================
        // Idle - PPU does nothing

        // ====================================================================
        // VBlank Scanlines (241-260)
        // ====================================================================
        if ($this->scanline === 241 && $this->cycle === 1) {
            // Set VBlank flag
            $this->status->setVerticalBlank(true);

            // Trigger NMI if enabled
            if ($this->control->enableNMI()) {
                $this->nmi = true;
            }
        }

        // ====================================================================
        // Pixel Composition (happens every cycle during visible scanlines)
        // ====================================================================
        if ($this->scanline >= 0 && $this->scanline < 240 && $this->cycle >= 1 && $this->cycle <= 256) {
            $x = $this->cycle - 1;
            $y = $this->scanline;

            // Only render if rendering is enabled
            if ($this->cachedRenderingEnabled) {
                // Background pixel
                $bgPixel = 0x00;
                $bgPalette = 0x00;

                if ($this->cachedRenderBackground) {
                    // Select bit based on fine X scroll
                    $bitMux = 0x8000 >> $this->fineX;

                    // Extract pixel value (2 bits)
                    $p0 = ($this->bgShifterPatternLo & $bitMux) ? 1 : 0;
                    $p1 = ($this->bgShifterPatternHi & $bitMux) ? 1 : 0;
                    $bgPixel = ($p1 << 1) | $p0;

                    // Extract palette (2 bits)
                    $pal0 = ($this->bgShifterAttribLo & $bitMux) ? 1 : 0;
                    $pal1 = ($this->bgShifterAttribHi & $bitMux) ? 1 : 0;
                    $bgPalette = ($pal1 << 1) | $pal0;
                }

                // Get color from palette
                $color = $this->getColorFromPalette($bgPalette, $bgPixel);

                // Write to frame buffer
                $this->frameBuffer[$y * 256 + $x] = $color;
            }
            // If rendering disabled, frame buffer keeps its initialized value (black)
        }
    }

    /**
     * Check if NMI should be triggered
     * CPU should poll this and call its nmi() method when true
     *
     * @return bool
     */
    public function hasNMI(): bool
    {
        return $this->nmi;
    }

    /**
     * Clear NMI flag
     * Should be called by CPU after handling NMI
     */
    public function clearNMI(): void
    {
        $this->nmi = false;
    }

    /**
     * Check if frame is complete
     *
     * @return bool
     */
    public function isFrameComplete(): bool
    {
        return $this->frameComplete;
    }

    /**
     * Clear frame complete flag
     */
    public function clearFrameComplete(): void
    {
        $this->frameComplete = false;
    }

    /**
     * Get current scanline
     *
     * @return int -1 to 260
     */
    public function getScanline(): int
    {
        return $this->scanline;
    }

    /**
     * Get current cycle
     *
     * @return int 0 to 340
     */
    public function getCycle(): int
    {
        return $this->cycle;
    }

    /**
     * Get frame count
     *
     * @return int
     */
    public function getFrameCount(): int
    {
        return $this->frameCount;
    }
}
