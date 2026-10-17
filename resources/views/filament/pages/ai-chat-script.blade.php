    <script>
        function registerAdminAiChat() {
            if (!window.Alpine) return;
            // livewire:navigated fires on every SoftNavigation; re-registering
            // the same Alpine.data name every time is wasteful and Alpine
            // warns about duplicates.
            if (window.adminAiChatRegistered) return;
            window.adminAiChatRegistered = true;
            window.Alpine.data('adminAiChat', (config) => ({
                csrfToken: config.csrfToken,
                provider: config.provider,
                providerLabel: config.providerLabel,
                modelName: config.modelName,
                workspace: config.workspace,
                adminUser: config.adminUser,
                sessions: config.initialSessions || [],
                initialActiveSession: config.initialActiveSession || null,
                workspaceStats: config.workspaceStats || {},

                // Start with the history sidebar closed on phones and in the
                // installed app — it covers the whole chat there. Desktop
                // browsers keep it open.
                sidebarOpen: typeof window !== 'undefined'
                    ? window.innerWidth >= 768 && ! window.matchMedia('(display-mode: standalone)').matches
                    : true,
                pinnedToBottom: true,
                copiedMessageId: null,
                speakingContent: null,
                showNewMessages: false,
                searchQuery: '',
                searchResults: [],
                activeSessionId: config.initialActiveSession ? config.initialActiveSession.id : null,
                activeSessionUuid: config.initialActiveSession ? config.initialActiveSession.uuid : null,
                linkCopied: false,
                messages: [],
                aiActions: [],
                inputMessage: '',
                isStreaming: false,
                abortController: null,
                actionLoading: {},
                expirationNow: Date.now(),
                continuationActionId: null,
                continuationInterval: null,
                continuationCountdown: 0,
                continuationFollowUp: '',

                isListening: false,
                voiceSupported: (typeof window !== 'undefined' && ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)),
                voiceRecognition: null,
                voiceError: null,
                voiceErrorTimer: null,
                isSpeaking: false,

                init() {
                    this.isListening = false;
                    this.isStreaming = false;
                    this.voiceError = null;
                    this.isSpeaking = false;
                        this.speakingContent = null;

                    const rememberSession = () => {
                        try {
                            const identifier = this.activeSessionUuid || (this.activeSessionId ? String(this.activeSessionId) : '');

                            if (identifier) {
                                window.localStorage.setItem('echoAiChatHead.session', identifier);
                            } else {
                                window.localStorage.removeItem('echoAiChatHead.session');
                            }
                        } catch (e) {}
                    };

                    this.$watch('activeSessionUuid', rememberSession);
                    this.$watch('activeSessionId', rememberSession);

                    this.$nextTick(() => {
                        if (this.$refs.welcomeComposerInput && !this.activeSessionId) {
                            this.$refs.welcomeComposerInput.focus();
                        }
                    });

                    // Keep approval-window countdowns fresh while a card is open.
                    if (this.expirationTimer) {
                        clearInterval(this.expirationTimer);
                    }
                    this.expirationTimer = setInterval(() => {
                        this.expirationNow = Date.now();
                    }, 30000);

                    const urlParams = new URLSearchParams(window.location.search);
                    const requestedParam = urlParams.get('c') || urlParams.get('session');

                    if (this.initialActiveSession) {
                        this.selectSession(this.initialActiveSession, false);
                    } else if (requestedParam && requestedParam !== 'undefined' && requestedParam !== 'null') {
                        this.selectSession(requestedParam, false);
                    }

                    window.addEventListener('popstate', this._popStateHandler = () => {
                        if (this.isStreaming) return;
                        const currentParams = new URLSearchParams(window.location.search);
                        const targetUuid = currentParams.get('c') || currentParams.get('session');

                        if (targetUuid && targetUuid !== 'undefined' && targetUuid !== 'null') {
                            if (this.activeSessionUuid !== targetUuid && String(this.activeSessionId) !== String(targetUuid)) {
                                this.selectSession(targetUuid, false);
                            }
                        } else {
                            if (this.activeSessionId !== null || this.activeSessionUuid !== null) {
                                this.activeSessionId = null;
                                this.activeSessionUuid = null;
                                this.messages = [];
                                this.aiActions = [];
                                this.inputMessage = '';
                                this.$nextTick(() => {
                                    if (this.$refs.welcomeComposerInput) {
                                        this.$refs.welcomeComposerInput.focus();
                                    }
                                });
                            }
                        }
                    });
                },

                destroy() {
                    if (this.expirationTimer) {
                        clearInterval(this.expirationTimer);
                        this.expirationTimer = null;
                    }
                    if (this.searchTimer) {
                        clearTimeout(this.searchTimer);
                        this.searchTimer = null;
                    }
                    if (this.streamingTimer) {
                        clearInterval(this.streamingTimer);
                        this.streamingTimer = null;
                    }
                    if (this.continuationInterval) {
                        clearInterval(this.continuationInterval);
                        this.continuationInterval = null;
                    }
                    if (this.voiceErrorTimer) {
                        clearTimeout(this.voiceErrorTimer);
                        this.voiceErrorTimer = null;
                    }
                    if (this.abortController) {
                        try {
                            this.abortController.abort();
                        } catch (e) {
                            // Already aborted.
                        }
                        this.abortController = null;
                    }
                    if (this._popStateHandler) {
                        window.removeEventListener('popstate', this._popStateHandler);
                        this._popStateHandler = null;
                    }
                    this.stopVoice();
                    this.stopSpeaking();
                },

                get timeOfDayGreeting() {
                    const hour = new Date().getHours();
                    if (hour >= 5 && hour < 12) return 'Good morning';
                    if (hour >= 12 && hour < 17) return 'Good afternoon';
                    if (hour >= 17 && hour < 22) return 'Good evening';
                    return 'Working late';
                },

                get currentDateFormatted() {
                    return new Intl.DateTimeFormat('en-US', {
                        weekday: 'long',
                        month: 'short',
                        day: 'numeric'
                    }).format(new Date());
                },

                get greetingSubtext() {
                    const stats = this.workspaceStats || {};
                    const parts = [];

                    if (stats.sections > 0) {
                        parts.push(stats.sections + (stats.sections === 1 ? ' section' : ' sections'));
                    }
                    if (stats.students > 0) {
                        parts.push(stats.students + (stats.students === 1 ? ' student' : ' students'));
                    }
                    if (stats.pendingApprovals > 0) {
                        parts.push(stats.pendingApprovals + (stats.pendingApprovals === 1 ? ' approval waiting' : ' approvals waiting'));
                    }

                    if (parts.length > 0) {
                        return parts.join(' · ');
                    }

                    return 'Ask Echo about exams, grades, submissions, or announcements.';
                },

                get firstName() {
                    if (this.adminUser && this.adminUser.first_name) {
                        return this.adminUser.first_name;
                    }
                    const raw = (this.adminUser && this.adminUser.name) ? this.adminUser.name : '';
                    const fallback = raw.trim().split(/\s+/)[0];
                    return fallback || 'Admin';
                },

                get timeGreeting() {
                    return this.timeOfDayGreeting;
                },

                get greetingLine() {
                    return this.firstName
                        ? `${this.timeGreeting}, ${this.firstName}`
                        : this.timeGreeting;
                },

                get filteredSessions() {
                    const q = this.searchQuery.trim().toLowerCase();
                    if (!q) {
                        return this.sessions;
                    }
                    const localMatches = this.sessions.filter(s => (s.title || '').toLowerCase().includes(q));
                    const serverMatches = (this.searchResults || []).filter(s => !localMatches.some(l => String(l.id) === String(s.id)));
                    return [...localMatches, ...serverMatches];
                },

                searchTimer: null,

                fetchSearchResults() {
                    const q = this.searchQuery.trim();
                    if (this.searchTimer) {
                        clearTimeout(this.searchTimer);
                    }
                    if (q.length < 2) {
                        this.searchResults = [];
                        return;
                    }
                    this.searchTimer = setTimeout(async () => {
                        try {
                            const res = await fetch('/api/chats/search?q=' + encodeURIComponent(q), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (res.ok) {
                                const data = await res.json();
                                this.searchResults = data.data || [];
                            }
                        } catch (e) {}
                    }, 300);
                },

                get groupedSessions() {
                    const today = [];
                    const yesterday = [];
                    const previousWeek = [];
                    const older = [];

                    const now = new Date();
                    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
                    const startOfYesterday = startOfToday - (24 * 60 * 60 * 1000);
                    const startOf7Days = startOfToday - (7 * 24 * 60 * 1000);

                    this.filteredSessions.forEach(session => {
                        const time = session.updated_at ? new Date(session.updated_at).getTime() : 0;
                        if (time >= startOfToday) {
                            today.push(session);
                        } else if (time >= startOfYesterday) {
                            yesterday.push(session);
                        } else if (time >= startOf7Days) {
                            previousWeek.push(session);
                        } else {
                            older.push(session);
                        }
                    });

                    return { today, yesterday, previousWeek, older };
                },

                async selectSession(target, updateUrl = true) {
                    if (this.isStreaming) return;
                    if (!target || target === 'undefined' || target === 'null') return;

                    let session = null;
                    let identifier = target;

                    if (typeof target === 'object' && target !== null) {
                        session = target;
                        identifier = target.uuid || target.id;
                    } else if (target) {
                        session = this.sessions.find(s => s.uuid === target || String(s.id) === String(target));
                        identifier = target;
                    }

                    if (!identifier || identifier === 'undefined' || identifier === 'null') return;

                    if (session) {
                        this.activeSessionId = session.id;
                        this.activeSessionUuid = session.uuid || null;
                    } else {
                        if (typeof identifier === 'number' || (typeof identifier === 'string' && /^\d+$/.test(identifier))) {
                            this.activeSessionId = parseInt(identifier, 10);
                            this.activeSessionUuid = null;
                        } else {
                            this.activeSessionId = null;
                            this.activeSessionUuid = String(identifier);
                        }
                    }

                    const urlParam = this.activeSessionUuid || (this.activeSessionId ? String(this.activeSessionId) : null) || (identifier ? String(identifier) : null);
                    if (updateUrl && urlParam && urlParam !== 'undefined' && urlParam !== 'null') {
                        const url = new URL(window.location.href);
                        url.searchParams.set('c', urlParam);
                        url.searchParams.delete('session');
                        window.history.pushState({ sessionUuid: urlParam }, '', url.toString());
                    }

                    this.messages = [];
                    this.aiActions = [];

                    try {
                        const res = await fetch(`/api/chats/${identifier}/messages`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            const data = await res.json();
                            if (data.session) {
                                this.activeSessionId = data.session.id;
                                this.activeSessionUuid = data.session.uuid;
                                if (!this.sessions.some(s => s.id === data.session.id)) {
                                    this.sessions.unshift({
                                        id: data.session.id,
                                        uuid: data.session.uuid,
                                        title: data.session.title || 'New chat',
                                        updated_at: data.session.updated_at,
                                        updated_at_human: data.session.updated_at_human || 'recently'
                                    });
                                }
                            }

                            this.messages = (data.data || []).map(m => ({
                                id: m.id,
                                role: m.role,
                                content: m.content,
                                thinking: m.thinking || null,
                                thinkingOpen: false,
                                typing: false,
                                createdAt: m.createdAt || null,
                                actionIds: []
                            }));
                            this.scrollToBottom(true);
                            this.$nextTick(() => {
                                if (this.$refs.composerInput) {
                                    this.$refs.composerInput.focus();
                                }
                            });
                        }
                        await this.loadAiActions(this.activeSessionId || identifier);
                        this.associateActionsWithMessages();
                    } catch (e) {
                        console.error('Error loading session messages:', e);
                    }
                },

                newChat() {
                    if (this.isStreaming) return;
                    this.activeSessionId = null;
                    this.activeSessionUuid = null;
                    this.messages = [];
                    this.aiActions = [];
                    this.inputMessage = '';

                    const url = new URL(window.location.href);
                    url.searchParams.delete('c');
                    url.searchParams.delete('session');
                    const cleanUrl = url.pathname + (url.search ? url.search : '');
                    window.history.pushState({}, '', cleanUrl);

                    this.$nextTick(() => {
                        if (this.$refs.welcomeComposerInput) {
                            this.$refs.welcomeComposerInput.focus();
                        }
                    });
                },

                async createChatSession() {
                    try {
                        const res = await fetch('/api/chats', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({})
                        });
                        if (res.ok) {
                            const json = await res.json();
                            const newSession = {
                                id: json.session.id,
                                uuid: json.session.uuid,
                                title: 'New chat',
                                updated_at: new Date().toISOString(),
                                updated_at_human: 'just now'
                            };
                            this.sessions.unshift(newSession);
                            this.activeSessionId = newSession.id;
                            this.activeSessionUuid = newSession.uuid;

                            const url = new URL(window.location.href);
                            url.searchParams.set('c', newSession.uuid || newSession.id);
                            url.searchParams.delete('session');
                            window.history.pushState({ sessionUuid: newSession.uuid || newSession.id }, '', url.toString());

                            return newSession.id;
                        }
                    } catch (e) {
                        console.error('Error creating new chat session:', e);
                    }
                    return null;
                },

                async confirmDeleteSession(sessionId) {
                    if (!confirm('Are you sure you want to delete this conversation?')) return;
                    try {
                        const res = await fetch(`/api/chats/${sessionId}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            this.sessions = this.sessions.filter(s => s.id !== sessionId);
                            if (this.activeSessionId === sessionId) {
                                if (this.sessions.length > 0) {
                                    this.selectSession(this.sessions[0]);
                                } else {
                                    this.newChat();
                                }
                            }
                        }
                    } catch (e) {
                        console.error('Error deleting session:', e);
                    }
                },

                async copyConversationLink(item = null) {
                    const targetUuid = (item && (item.uuid || item.id)) || this.activeSessionUuid || this.activeSessionId;
                    if (!targetUuid) return;

                    const url = new URL(window.location.href);
                    url.searchParams.set('c', targetUuid);
                    url.searchParams.delete('session');
                    const fullUrl = url.toString();

                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(fullUrl);
                        } else {
                            const textarea = document.createElement('textarea');
                            textarea.value = fullUrl;
                            textarea.style.position = 'fixed';
                            textarea.style.left = '-9999px';
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                        }

                        this.linkCopied = true;
                        setTimeout(() => {
                            this.linkCopied = false;
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy link:', err);
                    }
                },

                async loadAiActions(sessionId) {
                    const sid = sessionId || this.activeSessionId;
                    if (!sid) return;
                    try {
                        const res = await fetch(`/api/ai-actions?session_id=${sid}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            const data = await res.json();
                            const existingMap = new Map((this.aiActions || []).map(a => [a.id, a]));
                            this.aiActions = (data.data || []).map(action => {
                                const existing = existingMap.get(action.id);
                                return {
                                    ...action,
                                    _loading: existing?._loading ?? false,
                                    _inlineAnswer: existing?._inlineAnswer || '',
                                    _collapsed: existing?._collapsed ?? false
                                };
                            });
                        }
                    } catch (e) {
                        console.error('Error loading AI actions:', e);
                    }
                },

                associateActionsWithMessages() {
                    if (!this.messages.length || !this.aiActions.length) return;

                    const assistantMessages = [];
                    for (let i = 0; i < this.messages.length; i++) {
                        if (this.messages[i].role === 'assistant') {
                            let prevUserTime = null;
                            for (let u = i - 1; u >= 0; u--) {
                                if (this.messages[u].role === 'user' && this.messages[u].createdAt) {
                                    const parsed = Date.parse(this.messages[u].createdAt);
                                    if (!isNaN(parsed)) {
                                        prevUserTime = parsed;
                                        break;
                                    }
                                }
                            }

                            let nextUserTime = null;
                            for (let u = i + 1; u < this.messages.length; u++) {
                                if (this.messages[u].role === 'user' && this.messages[u].createdAt) {
                                    const parsed = Date.parse(this.messages[u].createdAt);
                                    if (!isNaN(parsed)) {
                                        nextUserTime = parsed;
                                        break;
                                    }
                                }
                            }

                            const rawMsgTime = this.messages[i].createdAt;
                            const parsedMsgTime = rawMsgTime ? Date.parse(rawMsgTime) : null;
                            const msgTime = (parsedMsgTime !== null && !isNaN(parsedMsgTime)) ? parsedMsgTime : null;

                            assistantMessages.push({
                                msg: this.messages[i],
                                index: i,
                                msgTime: msgTime,
                                startTime: prevUserTime ?? (msgTime ? msgTime - 60000 : null),
                                endTime: nextUserTime ?? Infinity,
                            });
                        }
                    }

                    if (!assistantMessages.length) return;

                    assistantMessages.forEach(am => {
                        am.msg.actionIds = am.msg.actionIds || [];
                    });

                    const assignedActionIds = new Set();
                    this.messages.forEach(m => {
                        if (m.actionIds) {
                            m.actionIds.forEach(id => assignedActionIds.add(id));
                        }
                    });

                    this.aiActions.forEach(action => {
                        if (assignedActionIds.has(action.id)) return;

                        const rawActionTime = action.createdAt;
                        const parsedActionTime = rawActionTime ? Date.parse(rawActionTime) : null;
                        const actionTime = (parsedActionTime !== null && !isNaN(parsedActionTime)) ? parsedActionTime : null;
                        let matched = null;

                        if (actionTime !== null) {
                            matched = assistantMessages.find(am => {
                                const start = am.startTime ?? 0;
                                const end = am.endTime ?? Infinity;
                                return actionTime >= (start - 10000) && actionTime < end;
                            });
                        }

                        if (!matched && actionTime !== null) {
                            let closest = null;
                            let minDiff = Infinity;
                            assistantMessages.forEach(am => {
                                if (am.msgTime !== null) {
                                    const diff = Math.abs(actionTime - am.msgTime);
                                    if (diff < minDiff) {
                                        minDiff = diff;
                                        closest = am;
                                    }
                                }
                            });
                            matched = closest;
                        }

                        if (!matched) {
                            matched = assistantMessages[assistantMessages.length - 1];
                        }

                        if (matched) {
                            if (!matched.msg.actionIds) {
                                matched.msg.actionIds = [];
                            }
                            if (!matched.msg.actionIds.includes(action.id)) {
                                matched.msg.actionIds.push(action.id);
                            }
                            assignedActionIds.add(action.id);
                        }
                    });
                },

                getActionsForMessage(msg, index) {
                    if (msg.role !== 'assistant') return [];
                    if (msg.action) return [msg.action];

                    if (msg.actionIds && msg.actionIds.length > 0) {
                        return this.aiActions.filter(a => msg.actionIds.includes(a.id));
                    }

                    // Fallback: If this is the last assistant message and there are actions not claimed by any message
                    const isLastAssistant = !this.messages.slice(index + 1).some(m => m.role === 'assistant');
                    if (isLastAssistant && this.aiActions.length > 0) {
                        const assignedIds = new Set();
                        this.messages.forEach(m => {
                            if (m.actionIds) {
                                m.actionIds.forEach(id => assignedIds.add(id));
                            }
                        });
                        return this.aiActions.filter(a => !assignedIds.has(a.id));
                    }

                    return [];
                },

                sendAdjustment(action) {
                    if (!action) return;
                    const text = (action._inlineAnswer || '').trim();
                    if (!text || this.isStreaming) return;

                    action._inlineAnswer = '';
                    this.sendMessage(text);
                },

                formatDuration(secs) {
                    if (!secs || secs < 0) return '0:01';
                    const mins = Math.floor(secs / 60);
                    const rem = secs % 60;
                    return `${mins}:${rem < 10 ? '0' : ''}${rem}`;
                },

                expiresInLabel(action) {
                    if (!action?.expiresAt) return null;
                    const remainingMs = new Date(action.expiresAt).getTime() - this.expirationNow;
                    if (remainingMs <= 0) return null;
                    const minutes = Math.max(1, Math.ceil(remainingMs / 60000));
                    return 'Approval window: ' + minutes + ' min left';
                },

                isLikelyAgentTask(text) {
                    if (!text) return false;
                    const lower = text.toLowerCase();
                    const keywords = [
                        'exam', 'test', 'quiz', 'assign', 'grade', 'student',
                        'course', 'section', 'announc', 'material', 'task',
                        'xp', 'create', 'delete', 'update', 'research', 'generate',
                        'add', 'make', 'draft', 'award', 'curriculum'
                    ];
                    return keywords.some(k => lower.includes(k));
                },

                deriveTaskTitle(text) {
                    if (!text) return 'Echo is preparing your request';
                    const lower = text.toLowerCase();
                    if (lower.includes('exam') || lower.includes('quiz') || lower.includes('test')) {
                        return 'Preparing: Create exam';
                    }
                    if (lower.includes('assignment') || lower.includes('homework')) {
                        return 'Preparing: Create assignment';
                    }
                    if (lower.includes('announc')) {
                        return 'Preparing: Post announcement';
                    }
                    if (lower.includes('course')) {
                        return 'Preparing: Course operation';
                    }
                    if (lower.includes('section')) {
                        return 'Preparing: Create class section';
                    }
                    if (lower.includes('xp') || lower.includes('gamif') || lower.includes('award')) {
                        return 'Preparing: Student XP award';
                    }
                    if (lower.includes('research') || lower.includes('curriculum')) {
                        return 'Autonomous research & analysis';
                    }
                    return 'Echo is preparing your request';
                },

                handleToolCallEvent(target, event) {
                    if (!target) return;
                    if (!target.activity) {
                        target.activity = {
                            title: 'Running your request',
                            status: 'running',
                            startedAt: Date.now(),
                            collapsed: false,
                            hasToolCalls: true,
                            steps: [
                                {
                                    id: 'auth',
                                    label: 'Workspace context loaded',
                                    target: this.workspace?.name || 'Active Workspace',
                                    status: 'completed',
                                    time: null
                                }
                            ]
                        };
                    } else {
                        target.activity.hasToolCalls = true;
                    }

                    // Mark previous running step as completed
                    const prevRunning = target.activity.steps.find(s => s.status === 'running');
                    if (prevRunning) {
                        prevRunning.status = 'completed';
                        const elapsed = Math.max(1, Math.round((Date.now() - (prevRunning.startTime || Date.now())) / 1000));
                        prevRunning.time = this.formatDuration(elapsed);
                    }

                    let args = {};
                    try {
                        args = typeof event.arguments === 'string' ? JSON.parse(event.arguments) : (event.arguments || {});
                    } catch (e) {
                        args = {};
                    }

                    const toolName = event.tool_name || 'agent_tool';
                    const step = {
                        id: toolName + '_' + Date.now(),
                        tool: toolName,
                        label: '',
                        target: '',
                        badge: null,
                        status: 'running',
                        startTime: Date.now(),
                        time: null
                    };

                    if (toolName === 'research_topic') {
                        step.label = 'Researching topic & curriculum';
                        step.target = args.topic ? ('Wikipedia · ' + args.topic) : 'Wikipedia / Knowledge Base';
                        target.activity.title = args.topic ? ('Autonomous research: ' + args.topic) : 'Autonomous research & analysis';
                    } else if (toolName === 'generate_exam_questions') {
                        step.label = 'Generating exam questions & answer key';
                        step.target = args.topic ? (args.topic + (args.question_count ? ` (${args.question_count} Qs)` : '')) : 'Curriculum Question Generator';
                    } else if (toolName === 'create_exam') {
                        step.label = 'Preparing exam creation';
                        step.target = 'create_exam';
                        step.badge = 'Needs review';
                        target.activity.title = args.title ? ('Preparing: ' + args.title) : 'Preparing: Create exam';
                    } else if (toolName === 'update_exam') {
                        step.label = 'Preparing exam update';
                        step.target = 'update_exam';
                        step.badge = 'Needs review';
                    } else if (toolName === 'create_assignment') {
                        step.label = 'Preparing assignment';
                        step.target = 'create_assignment';
                        step.badge = 'Needs review';
                        target.activity.title = args.title ? ('Preparing: ' + args.title) : 'Preparing: Create assignment';
                    } else if (toolName === 'update_assignment') {
                        step.label = 'Preparing assignment update';
                        step.target = 'update_assignment';
                        step.badge = 'Needs review';
                    } else if (toolName === 'post_announcement') {
                        step.label = 'Preparing announcement broadcast';
                        step.target = 'post_announcement';
                        step.badge = 'Needs review';
                        target.activity.title = args.title ? ('Preparing: ' + args.title) : 'Preparing: Post announcement';
                    } else if (toolName === 'award_student_xp') {
                        step.label = 'Preparing student gamification XP';
                        step.target = 'award_student_xp';
                        step.badge = 'Needs review';
                        target.activity.title = 'Preparing: Student XP award';
                    } else if (toolName === 'record_grade' || toolName === 'grade_submission' || toolName === 'update_grade' || toolName === 'record_activity_task_score') {
                        step.label = toolName === 'record_activity_task_score' ? 'Preparing activity task score review' : 'Preparing grade record review';
                        step.target = toolName;
                        step.badge = 'Needs review';
                    } else if (toolName === 'manage_maintenance') {
                        step.label = 'Preparing platform maintenance review';
                        step.target = 'manage_maintenance';
                        step.badge = 'Needs review';
                        target.activity.title = 'Preparing: Platform maintenance';
                    } else if (toolName === 'export_report') {
                        step.label = 'Preparing downloadable report export';
                        step.target = 'export_report';
                        step.badge = 'Needs review';
                        target.activity.title = 'Preparing: Export report';
                    } else if (toolName === 'create_course' || toolName === 'update_course') {
                        step.label = 'Preparing course configuration';
                        step.target = toolName;
                        step.badge = 'Needs review';
                    } else if (toolName === 'create_section' || toolName === 'update_section') {
                        step.label = 'Preparing section configuration';
                        step.target = toolName;
                        step.badge = 'Needs review';
                    } else if (toolName === 'create_user' || toolName === 'update_user' || toolName === 'reset_user_password') {
                        step.label = 'Preparing user management review';
                        step.target = toolName;
                        step.badge = 'Needs review';
                    } else if (toolName === 'create_activity_task') {
                        step.label = 'Preparing activity task';
                        step.target = toolName;
                        step.badge = 'Needs review';
                        target.activity.title = args.title ? ('Preparing: ' + args.title) : 'Preparing: Create activity task';
                    } else if (toolName === 'create_learning_material') {
                        step.label = 'Preparing learning material';
                        step.target = toolName;
                        step.badge = 'Needs review';
                        target.activity.title = args.title ? ('Preparing: ' + args.title) : 'Preparing: Learning material';
                    } else if (toolName === 'workspace_overview') {
                        step.label = 'Querying workspace analytics';
                        step.target = 'workspace_overview';
                    } else if (toolName === 'courses_admin' || toolName === 'sections_admin' || toolName === 'exams_admin' || toolName === 'assignments_admin' || toolName === 'announcements_admin' || toolName === 'materials_admin' || toolName === 'activity_tasks_admin') {
                        step.label = 'Inspecting workspace curriculum & records';
                        step.target = toolName;
                    } else if (toolName === 'admin_grades' || toolName === 'submissions_to_grade') {
                        step.label = 'Reviewing student submissions & grades';
                        step.target = toolName;
                    } else if (toolName === 'students') {
                        step.label = 'Querying enrolled students list';
                        step.target = 'students';
                    } else if (toolName.startsWith('delete_')) {
                        step.label = 'Preparing deletion: ' + toolName.replace('delete_', '');
                        step.target = toolName;
                        step.badge = 'Needs review';
                    } else {
                        step.label = 'Executing ' + toolName.replace(/_/g, ' ');
                        step.target = toolName;
                    }

                    target.activity.steps.push(step);
                    this.scrollToBottom();
                },

                handleToolResultEvent(target, event, sessionId) {
                    if (!target) return;
                    const toolName = event.tool_name || '';

                    if (target.activity && target.activity.steps) {
                        const step = [...target.activity.steps].reverse().find(s => s.tool === toolName && s.status === 'running')
                            || target.activity.steps.find(s => s.status === 'running');
                        if (step) {
                            step.status = 'completed';
                            const elapsed = Math.max(1, Math.round((Date.now() - (step.startTime || Date.now())) / 1000));
                            step.time = this.formatDuration(elapsed);
                        }
                    }

                    const isStagingTool = [
                        'create_exam', 'update_exam', 'delete_exam',
                        'create_assignment', 'update_assignment', 'delete_assignment',
                        'create_course', 'update_course', 'delete_course',
                        'create_section', 'update_section', 'delete_section',
                        'create_user', 'update_user', 'delete_user', 'reset_user_password',
                        'post_announcement', 'update_announcement', 'delete_announcement',
                        'create_learning_material', 'delete_learning_material',
                        'create_activity_task', 'delete_activity_task',
                        'award_student_xp', 'record_grade', 'update_grade', 'delete_grade',
                        'grade_submission', 'record_activity_task_score',
                        'generate_exam_questions', 'manage_maintenance', 'export_report'
                    ].includes(toolName);

                    if (isStagingTool) {
                        if (target.activity) {
                            target.activity.status = 'needs_approval';
                        }
                        const beforeActionIds = new Set((this.aiActions || []).map(a => a.id));
                        this.loadAiActions(sessionId).then(() => {
                            (this.aiActions || []).forEach(a => {
                                if (!beforeActionIds.has(a.id)) {
                                    target.actionIds = target.actionIds || [];
                                    if (!target.actionIds.includes(a.id)) {
                                        target.actionIds.push(a.id);
                                    }
                                }
                            });
                            this.associateActionsWithMessages();
                        });
                    }
                },

                async approveAction(action) {
                    if (!action) return;
                    if (!action.nonce) {
                        action.error = action.status === 'pending'
                            ? 'The approval window has expired. Please ask Echo to prepare it again.'
                            : ('This action has already been ' + (action.status || 'processed') + '.');
                        return;
                    }
                    action._loading = true;
                    this.actionLoading[action.id] = true;
                    action.error = null;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrfToken;
                        const res = await fetch(`/api/ai-actions/${action.id}/approve`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ nonce: action.nonce })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.data) {
                            action.status = data.data.status;
                            action.result = data.data.result;
                            action.error = null;
                            const inList = this.aiActions.find(a => a.id === action.id);
                            if (inList) {
                                inList.status = data.data.status;
                                inList.result = data.data.result;
                            }
                            this.handleActionApproved(action);
                        } else {
                            action.error = res.status === 409
                                ? 'This action was already resolved, likely in another tab. Refresh to see the latest state.'
                                : (data.message || ('Execution failed (HTTP ' + res.status + ')'));
                            if (data.data?.status) {
                                action.status = data.data.status;
                            }
                        }
                    } catch (e) {
                        action.error = e.message || 'Execution error';
                        console.error('approveAction error:', e);
                    } finally {
                        action._loading = false;
                        this.actionLoading[action.id] = false;
                    }
                },

                handleActionApproved(action) {
                    // Auto-proceed disabled: the manual continue button is the only path forward.
                },

                cancelAutoProceed() {
                    if (this.continuationInterval) {
                        clearInterval(this.continuationInterval);
                        this.continuationInterval = null;
                    }
                    this.continuationActionId = null;
                    this.continuationCountdown = 0;
                    this.continuationFollowUp = '';
                },

                manualContinue(action) {
                    if (!action || this.isStreaming) return;
                    this.cancelAutoProceed();
                    const followUpText = `Approved: ${action.result || action.title}. Please proceed immediately with the next steps from my previous request.`;
                    this.sendMessage(followUpText);
                },

                async rejectAction(action) {
                    if (!action) return;
                    if (!action.nonce) {
                        action.error = action.status === 'pending'
                            ? 'The approval window has expired. Please ask Echo to prepare it again.'
                            : ('This action has already been ' + (action.status || 'processed') + '.');
                        return;
                    }
                    action._loading = true;
                    this.actionLoading[action.id] = true;
                    action.error = null;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrfToken;
                        const res = await fetch(`/api/ai-actions/${action.id}/reject`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ nonce: action.nonce })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.data) {
                            action.status = data.data.status;
                            action.error = null;
                            const inList = this.aiActions.find(a => a.id === action.id);
                            if (inList) {
                                inList.status = data.data.status;
                            }
                        } else {
                            action.error = res.status === 409
                                ? 'This action was already resolved, likely in another tab. Refresh to see the latest state.'
                                : (data.message || ('Rejection failed (HTTP ' + res.status + ')'));
                            if (data.data?.status) {
                                action.status = data.data.status;
                            }
                        }
                    } catch (e) {
                        action.error = e.message || 'Rejection error';
                        console.error('rejectAction error:', e);
                    } finally {
                        action._loading = false;
                        this.actionLoading[action.id] = false;
                    }
                },

                prefillPrompt(prompt) {
                    if (this.isStreaming) return;
                    this.inputMessage = prompt;
                    this.$nextTick(() => {
                        const input = this.$refs.welcomeComposerInput || this.$refs.composerInput;
                        if (!input) return;
                        this.autoGrowTextarea({ target: input });
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    });
                },

                async sendMessage(explicitText = null) {
                    this.cancelAutoProceed();
                    if (explicitText !== null) {
                        this.inputMessage = explicitText;
                    }
                    const text = this.inputMessage.trim();
                    if (!text || this.isStreaming) return;

                    if (!this.activeSessionId) {
                        const createdId = await this.createChatSession();
                        if (!createdId) return;
                    }

                    const sessionId = this.activeSessionId;
                    this.inputMessage = '';
                    if (this.$refs.composerInput) {
                        this.$refs.composerInput.style.height = 'auto';
                    }
                    if (this.$refs.welcomeComposerInput) {
                        this.$refs.welcomeComposerInput.style.height = 'auto';
                    }

                    this.stopSpeaking();
                    this.stopVoice();

                    // Append user message
                    this.messages.push({
                        role: 'user',
                        content: text,
                        typing: false,
                        createdAt: new Date().toISOString()
                    });

                    // Update session title locally if new
                    const activeSession = this.sessions.find(s => s.id === sessionId);
                    if (activeSession && (activeSession.title === 'New chat' || !activeSession.title)) {
                        activeSession.title = text.length > 50 ? text.substring(0, 50) + '...' : text;
                    }

                    // Check if the prompt is an agent workspace task to show initial activity immediately
                    const hasLikelyTask = this.isLikelyAgentTask(text);
                    const assistantIndex = this.messages.push({
                        role: 'assistant',
                        content: '',
                        thinking: null,
                        thinkingOpen: true,
                        thinkingStartedAt: Date.now(),
                        thinkingMs: null,
                        elapsedSeconds: 0,
                        typing: true,
                        createdAt: new Date().toISOString(),
                        actionIds: [],
                        activity: hasLikelyTask ? {
                            title: this.deriveTaskTitle(text),
                            status: 'running',
                            startedAt: Date.now(),
                            collapsed: false,
                            hasToolCalls: false,
                            steps: [
                                {
                                    id: 'auth',
                                    label: 'Workspace context loaded',
                                    target: this.workspace?.name || 'Active Workspace',
                                    status: 'completed',
                                    time: null
                                },
                                {
                                    id: 'analyze',
                                    label: 'Analyzing request & workspace context',
                                    target: 'Echo Agent',
                                    status: 'running',
                                    startTime: Date.now(),
                                    time: null
                                }
                            ]
                        } : null
                    }) - 1;

                    if (this.streamingTimer) {
                        clearInterval(this.streamingTimer);
                    }
                    this.streamingTimer = setInterval(() => {
                        const target = this.messages[assistantIndex];
                        if (target && target.typing) {
                            const started = target.thinkingStartedAt;
                            if (started) {
                                target.elapsedSeconds = Math.max(1, Math.round((Date.now() - started) / 1000));
                            }
                            if (target.activity && target.activity.steps) {
                                const runningStep = target.activity.steps.find(s => s.status === 'running');
                                if (runningStep && runningStep.startTime) {
                                    const secs = Math.max(1, Math.round((Date.now() - runningStep.startTime) / 1000));
                                    runningStep.time = this.formatDuration(secs);
                                }
                            }
                        }
                    }, 500);

                    this.isStreaming = true;
                    this.scrollToBottom(true);

                    this.$nextTick(() => {
                        if (this.$refs.composerInput) {
                            this.$refs.composerInput.focus();
                        }
                    });

                    this.abortController = new AbortController();
                    const existingActionIds = new Set((this.aiActions || []).map(a => a.id));

                    try {
                        const response = await fetch(`/api/chats/${sessionId}/stream`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'text/event-stream',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ message: text }),
                            signal: this.abortController.signal
                        });

                        if (!response.ok) {
                            throw new Error('Chat service returned HTTP ' + response.status);
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        const sseParser = window.EchoChat.createSseParser();
                        let assistantText = '';
                        let streamDone = false;

                        while (!streamDone) {
                            const { done, value } = await reader.read();
                            if (done) break;

                            for (const payload of sseParser.push(decoder.decode(value, { stream: true }))) {
                                if (payload === '[DONE]') {
                                    streamDone = true;
                                    break;
                                }

                                try {
                                    const event = JSON.parse(payload);
                                    const target = this.messages[assistantIndex];

                                    if (event.type === 'tool_call') {
                                        this.handleToolCallEvent(target, event);
                                    } else if (event.type === 'tool_result') {
                                        this.handleToolResultEvent(target, event, sessionId);
                                    } else if (event.type === 'reasoning_delta' && event.delta) {
                                        if (!target.thinking) {
                                            target.thinking = '';
                                            target.thinkingOpen = true;
                                            target.thinkingStartedAt = Date.now();
                                        }
                                        target.thinking += event.delta;
                                        this.scrollToBottom();
                                    } else if (event.type === 'text_delta' && event.delta) {
                                        if (target.thinkingStartedAt && !target.thinkingMs) {
                                            target.thinkingMs = Math.max(1, Math.round((Date.now() - target.thinkingStartedAt) / 1000));
                                            target.thinkingOpen = false;
                                        }
                                        if (target.activity && target.activity.steps) {
                                            const analyzeStep = target.activity.steps.find(s => s.id === 'analyze' && s.status === 'running');
                                            if (analyzeStep) {
                                                analyzeStep.status = 'completed';
                                                analyzeStep.time = this.formatDuration(Math.max(1, Math.round((Date.now() - (analyzeStep.startTime || Date.now())) / 1000)));
                                            }
                                        }
                                        assistantText += event.delta;
                                        target.content = assistantText;
                                        this.scrollToBottom();
                                    }
                                } catch (e) {
                                    // Ignore non-json or delta parse errors
                                }
                            }
                        }
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            this.messages[assistantIndex].content += '\n\n*(Echo encountered an issue: ' + (err.message || 'connection failed') + ')*';
                        }
                    } finally {
                        if (this.streamingTimer) {
                            clearInterval(this.streamingTimer);
                            this.streamingTimer = null;
                        }
                        const target = this.messages[assistantIndex];
                        if (target) {
                            target.typing = false;
                            if (target.activity) {
                                target.activity.steps.forEach(s => {
                                    if (s.status === 'running') {
                                        s.status = 'completed';
                                        if (!s.time && s.startTime) {
                                            s.time = this.formatDuration(Math.max(1, Math.round((Date.now() - s.startTime) / 1000)));
                                        }
                                    }
                                });
                            }
                        }
                        this.isStreaming = false;
                        this.abortController = null;
                        await this.loadAiActions(sessionId);
                        if (target) {
                            (this.aiActions || []).forEach(a => {
                                if (!existingActionIds.has(a.id)) {
                                    target.actionIds = target.actionIds || [];
                                    if (!target.actionIds.includes(a.id)) {
                                        target.actionIds.push(a.id);
                                    }
                                }
                            });
                        }
                        this.associateActionsWithMessages();
                        if (target && target.activity) {
                            const targetActions = this.getActionsForMessage(target, assistantIndex);
                            if (targetActions.length > 0) {
                                target.activity.status = 'needs_approval';
                            } else {
                                target.activity.status = 'completed';
                                if (!target.activity.hasToolCalls && targetActions.length === 0) {
                                    target.activity = null;
                                }
                            }
                        }
                        this.scrollToBottom();
                    }
                },

                stopStreaming() {
                    this.stopSpeaking();
                    if (this.streamingTimer) {
                        clearInterval(this.streamingTimer);
                        this.streamingTimer = null;
                    }
                    if (this.abortController) {
                        this.abortController.abort();
                        this.isStreaming = false;
                    }
                },

                handleKeyDown(event) {
                    if (event.key === 'Enter') {
                        if (event.shiftKey) {
                            // Shift + Enter: create a newline and expand textarea height
                            this.$nextTick(() => this.autoGrowTextarea(event));
                            return;
                        }

                        if (event.isComposing) {
                            return;
                        }

                        // Plain Enter: submit the message without adding a newline
                        event.preventDefault();
                        this.sendMessage();
                    }
                },

                autoGrowTextarea(event) {
                    this.cancelAutoProceed();
                    const el = event?.target || event;
                    if (!el || !el.style) return;
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 180) + 'px';
                },

                handleScroll() {
                    const container = this.$refs.messageContainer;
                    if (!container) return;
                    const nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 80;
                    this.pinnedToBottom = nearBottom;
                    if (nearBottom) {
                        this.showNewMessages = false;
                    }
                },

                scrollToBottom(force = false) {
                    if (!force && !this.pinnedToBottom) {
                        this.showNewMessages = true;
                        return;
                    }
                    this.$nextTick(() => {
                        const container = this.$refs.messageContainer;
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                            this.showNewMessages = false;
                        }
                    });
                },

                copyText(text) {
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text);
                    }
                },

                toggleVoice() {
                    if (this.isStreaming) return;

                    const SpeechRecognition = typeof window !== 'undefined'
                        ? (window.SpeechRecognition || window.webkitSpeechRecognition)
                        : null;

                    if (!SpeechRecognition) {
                        this.voiceError = 'Voice dictation requires Google Chrome, Microsoft Edge, Safari, or Opera with Web Speech support.';
                        return;
                    }

                    if (this.isListening) {
                        this.stopVoice();
                        return;
                    }

                    this.startVoice();
                },

                async startVoice() {
                    this.voiceError = null;
                    const SpeechRecognition = typeof window !== 'undefined'
                        ? (window.SpeechRecognition || window.webkitSpeechRecognition)
                        : null;

                    if (!SpeechRecognition) {
                        this.voiceError = 'Voice dictation is not supported in this browser.';
                        return;
                    }

                    // Microphone access is only grantable on secure origins.
                    if (typeof window !== 'undefined' && window.isSecureContext === false) {
                        this.voiceError = 'Microphone access needs a secure (HTTPS) page. Please open the site over HTTPS, then tap the mic again.';
                        return;
                    }

                    // Ask the browser for microphone permission first. This shows the
                    // permission prompt (and confirms a mic exists) before speech
                    // recognition starts — without it, browsers just fire a
                    // confusing `not-allowed` error and dictation never begins.
                    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            stream.getTracks().forEach((track) => track.stop());
                        } catch (err) {
                            const name = err && err.name ? err.name : '';

                            if (name === 'NotAllowedError' || name === 'SecurityError') {
                                this.voiceError = 'Microphone access was denied. Tap the lock/tune icon in the address bar → Permissions → Microphone → Allow (on Android, also allow microphone access for the browser in system settings), then tap the mic again.';
                            } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                                this.voiceError = 'No microphone was found on this device. Connect or enable a microphone, then try again.';
                            } else if (name === 'NotReadableError' || name === 'AbortError') {
                                this.voiceError = 'Your microphone is busy in another app or tab. Close it and try again.';
                            } else {
                                this.voiceError = 'Could not access the microphone. Check your browser and system microphone permissions, then try again.';
                            }

                            if (this.voiceErrorTimer) {
                                clearTimeout(this.voiceErrorTimer);
                            }
                            this.voiceErrorTimer = setTimeout(() => { this.voiceError = null; }, 10000);
                            this.isListening = false;
                            return;
                        }
                    }

                    try {
                        const rec = new SpeechRecognition();
                        rec.continuous = false;
                        rec.interimResults = true;
                        rec.lang = 'en-US';

                        let baseText = (this.inputMessage || '').trim();

                        rec.onstart = () => {
                            this.isListening = true;
                            this.voiceError = null;
                            baseText = (this.inputMessage || '').trim();
                        };

                        rec.onresult = (event) => {
                            let interim = '';
                            let finalTranscript = '';

                            for (let i = event.resultIndex; i < event.results.length; ++i) {
                                if (event.results[i].isFinal) {
                                    finalTranscript += event.results[i][0].transcript;
                                } else {
                                    interim += event.results[i][0].transcript;
                                }
                            }

                            const spoken = (finalTranscript || interim).trim();
                            if (spoken) {
                                this.inputMessage = baseText ? (baseText + ' ' + spoken) : spoken;
                                this.$nextTick(() => {
                                    if (this.$refs.composerInput) {
                                        this.autoGrowTextarea({ target: this.$refs.composerInput });
                                    }
                                    if (this.$refs.welcomeComposerInput) {
                                        this.autoGrowTextarea({ target: this.$refs.welcomeComposerInput });
                                    }
                                });
                            }
                        };

                        rec.onerror = (event) => {
                            console.warn('Speech recognition error:', event.error);
                            if (event.error === 'no-speech') {
                                this.isListening = false;
                                return;
                            }

                            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                                this.voiceError = 'Microphone access was denied or is unavailable (private/incognito windows can disable dictation). Allow microphone access for this site — address bar lock icon → Permissions → Microphone → Allow — then tap the mic again.';
                            } else if (event.error === 'network') {
                                this.voiceError = 'Speech service network error. Please verify your internet connection.';
                            } else if (event.error === 'audio-capture') {
                                this.voiceError = 'No audio captured. Make sure a working microphone is connected and enabled on this device, then try again.';
                            } else {
                                this.voiceError = 'Dictation error: ' + event.error;
                            }

                            if (this.voiceErrorTimer) {
                                clearTimeout(this.voiceErrorTimer);
                            }
                            this.voiceErrorTimer = setTimeout(() => { this.voiceError = null; }, 8000);
                            this.isListening = false;
                        };

                        rec.onend = () => {
                            this.isListening = false;
                        };

                        this.voiceRecognition = rec;
                        rec.start();
                    } catch (e) {
                        console.error('Speech recognition start failed:', e);
                        this.voiceError = 'Could not start microphone dictation.';
                        this.isListening = false;
                    }
                },

                stopVoice() {
                    if (this.voiceRecognition) {
                        try {
                            this.voiceRecognition.stop();
                        } catch (e) {}
                    }
                    this.isListening = false;
                },

                speakText(text) {
                    if (typeof window === 'undefined' || !window.speechSynthesis) return;

                    if (this.isSpeaking && this.speakingContent === text) {
                        window.speechSynthesis.cancel();
                        this.isSpeaking = false;
                        this.speakingContent = null;
                        return;
                    }

                    const clean = (text || '')
                        .replace(/```[\s\S]*?```/g, 'Code block omitted.')
                        .replace(/`([^`]+)`/g, '$1')
                        .replace(/\*\*([^*]+)\*\*/g, '$1')
                        .replace(/\*([^*]+)\*/g, '$1')
                        .replace(/#+\s+/g, '')
                        .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
                        .replace(/[|>\-_~]/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();

                    if (!clean) return;

                    const utterance = new SpeechSynthesisUtterance(clean);
                    utterance.rate = 1.05;
                    utterance.pitch = 1.0;

                    utterance.onstart = () => {
                        this.isSpeaking = true;
                        this.speakingContent = text;
                    };
                    utterance.onend = () => {
                        this.isSpeaking = false;
                        this.speakingContent = null;
                    };
                    utterance.onerror = () => {
                        this.isSpeaking = false;
                        this.speakingContent = null;
                    };

                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(utterance);
                },

                stopSpeaking() {
                    if (typeof window !== 'undefined' && window.speechSynthesis) {
                        window.speechSynthesis.cancel();
                        this.isSpeaking = false;
                        this.speakingContent = null;
                    }
                },

                // Rendering lives in /js/ai-chat-format.js (window.EchoChat) so
                // it can be unit-tested; keep this thin delegate because the
                // templates bind x-html="formatMarkdown(msg.content)".
                formatMarkdown(raw) {
                    return window.EchoChat ? window.EchoChat.formatMarkdown(raw) : '';
                }
            }));
        }

        if (window.Alpine) {
            registerAdminAiChat();
        } else {
            document.addEventListener('alpine:init', registerAdminAiChat);
        }
        document.addEventListener('livewire:init', registerAdminAiChat);
        document.addEventListener('livewire:navigated', registerAdminAiChat);
    </script>
