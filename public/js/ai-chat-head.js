(() => {
    if (!window.EchoChatHead) {
        const clampToViewport = (position, viewport, element, margin = 16) => {
            const maxX = Math.max(margin, viewport.width - element.width - margin);
            const maxY = Math.max(margin, viewport.height - element.height - margin);

            return {
                x: Math.min(Math.max(position.x, margin), maxX),
                y: Math.min(Math.max(position.y, margin), maxY),
            };
        };

        const parseStoredPosition = (raw) => {
            if (typeof raw !== 'string' || raw === '') {
                return null;
            }

            try {
                const parsed = JSON.parse(raw);

                if (parsed && Number.isFinite(parsed.x) && Number.isFinite(parsed.y)) {
                    return { x: parsed.x, y: parsed.y };
                }
            } catch {
                return null;
            }

            return null;
        };

        const shouldDisplay = ({ standalone, pathname, chatPaths }) =>
            Boolean(standalone) && !(chatPaths || []).includes(pathname);

        const panelPosition = (bubble, panel, viewport, margin = 16) => {
            const maxX = Math.max(margin, viewport.width - panel.width - margin);
            const maxY = Math.max(margin, viewport.height - panel.height - margin);
            const x = Math.min(Math.max(bubble.x + bubble.width - panel.width, margin), maxX);
            const above = bubble.y - panel.height - margin;
            const below = bubble.y + bubble.height + margin;
            const y = above >= margin ? above : Math.min(Math.max(below, margin), maxY);

            return { x, y };
        };

        window.EchoChatHead = { clampToViewport, parseStoredPosition, shouldDisplay, panelPosition };
    }

    if (window.self !== window.top) {
        return;
    }

    const container = document.querySelector('[data-echo-chat-head]');

    if (!container || container.dataset.chatHeadReady === '1') {
        return;
    }

    container.dataset.chatHeadReady = '1';

    const storageKey = 'echoAiChatHead.position';
    const margin = 16;
    const dragThreshold = 6;
    const bubble = container.querySelector('[data-chat-head-bubble]');
    const panel = container.querySelector('[data-chat-head-panel]');
    const frame = container.querySelector('[data-chat-head-frame]');
    const chatUrl = container.dataset.chatUrl || '/admin/ai-assistant-app';
    const standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    let chatPaths = [];

    try {
        chatPaths = JSON.parse(container.dataset.chatPaths || '[]');
    } catch {
        chatPaths = [];
    }

    let open = false;
    let suppressClick = false;
    let pointer = null;
    let position = null;

    const viewport = () => ({ width: window.innerWidth, height: window.innerHeight });
    const bubbleSize = () => ({ width: bubble.offsetWidth || 56, height: bubble.offsetHeight || 56 });

    const defaultPosition = () => {
        const size = bubbleSize();
        const view = viewport();

        return { x: view.width - size.width - margin, y: view.height - size.height - margin };
    };

    const applyBubblePosition = () => {
        bubble.style.left = `${position.x}px`;
        bubble.style.top = `${position.y}px`;
        bubble.style.right = 'auto';
        bubble.style.bottom = 'auto';
    };

    const positionPanel = () => {
        const spot = window.EchoChatHead.panelPosition(
            { ...position, ...bubbleSize() },
            { width: panel.offsetWidth, height: panel.offsetHeight },
            viewport(),
            margin,
        );

        panel.style.left = `${spot.x}px`;
        panel.style.top = `${spot.y}px`;
    };

    const closePanel = () => {
        open = false;
        panel.hidden = true;
        bubble.setAttribute('aria-expanded', 'false');
    };

    const openPanel = () => {
        if (frame.dataset.loaded !== '1') {
            frame.src = `${chatUrl}${chatUrl.includes('?') ? '&' : '?'}embed=1`;
            frame.dataset.loaded = '1';
        }

        open = true;
        panel.hidden = false;
        bubble.setAttribute('aria-expanded', 'true');
        positionPanel();
    };

    const syncVisibility = () => {
        const display = window.EchoChatHead.shouldDisplay({
            standalone,
            pathname: window.location.pathname,
            chatPaths,
        });

        if (!display && open) {
            closePanel();
        }

        container.hidden = !display;
    };

    const stored = window.EchoChatHead.parseStoredPosition(window.localStorage.getItem(storageKey));
    position = window.EchoChatHead.clampToViewport(stored || defaultPosition(), viewport(), bubbleSize(), margin);
    bubble.classList.remove('end-4', 'bottom-4');
    applyBubblePosition();

    bubble.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
            return;
        }

        pointer = {
            id: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            originX: position.x,
            originY: position.y,
            moved: false,
        };

        bubble.setPointerCapture(event.pointerId);
    });

    bubble.addEventListener('pointermove', (event) => {
        if (!pointer || event.pointerId !== pointer.id) {
            return;
        }

        const dx = event.clientX - pointer.startX;
        const dy = event.clientY - pointer.startY;

        if (!pointer.moved && Math.hypot(dx, dy) < dragThreshold) {
            return;
        }

        pointer.moved = true;
        position = window.EchoChatHead.clampToViewport(
            { x: pointer.originX + dx, y: pointer.originY + dy },
            viewport(),
            bubbleSize(),
            margin,
        );

        applyBubblePosition();

        if (open) {
            positionPanel();
        }
    });

    const endPointer = (event) => {
        if (!pointer || event.pointerId !== pointer.id) {
            return;
        }

        const moved = pointer.moved;
        pointer = null;

        if (bubble.hasPointerCapture(event.pointerId)) {
            bubble.releasePointerCapture(event.pointerId);
        }

        if (moved) {
            suppressClick = true;
            window.localStorage.setItem(storageKey, JSON.stringify(position));
        }
    };

    bubble.addEventListener('pointerup', endPointer);
    bubble.addEventListener('pointercancel', endPointer);

    bubble.addEventListener('click', (event) => {
        if (suppressClick) {
            suppressClick = false;
            event.preventDefault();
            event.stopPropagation();

            return;
        }

        if (open) {
            closePanel();
        } else {
            openPanel();
        }
    });

    container.querySelector('[data-chat-head-expand]')?.addEventListener('click', () => {
        window.location.assign(chatUrl);
    });

    container.querySelector('[data-chat-head-minimize]')?.addEventListener('click', closePanel);
    container.querySelector('[data-chat-head-close]')?.addEventListener('click', closePanel);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && open) {
            closePanel();
            bubble.focus();
        }
    });

    window.addEventListener('resize', () => {
        position = window.EchoChatHead.clampToViewport(position, viewport(), bubbleSize(), margin);
        applyBubblePosition();

        if (open) {
            positionPanel();
        }
    });

    document.addEventListener('livewire:navigated', syncVisibility);

    syncVisibility();
})();
