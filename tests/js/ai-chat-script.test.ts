import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it, vi } from 'vitest';

const source = () => {
    const blade = readFileSync(
        join(process.cwd(), 'resources/views/filament/pages/ai-chat-script.blade.php'),
        'utf8',
    );
    const match = blade.match(/<script>([\s\S]*)<\/script>/);

    if (!match) {
        throw new Error('ai-chat-script.blade.php: inline <script> not found');
    }

    return match[1];
};

const loadComponent = (config: Record<string, unknown> = {}) => {
    const data = vi.fn();
    const fakeWindow: Record<string, unknown> = {
        Alpine: { data },
        innerWidth: 1200,
        matchMedia: () => ({ matches: false }),
    };
    const fakeDocument = {
        addEventListener: vi.fn(),
    };

    new Function('window', 'document', source())(fakeWindow, fakeDocument);

    const factory = data.mock.calls.find((call) => call[0] === 'adminAiChat')?.[1];

    if (typeof factory !== 'function') {
        throw new Error('adminAiChat was not registered with Alpine');
    }

    const component = factory({
        csrfToken: 'token',
        provider: 'gemini',
        providerLabel: 'Gemini',
        modelName: 'model',
        workspace: { id: 1, name: 'Default Workspace' },
        adminUser: { id: 1, name: 'Admin', first_name: 'Admin', email: 'a@b.c' },
        workspaceStats: { sections: 0, students: 0, pendingApprovals: 0 },
        ...config,
    });

    return { data, component };
};

describe('ai-chat-script inline component', () => {
    it('parses without syntax errors', () => {
        expect(() => new Function('window', 'document', source())).not.toThrow();
    });

    it('registers the adminAiChat Alpine component', () => {
        const { data } = loadComponent();

        expect(data).toHaveBeenCalledWith('adminAiChat', expect.any(Function));
    });

    it('initializes the session list from initialSessions', () => {
        const initialSessions = [
            { id: 7, uuid: 'uuid-7', title: 'Draft quiz', mode: 'solo', bot: null, participantBotIds: [] },
        ];
        const { component } = loadComponent({ initialSessions, initialActiveSession: null });

        expect(component.sessions).toEqual(initialSessions);
        expect(component.filteredSessions).toBe(initialSessions);
        expect(component.activeSessionId).toBeNull();
        expect(component.botPicker).toEqual({ coordinator: null, specialists: [] });
    });

    it('restores the active session and bot teams state', () => {
        const initialActiveSession = {
            id: 42,
            uuid: 'uuid-42',
            title: 'Team chat',
            mode: 'group',
            bot: null,
            participantBotIds: [3, 4],
        };
        const { component } = loadComponent({
            initialSessions: [initialActiveSession],
            initialActiveSession,
        });

        expect(component.activeSessionId).toBe(42);
        expect(component.activeSessionUuid).toBe('uuid-42');
    });
});
