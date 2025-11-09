# NES Mapper Implementations

This document describes the mappers implemented in this NES emulator.

## Overview

Mappers are hardware chips on NES cartridges that control memory banking and additional features. Different games use different mappers depending on their memory requirements and features.

## Implemented Mappers

### Mapper 0 (NROM)
**Coverage:** ~11% of all NES games
**File:** `src/Cartridge/Mapper0.php`

The simplest mapper with no bank switching.

**Features:**
- 16KB or 32KB PRG-ROM (fixed, no switching)
- 8KB CHR-ROM or CHR-RAM (fixed, no switching)
- Fixed mirroring (horizontal or vertical)

**Memory Map:**
- CPU `$8000-$BFFF`: First 16KB of PRG-ROM
- CPU `$C000-$FFFF`: Second 16KB of PRG-ROM (or mirror of first 16KB)
- PPU `$0000-$1FFF`: 8KB CHR-ROM/RAM

**Example Games:** Donkey Kong, Super Mario Bros, Excitebike, Ice Climber

---

### Mapper 1 (MMC1)
**Coverage:** ~28% of all NES games
**File:** `src/Cartridge/Mapper1.php`

Most common NES mapper with PRG and CHR bank switching.

**Features:**
- PRG-ROM bank switching (16KB or 32KB modes)
- CHR-ROM/RAM bank switching (4KB or 8KB modes)
- Configurable mirroring (horizontal, vertical, one-screen)
- Serial write interface (5 writes to configure registers)

**Memory Map:**
- CPU `$8000-$BFFF`: Switchable 16KB PRG-ROM bank
- CPU `$C000-$FFFF`: Switchable or fixed 16KB PRG-ROM bank
- PPU `$0000-$0FFF`: Switchable 4KB CHR bank
- PPU `$1000-$1FFF`: Switchable 4KB CHR bank

**Registers:**
- `$8000-$9FFF`: Control register
- `$A000-$BFFF`: CHR bank 0
- `$C000-$DFFF`: CHR bank 1
- `$E000-$FFFF`: PRG bank

**Example Games:** The Legend of Zelda, Metroid, Tetris, Mega Man 2, Kid Icarus

---

### Mapper 2 (UxROM)
**Coverage:** ~11% of all NES games
**File:** `src/Cartridge/Mapper2.php`

PRG-ROM bank switching with CHR-RAM.

**Features:**
- Switchable 16KB PRG-ROM bank at `$8000-$BFFF`
- Fixed 16KB PRG-ROM bank at `$C000-$FFFF` (last bank)
- 8KB CHR-RAM (no CHR-ROM, writable)
- Fixed mirroring

**Memory Map:**
- CPU `$8000-$BFFF`: Switchable 16KB PRG-ROM bank
- CPU `$C000-$FFFF`: Fixed 16KB PRG-ROM bank (last)
- PPU `$0000-$1FFF`: 8KB CHR-RAM (writable)

**Bank Select:**
- Write to `$8000-$FFFF`: Select PRG-ROM bank (lower 4 bits)

**Example Games:** Mega Man, Castlevania, Contra, Duck Tales, Ghosts 'n Goblins

---

### Mapper 3 (CNROM)
**Coverage:** ~6% of all NES games
**File:** `src/Cartridge/Mapper3.php`

Simple CHR-ROM bank switching.

**Features:**
- Fixed 16KB or 32KB PRG-ROM (no switching)
- CHR-ROM bank switching (8KB banks)
- Fixed mirroring

**Memory Map:**
- CPU `$8000-$BFFF`: First 16KB of PRG-ROM
- CPU `$C000-$FFFF`: Second 16KB of PRG-ROM (or mirror)
- PPU `$0000-$1FFF`: Switchable 8KB CHR-ROM bank

**Bank Select:**
- Write to `$8000-$FFFF`: Select CHR-ROM bank (lower 2 bits)

**Example Games:** Arkanoid, Joust, Paperboy, Gradius

---

### Mapper 4 (MMC3)
**Coverage:** ~22% of all NES games
**File:** `src/Cartridge/Mapper4.php`

Most advanced common mapper with sophisticated features.

**Features:**
- 8KB PRG-ROM bank switching with configurable modes
- 1KB/2KB CHR-ROM bank switching with configurable modes
- Scanline counter for IRQ timing (for advanced graphics effects)
- PRG-RAM with write protection
- Configurable mirroring

**Memory Map:**
- CPU `$6000-$7FFF`: 8KB PRG-RAM
- CPU `$8000-$9FFF`: 8KB switchable PRG-ROM (or fixed)
- CPU `$A000-$BFFF`: 8KB switchable PRG-ROM
- CPU `$C000-$DFFF`: 8KB switchable PRG-ROM (or fixed)
- CPU `$E000-$FFFF`: 8KB fixed PRG-ROM (last bank)
- PPU `$0000-$1FFF`: Six switchable CHR banks (mixed 1KB/2KB)

**Registers:**
- `$8000-$8001`: Bank select and bank data
- `$A000-$A001`: Mirroring and PRG-RAM protect
- `$C000-$C001`: IRQ latch and reload
- `$E000-$E001`: IRQ disable and enable

**Example Games:** Super Mario Bros 2, Super Mario Bros 3, Mega Man 3-6, Kirby's Adventure, Final Fantasy

---

## Coverage Statistics

With these 5 mappers implemented, the emulator can run approximately **60-70% of all NES games**:

| Mapper | Name  | Games Coverage | Cumulative |
|--------|-------|----------------|------------|
| 0      | NROM  | ~11%           | ~11%       |
| 1      | MMC1  | ~28%           | ~39%       |
| 2      | UxROM | ~11%           | ~50%       |
| 3      | CNROM | ~6%            | ~56%       |
| 4      | MMC3  | ~22%           | ~78%       |

## Implementation Notes

### CHR-ROM vs CHR-RAM
- **CHR-ROM**: Read-only graphics data on cartridge (most common)
- **CHR-RAM**: Writable RAM for graphics (used by Mapper 2 and some others)
- Mappers automatically detect CHR-ROM size and use CHR-RAM if size is 0

### Mirroring Modes
- **Horizontal (0)**: Top/bottom nametables share memory (vertical scrolling)
- **Vertical (1)**: Left/right nametables share memory (horizontal scrolling)
- Some mappers (MMC1, MMC3) support switchable mirroring

### Bank Sizes
Different mappers use different bank sizes:
- PRG-ROM: 8KB, 16KB, or 32KB banks
- CHR-ROM: 1KB, 2KB, 4KB, or 8KB banks

### IRQ Support (MMC3)
Mapper 4 includes a scanline counter that can trigger IRQs for:
- Status bar effects (e.g., Super Mario Bros 3)
- Split-screen scrolling
- Advanced graphics timing

## Testing

Run `test_mappers.php` to verify all mapper implementations:

```bash
php test_mappers.php
```

This will load and run test ROMs for each mapper to ensure they work correctly.

## Future Mappers

Additional mappers that could be implemented for wider game coverage:

- **Mapper 7 (AxROM)**: Used by Battletoads, Wizards & Warriors
- **Mapper 9 (MMC2)**: Used by Punch-Out!!
- **Mapper 10 (MMC4)**: Used by Fire Emblem
- **Mapper 11**: Used by Color Dreams games
- **Mapper 66 (GxROM)**: Used by some early games

Adding these would bring coverage to ~85-90% of all NES games.
