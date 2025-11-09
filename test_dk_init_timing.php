<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Testing Different Initialization Timings ===\n\n";

// Test 1: No forceRendering at all
echo "Test 1: No forceRendering\n";
$nes1 = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');
for ($i = 0; $i < 11; $i++) {
    $nes1->runFrame();
}
$fb1 = $nes1->getFrameBuffer();
$colors1 = [];
foreach ($fb1 as $p) $colors1[implode(',', $p)] = true;
echo "  Colors: " . count($colors1) . "\n\n";

// Test 2: forceRendering after just 1 frame
echo "Test 2: forceRendering after 1 frame\n";
$nes2 = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');
$nes2->runFrame();
$nes2->getBus()->write(0x2001, 0x1E);
for ($i = 0; $i < 10; $i++) {
    $nes2->runFrame();
}
$fb2 = $nes2->getFrameBuffer();
$colors2 = [];
foreach ($fb2 as $p) $colors2[implode(',', $p)] = true;
echo "  Colors: " . count($colors2) . "\n\n";

// Test 3: Current method (forceRendering after 10, then 1 more)
echo "Test 3: Current method (10 + force + 1)\n";
$nes3 = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');
for ($i = 0; $i < 10; $i++) {
    $nes3->runFrame();
}
$nes3->getBus()->write(0x2001, 0x1E);
$nes3->runFrame();
$fb3 = $nes3->getFrameBuffer();
$colors3 = [];
foreach ($fb3 as $p) $colors3[implode(',', $p)] = true;
echo "  Colors: " . count($colors3) . "\n\n";

echo "Now testing if they progress after more frames...\n\n";

// Run 20 more frames on test 3
for ($i = 0; $i < 20; $i++) {
    $nes3->runFrame();
}
$fb3 = $nes3->getFrameBuffer();
$colors3 = [];
foreach ($fb3 as $p) $colors3[implode(',', $p)] = true;
echo "Test 3 after 20 more frames: " . count($colors3) . " colors\n";
