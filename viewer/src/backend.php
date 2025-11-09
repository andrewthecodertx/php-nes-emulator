<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

set_time_limit(600); // 10 minutes
ini_set('max_execution_time', '600');

require_once __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

session_start();

$romFile = "arkanoid.nes";

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    $action = $_GET['action'] ?? 'status';

    switch ($action) {
        case 'reset':
            handleReset();
            break;
        case 'step':
            $frames = (int)($_GET['frames'] ?? 1);
            $buttons = (int)($_GET['buttons'] ?? 0);
            handleStep($frames, $buttons);
            break;
        case 'status':
            handleStatus();
            break;
        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

function forceRendering(NES $nes): void
{
    $bus = $nes->getBus();
    // PPUMASK ($2001): Enable background + sprites + leftmost 8 pixels
    $bus->write(0x2001, 0x1E);
}

function handleReset(): void
{
    global $romFile;

    // Create fresh NES instance
    $nes = NES::fromROM(__DIR__ . '/../../roms/' . $romFile);

    // run 10 frames to allow game initialization
    for ($i = 0; $i < 10; $i++) {
        $nes->runFrame();
    }

    forceRendering($nes);
    $nes->runFrame();

    $_SESSION['frame_count'] = 11;
    $_SESSION['button_history'] = []; // Clear button history

    // Serialize and store NES state
    $_SESSION['nes_state'] = serialize($nes);

    respondWithState($nes);
}

function handleStep(int $frames, int $buttons = 0): void
{
    // Restore NES state from session
    if (!isset($_SESSION['nes_state'])) {
        throw new Exception("No NES state in session. Call reset first.");
    }

    $nes = unserialize($_SESSION['nes_state']);
    $controller = $nes->getBus()->getController1();

    // Run the requested number of frames with current button state
    for ($i = 0; $i < $frames; $i++) {
        $controller->setButtonStates($buttons);
        $nes->runFrame();
    }

    // Update frame count
    $currentFrames = $_SESSION['frame_count'] ?? 0;
    $_SESSION['frame_count'] = $currentFrames + $frames;

    // Store button states for history (optional, for debugging)
    $buttonHistory = $_SESSION['button_history'] ?? [];
    for ($i = $currentFrames; $i < $currentFrames + $frames; $i++) {
        $buttonHistory[$i] = $buttons;
    }
    $_SESSION['button_history'] = $buttonHistory;

    // Save updated NES state
    $_SESSION['nes_state'] = serialize($nes);

    // Return state
    respondWithState($nes);
}

function handleStatus(): void
{
    if (!isset($_SESSION['nes_state'])) {
        echo json_encode([
            'success' => true,
            'initialized' => false
        ]);

        return;
    }

    // Restore NES state from session
    $nes = unserialize($_SESSION['nes_state']);

    respondWithState($nes);
}

/**
 * Send state and frame buffer as JSON
 */
function respondWithState(NES $nes): void
{
    $ppu = $nes->getPPU();
    $frameBuffer = $nes->getFrameBuffer();

    // Count unique colors
    $uniqueColors = [];
    foreach ($frameBuffer as $pixel) {
        $color = implode(',', $pixel);
        $uniqueColors[$color] = true;
    }

    echo json_encode([
        'success' => true,
        'initialized' => true,
        'frameBuffer' => $frameBuffer,
        'frameCount' => $ppu->getFrameCount(),
        'scanline' => $ppu->getScanline(),
        'cycle' => $ppu->getCycle(),
        'uniqueColors' => count($uniqueColors)
    ]);
}
