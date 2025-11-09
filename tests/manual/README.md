# Manual Tests

This directory contains manual test scripts for the NES emulator.

## Running Tests

### Mapper Tests

Tests all implemented mappers by loading and running ROMs:

```bash
cd tests/manual
php test_mappers.php
```

This will test:
- Mapper 0 (NROM) - Donkey Kong, Super Mario Bros, NESTest
- Mapper 1 (MMC1) - Tetris
- Mapper 3 (CNROM) - Joust

The test verifies that each mapper can load and run without errors.

## Adding New Tests

When adding new manual tests:
1. Place the test file in this directory
2. Use `__DIR__ . '/../../'` to reference the project root
3. Document the test in this README
