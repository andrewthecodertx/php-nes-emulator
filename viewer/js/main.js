const canvas = document.getElementById('screen');
const ctx = canvas.getContext('2d');
const imageData = ctx.createImageData(256, 240);

let isRunning = false;
let totalFrames = 0;

const BUTTON_A = 0x01;
const BUTTON_B = 0x02;
const BUTTON_SELECT = 0x04;
const BUTTON_START = 0x08;
const BUTTON_UP = 0x10;
const BUTTON_DOWN = 0x20;
const BUTTON_LEFT = 0x40;
const BUTTON_RIGHT = 0x80;

let buttonStates = 0x00;

function log(message, type = 'info') {
    const logDiv = document.getElementById('log');
    const entry = document.createElement('div');
    entry.className = `log-entry ${type}`;
    entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
    logDiv.appendChild(entry);
    logDiv.scrollTop = logDiv.scrollHeight;

    while (logDiv.children.length > 100) {
        logDiv.removeChild(logDiv.firstChild);
    }
}

function setStatus(message, type = 'normal') {
    const status = document.getElementById('status');
    status.textContent = message;
    status.className = `status ${type}`;
}

async function resetEmulator() {
    log('Resetting emulator...');
    setStatus('Resetting...', 'running');

    try {
        const response = await fetch('../src/backend.php?action=reset', {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (data.success) {
            log('Emulator reset complete');
            totalFrames = 0;
            updateInfo(data);
            renderFrame(data.frameBuffer);
            setStatus('Ready', 'normal');
        } else {
            throw new Error(data.error || 'Reset failed');
        }
    } catch (error) {
        log(`Error: ${error.message}`, 'error');
        setStatus('Error', 'error');
    }
}

async function runFrames(count) {
    log(`Running ${count} frame(s)...`);
    setStatus(`Running ${count} frame(s)... Please wait, this may take several minutes.`, 'running');
    disableControls(true);

    const startTime = performance.now();

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), count * 5000 + 60000); // 5s per frame + 1min buffer

        log(`Sending request: frames=${count}, buttons=0x${buttonStates.toString(16).toUpperCase().padStart(2, '0')}`);
        const response = await fetch(`../src/backend.php?action=step&frames=${count}&buttons=${buttonStates}`, {
            credentials: 'same-origin',
            signal: controller.signal
        });
        clearTimeout(timeoutId);

        const data = await response.json();

        if (data.success) {
            const endTime = performance.now();
            const elapsed = endTime - startTime;

            totalFrames += count;
            updateInfo(data, elapsed);
            renderFrame(data.frameBuffer);

            log(`Completed ${count} frame(s) in ${elapsed.toFixed(0)}ms`);
            setStatus('Ready', 'normal');
        } else {
            throw new Error(data.error || 'Step failed');
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            log(`Request timed out after ${Math.round((performance.now() - startTime) / 1000)}s`, 'error');
            log(`Try smaller step counts (10 frames) or wait longer`, 'warning');
        } else {
            log(`Error: ${error.message}`, 'error');
        }
        setStatus('Error', 'error');
    } finally {
        disableControls(false);
    }
}

async function runContinuous() {
    if (!isRunning) return;

    try {
        await runFrames(1);
        if (isRunning) {
            setTimeout(runContinuous, 0);
        }
    } catch (error) {
        isRunning = false;
        updateRunButton();
    }
}

function toggleRun() {
    isRunning = !isRunning;
    updateRunButton();

    if (isRunning) {
        log('Starting continuous run...');
        runContinuous();
    } else {
        log('Stopped continuous run');
    }
}

function updateRunButton() {
    const btn = document.getElementById('run');
    btn.textContent = isRunning ? '⏸ Pause' : '▶ Run Continuous';
    document.getElementById('runStatus').textContent = isRunning ? 'Running' : 'Idle';
}

function disableControls(disabled) {
    document.getElementById('reset').disabled = disabled;
    document.getElementById('step1').disabled = disabled;
    document.getElementById('step10').disabled = disabled;
    document.getElementById('step100').disabled = disabled;
    document.getElementById('run').disabled = disabled;
}

function updateInfo(data, renderTime = 0) {
    document.getElementById('frameCount').textContent = data.frameCount || 0;
    document.getElementById('scanline').textContent = data.scanline || 0;
    document.getElementById('cycle').textContent = data.cycle || 0;
    document.getElementById('uniqueColors').textContent = data.uniqueColors || 0;
    document.getElementById('renderTime').textContent = renderTime.toFixed(0) + 'ms';
    document.getElementById('totalFrames').textContent = totalFrames;

    if (renderTime > 0) {
        const fps = (1000 / renderTime).toFixed(1);
        document.getElementById('fps').textContent = fps;
    }
}

function renderFrame(frameBuffer) {
    if (!frameBuffer || frameBuffer.length !== 256 * 240) {
        log('Invalid frame buffer', 'error');
        return;
    }

    // Convert flat RGB array to ImageData
    for (let i = 0; i < frameBuffer.length; i++) {
        const pixel = frameBuffer[i];
        const offset = i * 4;
        imageData.data[offset] = pixel[0];     // R
        imageData.data[offset + 1] = pixel[1]; // G
        imageData.data[offset + 2] = pixel[2]; // B
        imageData.data[offset + 3] = 255;      // A
    }

    ctx.putImageData(imageData, 0, 0);
}

const keyMap = {
    'KeyX': BUTTON_A,            // X = A button
    'KeyZ': BUTTON_B,            // Z = B button
    'ShiftLeft': BUTTON_SELECT,  // Left Shift = Select
    'Enter': BUTTON_START,       // Enter = Start
    'ArrowUp': BUTTON_UP,
    'ArrowDown': BUTTON_DOWN,
    'ArrowLeft': BUTTON_LEFT,
    'ArrowRight': BUTTON_RIGHT
};

window.addEventListener('keydown', (e) => {
    if (keyMap[e.code]) {
        e.preventDefault();
        const oldStates = buttonStates;
        buttonStates |= keyMap[e.code];
        if (oldStates !== buttonStates) {
            log(`Button pressed: ${getButtonName(keyMap[e.code])}`);
        }
        updateButtonVisuals();
    }
});

window.addEventListener('keyup', (e) => {
    if (keyMap[e.code]) {
        e.preventDefault();
        buttonStates &= ~keyMap[e.code];
        updateButtonVisuals();
    }
});

function getButtonName(button) {
    switch (button) {
        case BUTTON_A: return 'A';
        case BUTTON_B: return 'B';
        case BUTTON_SELECT: return 'Select';
        case BUTTON_START: return 'Start';
        case BUTTON_UP: return 'Up';
        case BUTTON_DOWN: return 'Down';
        case BUTTON_LEFT: return 'Left';
        case BUTTON_RIGHT: return 'Right';
        default: return 'Unknown';
    }
}

const buttonNameMap = {
    'a': BUTTON_A,
    'b': BUTTON_B,
    'select': BUTTON_SELECT,
    'start': BUTTON_START,
    'up': BUTTON_UP,
    'down': BUTTON_DOWN,
    'left': BUTTON_LEFT,
    'right': BUTTON_RIGHT
};

// Initialize on load
window.addEventListener('load', () => {
    log('NES Emulator viewer initialized');
    log('');
    log('Controller input is fully functional!');
    log('Use the controls below to play the game.');
    log('');
    log('CONTROLS:');
    log('  Arrow Keys = D-Pad');
    log('  X = A Button (Jump)');
    log('  Z = B Button');
    log('  Enter = Start');
    log('  Right Shift = Select');
    log('');
    log('Loading ROM...');
    resetEmulator();
});
