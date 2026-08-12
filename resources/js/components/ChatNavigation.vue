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
    SidebarGroupAction,
    SidebarGroupContent,
    SidebarGroupLabel,
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
}>();

const emit = defineEmits<{
    create: [];
    delete: [session: ChatSession];
}>();

const isOpen = ref(true);
const visibleSessions = computed(() => props.sessions.slice(0, 12));
const hiddenCount = computed(() =>
    Math.max(0, props.sessions.length - visibleSessions.value.length),
);
</script>

<template>
    <SidebarGroup class="group-data-[collapsible=icon]:hidden">
        <Collapsible v-model:open="isOpen" class="group/chats">
            <SidebarGroupLabel as-child>
                <div class="flex w-full items-center">
                    <CollapsibleTrigger
                        class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-md text-left outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                    >
                        <MessageSquareText class="size-3.5 shrink-0" />
                        <span class="truncate">Recent chats</span>
                        <ChevronDown
                            class="ml-auto size-3.5 transition-transform duration-200 group-data-[state=closed]/chats:-rotate-90"
                        />
                    </CollapsibleTrigger>
                </div>
            </SidebarGroupLabel>

            <SidebarGroupAction
                as="button"
                title="New chat"
                :disabled="creating"
                @click="emit('create')"
            >
                <Plus />
                <span class="sr-only">New chat</span>
            </SidebarGroupAction>

            <CollapsibleContent>
                <SidebarGroupContent class="pt-1">
                    <SidebarMenu v-if="visibleSessions.length">
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
                                @click.prevent="emit('delete', session)"
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

                    <p
                        v-if="hiddenCount"
                        class="px-2 pt-2 text-[11px] text-muted-foreground"
                    >
                        {{ hiddenCount }} more in your history
                    </p>
                </SidebarGroupContent>
            </CollapsibleContent>
        </Collapsible>
    </SidebarGroup>
</template>
