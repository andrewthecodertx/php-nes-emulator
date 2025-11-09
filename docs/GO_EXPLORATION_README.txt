GO NES EMULATOR EXPLORATION - DOCUMENTATION
===========================================

This directory now contains comprehensive analysis of the Go NES emulator
codebase from /home/andrew/Projects/nes-emulator.

Three new documents have been created:

1. GO_EMULATOR_EXPLORATION_SUMMARY.md
   - Executive summary of findings
   - Key architectural insights
   - Why Go version shows frames vs PHP doesn't
   - 14-point implementation checklist
   - Path forward and recommendations

2. go_nes_analysis.md
   - Complete architectural overview (15 KB)
   - Project structure walkthrough
   - Each major component explained:
     * NES coordinator
     * System bus and memory mapping
     * PPU with cycle-accurate rendering
     * Rendering pipeline details
     * Shifter systems
     * Sprite evaluation and rendering
     * Hardware palette system
     * Controller input
     * Cartridge/mapper support
   - Critical implementation details
   - Debug tools available

3. critical_implementation_patterns.md
   - Practical implementation guide (19 KB)
   - 10 critical pattern areas with Go examples
   - PHP equivalent code patterns for each
   - Side-by-side comparisons
   - Critical differences highlighted
   - Most likely failure points in PHP version

QUICK START
===========

1. Read GO_EMULATOR_EXPLORATION_SUMMARY.md first
2. Use critical_implementation_patterns.md as a checklist
3. Refer to go_nes_analysis.md for architectural context
4. Cross-reference with /home/andrew/Projects/nes-emulator source code

KEY FINDINGS
============

Why Go Version Works:
- Frame buffer updated EVERY cycle (not deferred)
- PPU clock 3:1 ratio with CPU properly implemented
- Scanline counter supports -1 (pre-render scanline)
- Background shifters (16-bit) with load/shift operations
- Sprite evaluation + fetching (two phases)
- Palette indirection (RAM → indices, conversion at display)
- 120-frame pre-initialization before rendering stable

Most Likely PHP Issues:
- Frame buffer not updated every cycle
- PPU clock not properly 3:1 ratio
- Scanline counter can't represent -1
- Pre-render scanline not handled
- Shifters not implemented correctly
- Palette stored as RGB (not indices)
- Rendering always enabled (no flag checks)
- No initialization period pre-run

WHAT TO DO NEXT
===============

1. Immediate Actions:
   - Read summaries in order listed above
   - Identify which of 14 checklist items are missing
   - Note any architectural mismatches with PHP code

2. Implementation Priority:
   - Fix frame buffer (must be palette indices, updated every cycle)
   - Fix clock hierarchy (3:1 PPU:CPU ratio)
   - Fix scanline counter (support -1)
   - Implement shifters if missing
   - Add 120-frame pre-initialization

3. Testing:
   - Create unit tests for each PPU component
   - Run integration tests with actual ROMs
   - Verify frame buffer contents frame by frame

DIRECTORY STRUCTURE
===================

Go Emulator (reference implementation):
/home/andrew/Projects/nes-emulator/

Key Go files to examine:
- pkg/nes/nes.go (123 lines) - Main coordinator
- pkg/bus/bus.go (167 lines) - System bus
- pkg/ppu/ppu.go (605 lines) - PPU core
- pkg/ppu/rendering.go (144 lines) - Rendering pipeline
- pkg/ppu/sprites.go (197 lines) - Sprite handling
- pkg/ppu/registers.go (373 lines) - PPU registers
- pkg/cartridge/ - ROM loading and mappers
- pkg/controller/ - Input handling

PHP Emulator (to be improved):
/mnt/internalssd/Projects/NES/

Current documentation:
- go_nes_analysis.md
- critical_implementation_patterns.md
- GO_EMULATOR_EXPLORATION_SUMMARY.md (this file)

DOCUMENT FORMATS
================

All documents use Markdown format with:
- Code blocks (Go and PHP)
- Sections and subsections
- Tables for reference
- Bullet points for checklists
- Links within documents

View with:
- Any Markdown viewer
- GitHub web interface
- VS Code with Markdown preview
- cat (for terminal viewing)

GETTING HELP
============

Each document is self-contained but cross-references exist:
- Summary → Analysis → Patterns (increasing detail)
- Patterns → Go source code (for exact implementation)
- All documents → complete picture of architecture

When stuck:
1. Check critical_implementation_patterns.md for code examples
2. Read go_nes_analysis.md for architectural context
3. Examine actual Go source code in /home/andrew/Projects/nes-emulator

Created: November 5, 2025
