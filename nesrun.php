<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

// Parse command line arguments
$options = getopt('f:r:hvi', ['frames:', 'rom:', 'help', 'verbose', 'internal'], $optind);
$shortOpts = ['f' => 'frames', 'r' => 'rom', 'h' => 'help', 'v' => 'verbose', 'i' => 'internal'];

// Normalize options
foreach ($shortOpts as $short => $long) {
    if (isset($options[$short]) && !isset($options[$long])) {
        $options[$long] = $options[$short];
    }
}

// Show help
if (isset($options['help']) || (empty($argv[1]) && !isset($options['rom']))) {
    echo "NES Emulator ROM Runner\n\n";
    echo "Usage:\n";
    echo "  php nesrun.php <rom_file> [options]\n";
    echo "  php nesrun.php -r <rom_file> [options]\n\n";
    echo "Options:\n";
    echo "  -r, --rom <file>     ROM file to load (required)\n";
    echo "  -f, --frames <num>   Number of frames to run (default: 10)\n";
    echo "  -i, --internal       Show internal PPU register states\n";
    echo "  -v, --verbose        Verbose output\n";
    echo "  -h, --help           Show this help message\n\n";
    echo "Examples:\n";
    echo "  php nesrun.php roms/donkeykong_nes.rom\n";
    echo "  php nesrun.php -r roms/nestest.nes -f 20\n";
    echo "  php nesrun.php roms/tetris_nes.rom --internal --verbose\n\n";
    exit(0);
}

// Get ROM file
$romFile = null;
if (isset($options['rom'])) {
    $romFile = $options['rom'];
} else {
    // Get remaining arguments after options
    $remainingArgs = array_slice($argv, $optind);
    if (!empty($remainingArgs)) {
        $romFile = $remainingArgs[0];
    } elseif (!empty($argv[1]) && $argv[1][0] != '-') {
        $romFile = $argv[1];
    }
}

if (!$romFile) {
    echo "Error: ROM file not specified\n";
    echo "Use -h or --help for usage information\n";
    exit(1);
}

// Get frames to run
$frames = isset($options['frames']) ? (int)$options['frames'] : 10;
$verbose = isset($options['verbose']);
$showInternal = isset($options['internal']);

// Validate ROM file
if (!file_exists($romFile)) {
    echo "Error: ROM file not found: $romFile\n";
    exit(1);
}

if (!is_readable($romFile)) {
    echo "Error: ROM file not readable: $romFile\n";
    exit(1);
}

// Display header
echo "=== NES EMULATOR ROM RUNNER ===\n\n";
echo "ROM: " . basename($romFile) . "\n";
echo "Frames to run: $frames\n\n";

