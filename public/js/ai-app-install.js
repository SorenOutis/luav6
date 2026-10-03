(() => {
    if (window.aiAppInstallInitialized) return;
    window.aiAppInstallInitialized = true;

    let installPrompt = null;
    const installed = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const hideControls = () => {
        document.querySelectorAll('[data-ai-app-install]').forEach((element) => {
            element.hidden = installed();
        });
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        document.querySelectorAll('[data-ai-app-install]').forEach((element) => { element.hidden = true; });
    });
    document.addEventListener('livewire:navigated', hideControls);
    hideControls();

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-ai-app-install-button]');
        if (!button) return;
        const container = button.closest('[data-ai-app-install]');
        const help = container?.querySelector('[data-ai-app-install-help]') || button.parentElement.querySelector('[data-ai-app-install-help]');
        if (installPrompt) {
            const prompt = installPrompt;
            installPrompt = null;
            try {
                await prompt.prompt();
                await prompt.userChoice;
                return;
            } catch {
                // Browser may invalidate a deferred install prompt after navigation.
            }
        }
        help.hidden = false;
        help.textContent = window.isSecureContext
            ? 'iPhone/iPad: open in Safari, tap Share, then Add to Home Screen. Android: use browser menu → Install app or Add to Home screen. Login and internet required.'
            : 'Installation requires HTTPS. Open the secure site on your phone, then choose Add to Home Screen from the browser menu.';
    });
})();
