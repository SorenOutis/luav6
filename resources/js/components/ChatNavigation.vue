<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, MessageSquareText, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { show as chatsShow } from '@/routes/chats';

interface ChatSession {
    id: number;
    title: string;
    messageCount?: number;
    updatedAtHuman?: string;
}

const props = defineProps<{
    sessions: ChatSession[];
    activeSessionId?: number | null;
    creating?: boolean;
    hasMore?: boolean;
    loadingMore?: boolean;
}>();

const emit = defineEmits<{
    create: [];
    delete: [session: ChatSession];
    loadMore: [];
}>();

const isOpen = ref(false);
const visibleSessions = computed(() => props.sessions);
</script>

<template>
    <SidebarGroup class="px-2 py-0 group-data-[collapsible=icon]:hidden">
        <Collapsible v-model:open="isOpen" class="group/chats">
            <SidebarMenu>
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            data-testid="chat-history-toggle"
                            tooltip="Chats"
                            :is-active="true"
                            aria-label="Toggle chat history"
                            aria-controls="chat-history"
                            :aria-expanded="isOpen"
                        >
                            <MessageSquareText />
                            <span>Chats</span>
                            <ChevronDown
                                class="ml-auto transition-transform duration-200 group-data-[state=closed]/chats:-rotate-90"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>

                    <SidebarMenuAction
                        data-testid="chat-new"
                        title="New chat"
                        :disabled="creating"
                        @click.stop="emit('create')"
                    >
                        <Plus />
                        <span class="sr-only">New chat</span>
                    </SidebarMenuAction>
                </SidebarMenuItem>
            </SidebarMenu>

            <CollapsibleContent id="chat-history" data-testid="chat-history">
                <SidebarGroupContent class="pt-1 pl-2">
                    <SidebarMenu
                        v-if="visibleSessions.length"
                        data-lenis-prevent
                        @wheel.stop
                        class="max-h-72 overflow-y-auto pr-1"
                    >
                        <SidebarMenuItem
                            v-for="session in visibleSessions"
                            :key="session.id"
                            class="group/session"
                        >
                            <SidebarMenuButton
                                as-child
                                size="sm"
                                :is-active="activeSessionId === session.id"
                                :tooltip="session.title"
                            >
                                <Link
                                    :data-testid="`chat-session-${session.id}`"
                                    :href="
                                        chatsShow({ session: session.id }).url
                                    "
                                >
                                    <MessageSquareText />
                                    <span>{{ session.title }}</span>
                                    <span
                                        v-if="session.messageCount"
                                        class="ml-auto text-[10px] text-muted-foreground"
                                    >
                                        {{ session.messageCount }}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                            <SidebarMenuAction
                                show-on-hover
                                title="Delete chat"
                                @click.stop.prevent="emit('delete', session)"
                            >
                                <Trash2 />
                                <span class="sr-only">Delete chat</span>
                            </SidebarMenuAction>
                        </SidebarMenuItem>
                    </SidebarMenu>

                    <p
                        v-else
                        class="px-2 py-2 text-xs leading-relaxed text-muted-foreground"
                    >
                        Start a chat and it will appear here.
                    </p>

                    <button
                        v-if="hasMore"
                        type="button"
                        class="mt-1 w-full rounded-lg px-2 py-2 text-left text-[11px] font-medium text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground disabled:opacity-50"
                        :disabled="loadingMore"
                        @click="emit('loadMore')"
                    >
                        {{ loadingMore ? 'Loading…' : 'Load older chats' }}
                    </button>
                </SidebarGroupContent>
            </CollapsibleContent>
        </Collapsible>
    </SidebarGroup>
</template>