try {
    // Load ROM
    if ($verbose) {
        echo "Loading ROM...\n";
    }
    $nes = NES::fromROM($romFile);
    $ppu = $nes->getPPU();
    $bus = $nes->getBus();

    echo "✓ ROM loaded successfully\n";
    echo "  Size: " . number_format(filesize($romFile) / 1024, 1) . "KB\n\n";

    // Initial state
    echo "Initial state:\n";
    $ppustatus = $bus->read(0x2002);
    echo "  PPUSTATUS: \$" . sprintf("%02X", $ppustatus) .
         " (VBlank: " . (($ppustatus & 0x80) ? "SET" : "clear") . ")\n";

    if ($showInternal) {
        $ppuReflection = new ReflectionClass($ppu);
        $controlProperty = $ppuReflection->getProperty('control');
        $controlProperty->setAccessible(true);
        $control = $controlProperty->getValue($ppu);

        $maskProperty = $ppuReflection->getProperty('mask');
        $maskProperty->setAccessible(true);
        $mask = $maskProperty->getValue($ppu);

        echo "  PPUCTRL (internal):  \$" . sprintf("%02X", $control->get()) . "\n";
        echo "  PPUMASK (internal):  \$" . sprintf("%02X", $mask->get()) . "\n";
    }

    echo "\n";

    // Run frames
    echo "Running $frames frames...\n";
    $startTime = microtime(true);

    if ($showInternal) {
        // Track changes with internal state
        $ppuReflection = new ReflectionClass($ppu);
        $controlProperty = $ppuReflection->getProperty('control');
        $controlProperty->setAccessible(true);
        $maskProperty = $ppuReflection->getProperty('mask');
        $maskProperty->setAccessible(true);

        $lastCtrl = $controlProperty->getValue($ppu)->get();
        $lastMask = $maskProperty->getValue($ppu)->get();

        for ($i = 1; $i <= $frames; $i++) {
            $nes->runFrame();

            $currentCtrl = $controlProperty->getValue($ppu)->get();
            $currentMask = $maskProperty->getValue($ppu)->get();

            if ($currentCtrl != $lastCtrl || $currentMask != $lastMask) {
                echo "  Frame $i: ";
                if ($currentCtrl != $lastCtrl) {
                    echo "PPUCTRL \$" . sprintf("%02X", $lastCtrl) . "→\$" . sprintf("%02X", $currentCtrl);
                    if ($currentCtrl & 0x80) {
                        echo " (NMI enabled)";
                    }
                    echo "  ";
                }
                if ($currentMask != $lastMask) {
                    echo "PPUMASK \$" . sprintf("%02X", $lastMask) . "→\$" . sprintf("%02X", $currentMask);
                    if (($currentMask & 0x18) == 0x18) {
                        echo " (Rendering ON!)";
                    }
                }
                echo "\n";

                $lastCtrl = $currentCtrl;
                $lastMask = $currentMask;
            } elseif ($verbose) {
                echo "  Frame $i: No changes\n";
            }
        }
    } else {
        // Simple progress indicator
        for ($i = 1; $i <= $frames; $i++) {
            $nes->runFrame();
            if ($verbose || $i % 10 == 0) {
                echo "  Frame $i/$frames completed\n";
            }
        }
    }

    $endTime = microtime(true);
    $elapsed = $endTime - $startTime;

    echo "\n✓ Completed in " . number_format($elapsed, 2) . " seconds ";
    echo "(" . number_format($elapsed / $frames, 2) . "s per frame)\n\n";

    // Final state
    echo "Final state (after $frames frames):\n";

    if ($showInternal) {
        $control = $controlProperty->getValue($ppu);
        $mask = $maskProperty->getValue($ppu);

        echo "  PPUCTRL: \$" . sprintf("%02X", $control->get());
        echo " (NMI: " . (($control->get() & 0x80) ? "enabled" : "disabled") . ")\n";
        echo "  PPUMASK: \$" . sprintf("%02X", $mask->get());
        echo " (Rendering: " . ((($mask->get() & 0x18) == 0x18) ? "ENABLED ✓" : "disabled") . ")\n";

        if ($mask->get() & 0x18) {
            echo "    Bit 3 (Show BG):      " . (($mask->get() & 0x08) ? "ON  ✓" : "OFF") . "\n";
            echo "    Bit 4 (Show Sprites): " . (($mask->get() & 0x10) ? "ON  ✓" : "OFF") . "\n";
        }
    } else {
        echo "  PPUSTATUS: \$" . sprintf("%02X", $bus->read(0x2002)) . "\n";
        echo "  (Note: PPUCTRL/PPUMASK are write-only, use --internal to see values)\n";
    }

    echo "  Frame count: " . $ppu->getFrameCount() . "\n";

    // Check frame buffer
    $frameBuffer = $nes->getFrameBuffer();
    $uniqueColors = [];
    foreach ($frameBuffer as $pixel) {
        $color = implode(',', $pixel);
        $uniqueColors[$color] = true;
    }

    echo "  Unique colors in frame: " . count($uniqueColors) . "\n";

    if (count($uniqueColors) > 1) {
        echo "\n✓✓✓ GRAPHICS DETECTED! Multiple colors rendered! ✓✓✓\n";

        if ($verbose) {
            echo "\nSample colors:\n";
            $colorList = array_keys($uniqueColors);
            for ($i = 0; $i < min(5, count($colorList)); $i++) {
                echo "  RGB(" . $colorList[$i] . ")\n";
            }
        }
    } else {
        echo "\n  Only one color detected (solid gray)\n";
        echo "  Game has not enabled rendering yet\n";
    }

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    if ($verbose) {
        echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
}

echo "\n";
