<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Input\Controller;

echo "=== Testing When Super Mario Starts Polling Controller ===\n\n";

$romPath = __DIR__ . '/../../roms/supermario.nes';

if (!file_exists($romPath)) {
    echo "Error: supermario.nes not found\n";
    exit(1);
}

// Create a custom controller that logs reads
class LoggingController extends Controller
{
    public int $readCount = 0;
    public int $writeCount = 0;

    public function read(): int
    {
        $this->readCount++;
        return parent::read();
    }

    public function write(int $value): void
    {
        $this->writeCount++;
        parent::write($value);
    }
}

$nes = NES::fromROM($romPath);
$bus = $nes->getBus();

// Replace controller with logging version
$loggingController = new LoggingController();
$loggingController->setButtonStates(Controller::BUTTON_START);

$busReflection = new ReflectionClass($bus);
$controller1Prop = $busReflection->getProperty('controller1');
$controller1Prop->setAccessible(true);
$controller1Prop->setValue($bus, $loggingController);

echo "Running 50 frames and monitoring controller access...\n\n";

for ($frame = 0; $frame < 50; $frame++) {
    $beforeReads = $loggingController->readCount;
    $beforeWrites = $loggingController->writeCount;

    $nes->runFrame();

    $reads = $loggingController->readCount - $beforeReads;
    $writes = $loggingController->writeCount - $beforeWrites;

    if ($reads > 0 || $writes > 0) {
        echo sprintf("Frame %2d: %3d reads, %3d writes\n", $frame, $reads, $writes);
    }

    // Force rendering after frame 10
    if ($frame == 10) {
        $bus->write(0x2001, 0x1E);
        echo "           ^^^ Forced rendering ON\n";
    }
}

echo "\nTotal reads: {$loggingController->readCount}\n";
echo "Total writes: {$loggingController->writeCount}\n";
