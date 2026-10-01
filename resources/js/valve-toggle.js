const terminalStatuses = new Set(['ack', 'failed', 'expired']);

document.querySelectorAll('.valve-toggle').forEach((toggle) => {
    if (toggle.dataset.valveInitialized === 'true') return;

    toggle.dataset.valveInitialized = 'true';
    toggle.addEventListener('change', () => requestValveChange(toggle));
});

async function requestValveChange(toggle) {
    const requestedValue = toggle.checked ? 1 : 0;
    const actualValue = Number(toggle.dataset.original);
    const action = requestedValue ? 'Open' : 'Close';

    // Keep showing the confirmed physical state while asking for confirmation.
    applyActualState(toggle, actualValue);

    const confirmed = await confirmValveChange(action, toggle.dataset.deviceName);
    if (!confirmed) return;

    toggle.checked = Boolean(requestedValue);
    setPending(toggle, true, requestedValue ? 'Opening...' : 'Closing...');

    try {
        const response = await fetch(`/devices/${toggle.dataset.device}/valve`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ value: requestedValue }),
        });
        const body = await response.json();

        if (!response.ok) throw new Error(body.message || 'Command could not be sent.');

        await pollCommand(toggle, body.data.id, actualValue);
    } catch (error) {
        applyActualState(toggle, actualValue);
        setPending(toggle, false, error.message || 'Command failed.', true);
    }
}

function confirmValveChange(action, deviceName) {
    const modal = document.querySelector('#valve-confirmation-modal');
    const description = modal.querySelector('#valve-modal-description');
    const confirmButton = modal.querySelector('[data-valve-modal-confirm]');
    const cancelButton = modal.querySelector('[data-valve-modal-cancel]');
    const isOpening = action === 'Open';

    description.textContent = `${action} the drain valve for ${deviceName || 'this device'}? `
        + (isOpening
            ? 'Water will begin draining after the device confirms this command.'
            : 'The valve will be closed after the device confirms this command.');
    confirmButton.textContent = isOpening ? 'Open valve' : 'Close valve';

    modal.showModal();
    confirmButton.focus();

    return new Promise((resolve) => {
        const controller = new AbortController();
        const options = { signal: controller.signal };

        const finish = (confirmed) => {
            controller.abort();
            modal.close();
            resolve(confirmed);
        };

        confirmButton.addEventListener('click', () => finish(true), options);
        cancelButton.addEventListener('click', () => finish(false), options);
        modal.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(false);
        }, options);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) finish(false);
        }, options);
    });
}

async function pollCommand(toggle, commandId, fallbackValue) {
    while (true) {
        await delay(750);

        const response = await fetch(
            `/devices/${toggle.dataset.device}/valve/commands/${commandId}`,
            { headers: { Accept: 'application/json' } },
        );
        const body = await response.json();

        if (!response.ok) throw new Error(body.message || 'Unable to read command status.');

        const command = body.data;
        if (!terminalStatuses.has(command.status)) continue;

        if (command.status === 'ack') {
            const actualValue = Number(command.actual_value);
            applyActualState(toggle, actualValue);
            setPending(toggle, false, actualValue ? 'Valve opened' : 'Valve closed');
            return;
        }

        applyActualState(toggle, fallbackValue);
        setPending(
            toggle,
            false,
            command.status === 'expired' ? 'No response from device' : (command.message || 'Command failed'),
            true,
        );
        return;
    }
}

function applyActualState(toggle, value) {
    toggle.checked = Boolean(value);
    toggle.dataset.original = String(Number(value));
    toggle.closest('.valve-control').querySelector('.valve-state-label').textContent = value ? 'Open' : 'Closed';
}

function setPending(toggle, pending, message, failed = false) {
    const control = toggle.closest('.valve-control');
    const card = toggle.closest('.device-card');
    const spinner = card.querySelector('.valve-spinner');
    const status = card.querySelector('.valve-status-text');

    toggle.disabled = pending;
    control.classList.toggle('cursor-wait', pending);
    control.classList.toggle('opacity-60', pending);
    spinner.classList.toggle('hidden', !pending);
    status.textContent = message;
    status.classList.toggle('text-red-600', failed);
    status.classList.toggle('text-emerald-600', !pending && !failed);
}

function delay(milliseconds) {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}
