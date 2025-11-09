# Project Structure

## Directory Layout

```
NES/
├── docs/                          # All documentation
│   ├── ARCHITECTURE_INSIGHTS.md   # Analysis of reference emulators
│   ├── EMULATOR_STATUS.md         # Current implementation status
│   ├── MAPPER_IMPLEMENTATIONS.md  # Mapper documentation
│   ├── PROJECT_STRUCTURE.md       # This file
│   ├── TIMING_FIX_RESULTS.md      # Cycle-level timing notes
│   ├── TROUBLESHOOTING.md         # Common issues
│   ├── VIEWER_EXPLANATION.md      # Viewer behavior notes
│   ├── VIEWER_GUIDE.md            # How to use the viewer
│   ├── VIEWER_STATUS.md           # Viewer limitations
│   └── VISUAL_PROOF.md            # Rendering verification
│
├── roms/                          # NES ROM files (.nes)
│   ├── donkeykong.nes
│   ├── supermario.nes
│   ├── tetris.nes
│   └── ...
│
├── src/                           # PHP source code
│   ├── APU/                       # Audio Processing Unit
│   ├── Bus/                       # System bus
│   │   └── NESBus.php
│   ├── Cartridge/                 # ROM loading and mappers
│   │   ├── Cartridge.php
│   │   ├── MapperInterface.php
│   │   ├── Mapper0.php            # NROM
│   │   ├── Mapper1.php            # MMC1
│   │   ├── Mapper2.php            # UxROM
│   │   ├── Mapper3.php            # CNROM
│   │   └── Mapper4.php            # MMC3
│   ├── Input/                     # Controller input
│   ├── PPU/                       # Picture Processing Unit
│   │   ├── PPU.php
│   │   ├── PPUControl.php
│   │   ├── PPUMask.php
│   │   ├── PPUStatus.php
│   │   └── LoopyRegister.php
│   └── NES.php                    # Main emulator class
│
├── tests/                         # Test files
│   ├── manual/                    # Manual test scripts
│   │   ├── README.md
│   │   └── test_mappers.php       # Test mapper implementations
│   └── (PHPUnit tests)
│
├── vendor/                        # Composer dependencies
│   └── andrewthecoder/6502-emulator/
│
├── viewer/                        # Web-based viewer
│   ├── index.html
│   ├── emulator_backend.php
│   └── style.css
│
├── CLAUDE.md                      # Instructions for Claude Code
├── composer.json                  # Composer configuration
├── composer.lock
├── README.md                      # Main project README
└── nesrun.php                     # CLI runner script
```

## Key Files

### Main Entry Points
- `nesrun.php` - Command-line runner for the emulator
- `viewer/index.html` - Web-based viewer interface
- `src/NES.php` - Main emulator class

### Running Tests
```bash
# Run mapper tests
cd tests/manual
php test_mappers.php

# Run PHPUnit tests
./vendor/bin/phpunit
```

### Documentation
All documentation is in the `docs/` directory. Start with:
- `EMULATOR_STATUS.md` - Current implementation status
- `MAPPER_IMPLEMENTATIONS.md` - Supported mappers
- `VIEWER_GUIDE.md` - How to use the web viewer

## Recent Changes

### Cleanup (Latest)
- Removed 16+ diagnostic/debug PHP files from root
- Organized documentation into `docs/` directory
- Moved tests to `tests/manual/` directory
- Cleaned up project structure for better organization

### Mapper Implementations
- Added Mapper 2 (UxROM)
- Added Mapper 3 (CNROM)
- Added Mapper 4 (MMC3)
- Now supports 60-70% of all NES games

### PPU Improvements
- Implemented nametable mirroring (horizontal/vertical)
- Fixed background shifter loading
- Connected PPU to mapper for CHR-ROM access
