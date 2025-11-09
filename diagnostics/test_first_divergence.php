<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function getFirstInstructions(string $romPath, int $count): array
{
    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $bus = $nes->getBus();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run instructions
    for ($i = 0; $i < $count; $i++) {
        $cpu->step();
    }

    return $monitor->getInstructions();
}

echo "Comparing first 100 instructions\n";
echo "================================\n\n";

$dk = getFirstInstructions(__DIR__ . '/roms/donkeykong.nes', 100);
$sm = getFirstInstructions(__DIR__ . '/roms/supermario.nes', 100);

echo "Instruction-by-instruction comparison:\n\n";

$diverged = false;
for ($i = 0; $i < min(count($dk), count($sm)); $i++) {
    $dkInst = $dk[$i];
    $smInst = $sm[$i];

    // Show both instructions side by side
    printf("%3d: DK: PC=$%04X op=$%02X %-3s   SM: PC=$%04X op=$%02X %-3s",
        $i,
        $dkInst['pc'],
        $dkInst['instruction'],
        $dkInst['opcode'],
        $smInst['pc'],
        $smInst['instruction'],
        $smInst['opcode']
    );

    if ($dkInst['pc'] !== $smInst['pc'] || $dkInst['opcode'] !== $smInst['opcode']) {
        echo " <-- DIVERGENCE";
        $diverged = true;
    }

    echo "\n";

    if ($diverged && $i > 10) {
        echo "\n(Stopping after first divergence)\n";
        break;
    }
}

echo "\nDone!\n";
