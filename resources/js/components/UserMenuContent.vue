<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Check, LogOut, Settings, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const props = defineProps<Props>();

const page = usePage();
const workspaceState = computed(
    () =>
        (page.props.workspace as {
            isInspecting?: boolean;
            available?: Array<{
                id: string;
                name: string;
                role: string;
                isCurrent: boolean;
            }>;
        }) ?? {},
);
const workspaces = computed(() => workspaceState.value.available ?? []);
const switchingWorkspace = ref<string | null>(null);
const showLogoutDialog = ref(false);
const loggingOut = ref(false);

const activateWorkspace = (workspaceId: string) => {
    if (switchingWorkspace.value) return;
    switchingWorkspace.value = workspaceId;
    const action = props.user.is_super_admin ? 'inspect' : 'activate';
    router.post(
        `/workspaces/${workspaceId}/${action}`,
        {},
        {
            preserveScroll: false,
            onFinish: () => (switchingWorkspace.value = null),
        },
    );
};

const stopInspecting = () => {
    router.delete('/workspaces/inspection');
};

const openLogoutDialog = (event: Event) => {
    event.preventDefault();
    showLogoutDialog.value = true;
};

const confirmLogout = () => {
    loggingOut.value = true;
    sessionStorage.setItem('logged_out', 'true');
    router.post(
        logout(),
        {},
        {
            onFinish: () => {
                loggingOut.value = false;
                showLogoutDialog.value = false;
            },
        },
    );
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup
        v-if="workspaces.length > 1 || workspaceState.isInspecting"
    >
        <DropdownMenuLabel class="px-2 py-1 text-xs text-muted-foreground">
            Workspace
        </DropdownMenuLabel>
        <DropdownMenuItem
            v-for="workspace in workspaces"
            :key="workspace.id"
            :disabled="
                (workspace.isCurrent &&
                    (!user.is_super_admin || workspaceState.isInspecting)) ||
                switchingWorkspace !== null
            "
            @select="activateWorkspace(workspace.id)"
        >
            <Building2 class="mr-2 h-4 w-4" />
            <span class="min-w-0 flex-1 truncate">{{ workspace.name }}</span>
            <Check v-if="workspace.isCurrent" class="ml-2 h-4 w-4" />
        </DropdownMenuItem>
        <DropdownMenuItem
            v-if="workspaceState.isInspecting"
            class="text-destructive"
            @select="stopInspecting"
        >
            <XCircle class="mr-2 h-4 w-4" />
            Exit workspace inspection
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator
        v-if="workspaces.length > 1 || workspaceState.isInspecting"
    />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true" @select="openLogoutDialog">
        <button
            class="flex w-full cursor-pointer items-center px-2 py-1.5 text-sm"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </button>
    </DropdownMenuItem>

    <ResponsiveModal
        :open="showLogoutDialog"
        title="Log out of your account?"
        description="You'll need to sign in again to access your dashboard, progress, and learning map."
        @close="showLogoutDialog = false"
    >
        <template #footer>
            <Button
                variant="outline"
                :disabled="loggingOut"
                @click="showLogoutDialog = false"
            >
                Cancel
            </Button>
            <Button
                variant="destructive"
                :disabled="loggingOut"
                @click="confirmLogout"
                data-test="logout-confirm-button"
            >
                <LogOut class="mr-2 h-4 w-4" />
                {{ loggingOut ? 'Logging out...' : 'Log out' }}
            </Button>
        </template>
    </ResponsiveModal>
</template>
