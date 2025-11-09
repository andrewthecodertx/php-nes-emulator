<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$cpu = $nes->getCPU();

$monitor = new CPUMonitor();
$cpu->setMonitor($monitor);

// Run first 3 frames normally
for ($frame = 1; $frame <= 3; $frame++) {
    $monitor->clearLog();
    $nes->runFrame();
}

// Now trace frame 4 where the crash happens
$monitor->clearLog();
$nes->runFrame();

$instructions = $monitor->getInstructions();

// Find first occurrence of $8057
$firstHit = null;
foreach ($instructions as $i => $inst) {
    if ($inst['pc'] === 0x8057) {
        $firstHit = $i;
        break;
    }
}

if ($firstHit !== null) {
    printf("Frame 4: First hit of $8057 at instruction #%d\n\n", $firstHit);
    
    echo "30 instructions leading up to the crash:\n";
    $start = max(0, $firstHit - 30);
    
    for ($i = $start; $i <= min($firstHit + 3, count($instructions) - 1); $i++) {
        $inst = $instructions[$i];
        printf("%5d: PC=$%04X  op=$%02X  %-3s", 
            $i, $inst['pc'], $inst['instruction'], $inst['opcode']);
        
        if ($i === $firstHit) {
            echo " <-- CRASH: ENTERS INFINITE LOOP";
        }
        
        // Highlight jumps and branches
        if (in_array($inst['opcode'], ['JMP', 'JSR', 'BEQ', 'BNE', 'BCS', 'BCC', 'BMI', 'BPL', 'BVS', 'BVC'])) {
            echo " ***";
        }
        
        echo "\n";
    }
}

echo "\nDone!\n";
