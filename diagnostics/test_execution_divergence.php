<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function getExecutionSnapshot(string $romPath, int $instructionLimit): array
{
    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run until instruction limit
    for ($i = 0; $i < $instructionLimit; $i++) {
        $cpu->step();
    }

    $instructions = $monitor->getInstructions();

    return [
        'instructions' => $instructions,
        'pc' => $cpu->pc,
        'a' => $cpu->accumulator,
        'x' => $cpu->registerX,
        'y' => $cpu->registerY,
        'sp' => $cpu->sp,
        'status' => $cpu->status->toInt(),
    ];
}

echo "Comparing execution divergence\n";
echo "==============================\n\n";

$instructionLimit = 1000;

$dk = getExecutionSnapshot(__DIR__ . '/roms/donkeykong.nes', $instructionLimit);
$sm = getExecutionSnapshot(__DIR__ . '/roms/supermario.nes', $instructionLimit);

echo "After $instructionLimit instructions:\n\n";

echo "Donkey Kong (WORKS):\n";
printf("  PC: \$%04X  A: \$%02X  X: \$%02X  Y: \$%02X  SP: \$%02X  Status: \$%02X\n",
    $dk['pc'], $dk['a'], $dk['x'], $dk['y'], $dk['sp'], $dk['status']);

echo "\nSuper Mario (BROKEN):\n";
printf("  PC: \$%04X  A: \$%02X  X: \$%02X  Y: \$%02X  SP: \$%02X  Status: \$%02X\n",
    $sm['pc'], $sm['a'], $sm['x'], $sm['y'], $sm['sp'], $sm['status']);

// Find where execution paths start to differ significantly
echo "\n\nAnalyzing execution patterns:\n";

$dkInstructions = $dk['instructions'];
$smInstructions = $sm['instructions'];

// Compare first 50 instructions
echo "\nFirst 50 instructions comparison:\n";
for ($i = 0; $i < 50 && $i < count($dkInstructions) && $i < count($smInstructions); $i++) {
    $dkInst = $dkInstructions[$i];
    $smInst = $smInstructions[$i];

    if ($dkInst['opcode'] !== $smInst['opcode'] || $dkInst['pc'] !== $smInst['pc']) {
        printf("DIVERGENCE at instruction #%d:\n", $i);
        printf("  DK: PC=\$%04X opcode=%s (byte \$%02X)\n",
            $dkInst['pc'], $dkInst['opcode'], $dkInst['instruction']);
        printf("  SM: PC=\$%04X opcode=%s (byte \$%02X)\n",
            $smInst['pc'], $smInst['opcode'], $smInst['instruction']);
        break;
    }
}

if ($i === 50) {
    echo "  First 50 instructions are similar pattern\n";
}

// Check if Super Mario is stuck in a loop
$smPCs = array_column($smInstructions, 'pc');
$smPCCounts = array_count_values($smPCs);
arsort($smPCCounts);

echo "\nSuper Mario's most executed addresses:\n";
$count = 0;
foreach ($smPCCounts as $pc => $execCount) {
    printf("  \$%04X: %d times (%.1f%%)\n", $pc, $execCount, ($execCount / count($smInstructions)) * 100);
    if (++$count >= 5) break;
}

echo "\nDone!\n";
