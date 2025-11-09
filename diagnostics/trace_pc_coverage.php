<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function tracePCCoverage(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run 5 frames
    for ($i = 0; $i < 5; $i++) {
        $nes->runFrame();
    }

    $instructions = $monitor->getInstructions();
    $totalInstructions = count($instructions);

    // Get unique PC addresses
    $pcAddresses = array_column($instructions, 'pc');
    $uniquePCs = array_unique($pcAddresses);
    $uniqueCount = count($uniquePCs);

    // Find most executed addresses (potential loops)
    $pcCounts = array_count_values($pcAddresses);
    arsort($pcCounts);

    printf("Total instructions: %d\n", $totalInstructions);
    printf("Unique PC addresses: %d\n", $uniqueCount);
    printf("Code coverage: %.2f%%\n", ($uniqueCount / 32768) * 100);

    echo "Top 10 most executed addresses:\n";
    $count = 0;
    foreach ($pcCounts as $pc => $execCount) {
        printf("  $%04X: %d times (%.1f%%)\n", $pc, $execCount, ($execCount / $totalInstructions) * 100);
        if (++$count >= 10) break;
    }

    // Check if stuck in tight loop
    $topPC = array_key_first($pcCounts);
    $topCount = $pcCounts[$topPC];
    $loopPercent = ($topCount / $totalInstructions) * 100;

    if ($loopPercent > 50) {
        printf("\nWARNING: Spending %.1f%% of time at $%04X - likely stuck in loop!\n", $loopPercent, $topPC);
    }

    echo "\n";
}

tracePCCoverage(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (WORKS)');
tracePCCoverage(__DIR__ . '/roms/supermario.nes', 'Super Mario (BROKEN)');

echo "Done!\n";
