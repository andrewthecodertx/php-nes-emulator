<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

/**
 * Compare CPU execution traces between working and broken games
 * to identify where Super Mario diverges from Donkey Kong
 */

function traceGame(string $romPath, int $frames, int $maxInstructions): array
{
    echo "Loading ROM: $romPath\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    // Attach CPU monitor
    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    echo "Running $frames frames (max $maxInstructions instructions)...\n";

    // Run frames
    for ($frame = 0; $frame < $frames; $frame++) {
        $nes->runFrame();

        // Check if we've hit instruction limit
        $instructionCount = count($monitor->getInstructions());
        if ($instructionCount >= $maxInstructions) {
            echo "Hit instruction limit at frame $frame\n";
            break;
        }
    }

    $instructions = $monitor->getInstructions();
    echo "Total instructions executed: " . count($instructions) . "\n";
    echo "Total cycles: " . $monitor->getTotalCycles() . "\n\n";

    return $instructions;
}

function analyzeTrace(array $instructions, string $label): void
{
    echo "=== $label Analysis ===\n";

    // Show first 50 instructions
    echo "First 50 instructions:\n";
    for ($i = 0; $i < min(50, count($instructions)); $i++) {
        $inst = $instructions[$i];
        printf("  %04X: %02X (%s)\n", $inst['pc'], $inst['instruction'], $inst['opcode']);
    }

    // Count unique PC addresses
    $uniquePCs = array_unique(array_column($instructions, 'pc'));
    echo "\nUnique PC addresses visited: " . count($uniquePCs) . "\n";

    // Find most frequently executed addresses (loops)
    $pcCounts = array_count_values(array_column($instructions, 'pc'));
    arsort($pcCounts);
    echo "\nTop 10 most executed addresses:\n";
    $count = 0;
    foreach ($pcCounts as $pc => $execCount) {
        printf("  %04X: %d times\n", $pc, $execCount);
        if (++$count >= 10) break;
    }

    // Count opcode usage
    $opcodeCounts = array_count_values(array_column($instructions, 'opcode'));
    arsort($opcodeCounts);
    echo "\nTop 10 most used opcodes:\n";
    $count = 0;
    foreach ($opcodeCounts as $opcode => $execCount) {
        printf("  %s: %d times\n", $opcode, $execCount);
        if (++$count >= 10) break;
    }

    echo "\n";
}

function compareTraces(array $trace1, array $trace2, string $label1, string $label2): void
{
    echo "=== Comparing $label1 vs $label2 ===\n";

    $minLength = min(count($trace1), count($trace2));
    echo "Comparing first $minLength instructions...\n";

    // Find first divergence point
    for ($i = 0; $i < $minLength; $i++) {
        if ($trace1[$i]['pc'] !== $trace2[$i]['pc'] ||
            $trace1[$i]['instruction'] !== $trace2[$i]['instruction']) {
            echo "DIVERGENCE at instruction #$i:\n";
            printf("  %s: PC=%04X opcode=%02X (%s)\n",
                $label1,
                $trace1[$i]['pc'],
                $trace1[$i]['instruction'],
                $trace1[$i]['opcode']
            );
            printf("  %s: PC=%04X opcode=%02X (%s)\n",
                $label2,
                $trace2[$i]['pc'],
                $trace2[$i]['instruction'],
                $trace2[$i]['opcode']
            );

            // Show context (5 instructions before and after)
            echo "\nContext (5 before):\n";
            for ($j = max(0, $i - 5); $j < $i; $j++) {
                printf("  %s #%d: PC=%04X %s\n", $label1, $j, $trace1[$j]['pc'], $trace1[$j]['opcode']);
            }

            echo "\nContext (5 after divergence):\n";
            for ($j = $i; $j < min($minLength, $i + 5); $j++) {
                printf("  %s #%d: PC=%04X %s\n", $label1, $j, $trace1[$j]['pc'], $trace1[$j]['opcode']);
                printf("  %s #%d: PC=%04X %s\n", $label2, $j, $trace2[$j]['pc'], $trace2[$j]['opcode']);
            }

            break;
        }
    }

    if ($i === $minLength) {
        echo "No divergence found in first $minLength instructions!\n";
        echo "Traces are identical for this period.\n";
    }

    echo "\n";
}

// Test configuration
$maxInstructions = 5000;  // Limit to first 5000 instructions
$frames = 10;  // Run up to 10 frames

echo "NES CPU Execution Trace Comparison\n";
echo "===================================\n\n";

// Trace Donkey Kong (working)
$dkTrace = traceGame(__DIR__ . '/roms/donkeykong.nes', $frames, $maxInstructions);

// Trace Super Mario (broken)
$smTrace = traceGame(__DIR__ . '/roms/supermario.nes', $frames, $maxInstructions);

// Analyze each trace
analyzeTrace($dkTrace, "Donkey Kong (WORKING)");
analyzeTrace($smTrace, "Super Mario (BROKEN)");

// Compare traces
compareTraces($dkTrace, $smTrace, "Donkey Kong", "Super Mario");

echo "Done!\n";
