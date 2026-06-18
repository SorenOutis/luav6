<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

defineProps<Props>();

const showLogoutDialog = ref(false);
const loggingOut = ref(false);

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

    <Dialog v-model:open="showLogoutDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Log out of your account?</DialogTitle>
                <DialogDescription>
                    You'll need to sign in again to access your dashboard,
                    progress, and learning map.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
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
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
