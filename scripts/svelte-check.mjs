import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const executable = resolve(projectRoot, 'node_modules/.bin/svelte-check');
const child = spawn(executable, process.argv.slice(2), {
    cwd: projectRoot,
    stdio: ['inherit', 'pipe', 'pipe'],
});

child.stdout.pipe(process.stdout);

let stderrBuffer = '';
let suppressedVendorConfigLines = 0;

function shouldSuppressLine(line) {
    if (
        line.includes('Error while loading config at') &&
        line.includes('/vendor/laravel/framework/src/Illuminate/Foundation/resources/exceptions/renderer/vite.config.js')
    ) {
        suppressedVendorConfigLines = 2;
        return true;
    }

    if (suppressedVendorConfigLines > 0) {
        suppressedVendorConfigLines -= 1;
        return true;
    }

    return false;
}

child.stderr.on('data', (chunk) => {
    stderrBuffer += chunk.toString();
    const lines = stderrBuffer.split(/\r?\n/);
    stderrBuffer = lines.pop() ?? '';

    for (const line of lines) {
        if (!shouldSuppressLine(line)) {
            process.stderr.write(line + '\n');
        }
    }
});

child.on('close', (code, signal) => {
    if (stderrBuffer && !shouldSuppressLine(stderrBuffer)) {
        process.stderr.write(stderrBuffer);
    }

    if (signal) {
        process.kill(process.pid, signal);
        return;
    }

    process.exit(code ?? 1);
});
