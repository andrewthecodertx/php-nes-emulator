<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Cartridge\Mapper1;

if ($argc < 2) {
    echo "Usage: php debug_mapper.php <rom_file> [frames]\n";
    exit(1);
}

$romPath = $argv[1];
$framesToRun = $argv[2] ?? 200;
$logFile = __DIR__ . '/mapper_log.txt';

if (!file_exists($romPath)) {
    echo "ROM not found: $romPath\n";
    exit(1);
}

// Clear previous log
file_put_contents($logFile, '');

echo "Debugging ROM: " . basename($romPath) . "\n";
echo "Running for $framesToRun frames...\n";
echo "Logging to: $logFile\n\n";

try {
    $nes = NES::fromROM($romPath);
    $mapper = $nes->getMapper();

    // Enable logging if the mapper supports it
    if (method_exists($mapper, 'setLogger')) {
        $logger = function ($message) use ($logFile) {
            file_put_contents($logFile, $message . "\n", FILE_APPEND);
        };
        $mapper->setLogger($logger);
    } else {
        echo "Warning: Mapper does not support logging.\n";
    }

    for ($i = 0; $i < $framesToRun; $i++) {
        $nes->runFrame();
    }

    echo "--- Emulation Finished ---\n";
    echo "Final PPU Frame: " . $nes->getPPU()->getFrameCount() . "\n";
    echo "Final CPU PC: 0x" . dechex($nes->getCPU()->pc) . "\n";

    if ($mapper instanceof Mapper1) {
        $reflection = new ReflectionClass($mapper);
        
        $controlProp = $reflection->getProperty('control');
        $controlProp->setAccessible(true);
        $control = $controlProp->getValue($mapper);

        $prgBankProp = $reflection->getProperty('prgBank');
        $prgBankProp->setAccessible(true);
        $prgBank = $prgBankProp->getValue($mapper);

        $chrBank0Prop = $reflection->getProperty('chrBank0');
        $chrBank0Prop->setAccessible(true);
        $chrBank0 = $chrBank0Prop->getValue($mapper);

        $chrBank1Prop = $reflection->getProperty('chrBank1');
        $chrBank1Prop->setAccessible(true);
        $chrBank1 = $chrBank1Prop->getValue($mapper);

        echo "\n--- Mapper 1 Final State ---\n";
        printf("Control Register: 0x%02X (%s)\n", $control, decbin($control));
        printf("  - Mirroring: %d\n", $control & 0x03);
        printf("  - PRG Mode:  %d\n", ($control >> 2) & 0x03);
        printf("  - CHR Mode:  %d\n", ($control >> 4) & 0x01);
        printf("PRG Bank: 0x%02X\n", $prgBank);
        printf("CHR Bank 0: 0x%02X\n", $chrBank0);
        printf("CHR Bank 1: 0x%02X\n", $chrBank1);
    }

} catch (Exception $e) {
    echo "\n--- ERROR ---\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
    echo "\n";
}
