<x-filament-panels::page>
    <div class="space-y-6" x-data="{
        copiedId: null,
        copyText(text, id) {
            navigator.clipboard.writeText(text);
            this.copiedId = id;
            setTimeout(() => {
                if (this.copiedId === id) this.copiedId = null;
            }, 2000);
        }
    }">
        {{-- Intro banner --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        Community Lounge Access Controls
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        The Community Lounge is private and invite-only. Search any enrolled student in this workspace to view or generate their personal invite link. Regenerating a link permanently revokes the previous token.
                    </p>
                </div>
            </div>
        </div>

        {{-- Student Search Box --}}
        <div class="relative rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <label for="lounge-student-search" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                Search Student by Name or Email
            </label>

            <div class="relative">
                <input
                    id="lounge-student-search"
                    type="text"
                    wire:model.live.debounce.250ms="searchQuery"
                    placeholder="Type student name or email to generate / locate invite link..."
                    class="block w-full rounded-xl border-gray-300 bg-white py-2.5 pe-10 ps-4 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-amber-400"
                    autocomplete="off"
                />

                <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-gray-400">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5" />
                </div>
            </div>

            {{-- Instant Search Suggestions Dropdown --}}
            @if (! empty($searchQuery) && $this->searchResults->isNotEmpty() && ! $this->selectedStudent)
                <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white shadow-lg dark:divide-white/5 dark:border-white/10 dark:bg-gray-800">
                    @foreach ($this->searchResults as $match)
                        <button
                            type="button"
                            wire:click="selectStudent({{ $match->id }})"
                            class="flex w-full items-center justify-between px-4 py-3 text-start transition hover:bg-amber-500/5 dark:hover:bg-white/5"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-500/10 text-xs font-bold text-amber-600 dark:text-amber-400">
                                    {{ strtoupper(substr($match->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $match->name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $match->email }}
                                    </div>
                                </div>
                            </div>

                            <span class="inline-flex items-center gap-1 rounded-lg bg-amber-500/10 px-2 py-1 text-xs font-semibold text-amber-600 dark:text-amber-300">
                                Generate / View Link &rarr;
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Active Selected Student Card --}}
        @if ($student = $this->selectedStudent)
            @php
                $invite = $student->communityInvite;
                $inviteUrl = $this->getInviteUrl($student);
                $isAccepted = $invite?->isAccepted() ?? false;
            @endphp
            <div class="rounded-2xl border-2 border-amber-500/40 bg-white p-6 shadow-md dark:border-amber-500/40 dark:bg-gray-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/15 text-lg font-bold text-amber-600 shadow-sm dark:text-amber-400">
                            {{ strtoupper(substr($student->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold text-gray-950 dark:text-white">
                                    {{ $student->name }}
                                </h3>
                                @if ($isAccepted)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                        <x-filament::icon icon="heroicon-s-check-circle" class="h-3.5 w-3.5" />
                                        Access Unlocked
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                        <x-filament::icon icon="heroicon-s-clock" class="h-3.5 w-3.5" />
                                        Pending Activation
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $student->email }}
                                @if ($section = $student->sections->first())
                                    &bull; Section: <strong class="text-gray-700 dark:text-gray-300">{{ $section->name }}</strong>
                                @endif
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeSelectedStudent"
                        class="self-end text-xs font-semibold text-gray-500 hover:text-gray-800 sm:self-auto dark:text-gray-400 dark:hover:text-white"
                    >
                        &times; Close
                    </button>
                </div>

                {{-- Invite Link Copy Box --}}
                <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/5 dark:bg-white/5">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                        Unique Single-Student Invite Link
                    </label>

                    <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input
                            type="text"
                            readonly
                            value="{{ $inviteUrl }}"
                            class="block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-xs font-mono text-gray-900 shadow-sm outline-none dark:border-white/10 dark:bg-black/40 dark:text-zinc-200"
                        />

                        <button
                            type="button"
                            @click="copyText('{{ $inviteUrl }}', 'selected-{{ $student->id }}')"
                            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-gray-950 shadow-sm transition hover:bg-amber-400"
                        >
                            <span x-show="copiedId !== 'selected-{{ $student->id }}'" class="inline-flex items-center gap-1">
                                <x-filament::icon icon="heroicon-o-clipboard-document" class="h-4 w-4" />
                                Copy Link
                            </span>
                            <span x-show="copiedId === 'selected-{{ $student->id }}'" x-cloak class="inline-flex items-center gap-1 text-emerald-950">
                                <x-filament::icon icon="heroicon-s-check" class="h-4 w-4" />
                                Copied!
                            </span>
                        </button>
                    </div>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        @if ($invite?->accepted_at)
                            Redeemed on {{ $invite->accepted_at->format('M d, Y h:i A') }}.
                        @else
                            Not yet activated by student. Share this link directly with {{ $student->name }}.
                        @endif
                    </p>
                </div>

                {{-- Action Toolbar --}}
                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-white/5">
                    <button
                        type="button"
                        wire:click="regenerateInvite({{ $student->id }})"
                        wire:confirm="Regenerating will permanently revoke the current link. Proceed?"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-path" class="h-3.5 w-3.5" />
                        Regenerate Fresh Link
                    </button>

                    @if (! $isAccepted)
                        <button
                            type="button"
                            wire:click="grantAccess({{ $student->id }})"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500"
                        >
                            <x-filament::icon icon="heroicon-o-check" class="h-3.5 w-3.5" />
                            Grant Instant Access
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="revokeAccess({{ $student->id }})"
                            wire:confirm="This will lock the lounge for {{ $student->name }}. Proceed?"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/60"
                        >
                            <x-filament::icon icon="heroicon-o-lock-closed" class="h-3.5 w-3.5" />
                            Revoke / Lock Access
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Workspace Students Roster Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/5">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                        Workspace Students Roster
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Quick overview of all students and their community lounge access status.
                    </p>
                </div>

                {{-- Status Filter Tabs --}}
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-white/10 dark:bg-white/5">
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'all')"
                        class="rounded-md px-3 py-1 text-xs font-semibold transition {{ $statusFilter === 'all' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'accepted')"
                        class="rounded-md px-3 py-1 text-xs font-semibold transition {{ $statusFilter === 'accepted' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                    >
                        Unlocked
                    </button>
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'pending')"
                        class="rounded-md px-3 py-1 text-xs font-semibold transition {{ $statusFilter === 'pending' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                    >
                        Pending
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 text-start">Student</th>
                            <th class="px-5 py-3 text-start">Section</th>
                            <th class="px-5 py-3 text-start">Lounge Status</th>
                            <th class="px-5 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($this->studentsRoster as $rowStudent)
                            @php
                                $rowInvite = $rowStudent->communityInvite;
                                $rowAccepted = $rowInvite?->isAccepted() ?? false;
                                $rowUrl = $rowInvite ? url("/community/join/{$rowInvite->token}") : null;
                            @endphp
                            <tr class="transition hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/10 text-xs font-bold text-amber-600 dark:text-amber-400">
                                            {{ strtoupper(substr($rowStudent->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-950 dark:text-white">
                                                {{ $rowStudent->name }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $rowStudent->email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-xs text-gray-600 dark:text-gray-300">
                                    {{ $rowStudent->sections->first()?->name ?? '-' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($rowAccepted)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            <x-filament::icon icon="heroicon-s-check-circle" class="h-3 w-3" />
                                            Unlocked
                                        </span>
                                    @elseif ($rowInvite)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                            <x-filament::icon icon="heroicon-s-clock" class="h-3 w-3" />
                                            Pending
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-500/10 px-2 py-0.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                            No link yet
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-end">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="selectStudent({{ $rowStudent->id }})"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            <x-filament::icon icon="heroicon-o-eye" class="h-3.5 w-3.5" />
                                            Manage
                                        </button>

                                        @if ($rowUrl)
                                            <button
                                                type="button"
                                                @click="copyText('{{ $rowUrl }}', 'row-{{ $rowStudent->id }}')"
                                                class="inline-flex items-center gap-1 rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-500/20 dark:text-amber-300"
                                            >
                                                <span x-show="copiedId !== 'row-{{ $rowStudent->id }}'" class="inline-flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-clipboard-document" class="h-3.5 w-3.5" />
                                                    Copy
                                                </span>
                                                <span x-show="copiedId === 'row-{{ $rowStudent->id }}'" x-cloak class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                    <x-filament::icon icon="heroicon-s-check" class="h-3.5 w-3.5" />
                                                    Copied!
                                                </span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                                    No students found for this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
