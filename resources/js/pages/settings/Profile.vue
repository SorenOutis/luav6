<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Camera, Hash, LogOut, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SectionSelectionModal from '@/components/SectionSelectionModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';
import { edit } from '@/routes/profile';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    userSections: Array<{ id: number; name: string }>;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const handleFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const coverInput = ref<HTMLInputElement | null>(null);
const coverPreviewUrl = ref<string | null>(null);

const handleCoverChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        coverPreviewUrl.value = URL.createObjectURL(file);
    }
};

const triggerCoverInput = () => {
    coverInput.value?.click();
};

// ── Section join modal state ────────────────────────────────────────
const showSectionModal = ref(false);

const openSectionModal = () => {
    showSectionModal.value = true;
};

const closeSectionModal = () => {
    showSectionModal.value = false;
};

const leaveSection = (sectionId: number) => {
    const remaining = props.userSections
        .filter((s) => s.id !== sectionId)
        .map((s) => s.id);

    router.patch('/profile/section', {
        section_ids: remaining,
        section_passwords: {},
    }, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['userSections'] });
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Profile information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    enc-type="multipart/form-data"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <!-- Avatar Upload Section -->
                    <div
                        class="flex flex-col items-center gap-6 border-b border-border/40 pb-6 sm:flex-row"
                    >
                        <div class="group relative">
                            <Avatar
                                class="h-24 w-24 border-2 border-border/50 transition-colors duration-300 group-hover:border-primary/50"
                            >
                                <AvatarImage
                                    v-if="previewUrl || user.avatar"
                                    :src="previewUrl || user.avatar"
                                    :alt="user.name"
                                    class="object-cover"
                                />
                                <AvatarFallback
                                    class="bg-muted text-xl font-bold text-foreground"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <button
                                type="button"
                                @click="triggerFileInput"
                                class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                            >
                                <Camera class="h-6 w-6" />
                            </button>
                        </div>

                        <!-- Avatar Upload Section -->
                        <div class="space-y-2 text-center sm:text-left">
                            <h4 class="text-sm font-bold">Profile Picture</h4>
                            <p class="text-xs text-muted-foreground">
                                Recommend: Square PNG, JPG, or GIF, max 10MB.
                            </p>
                            <div
                                class="flex items-center justify-center gap-2 sm:justify-start"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="triggerFileInput"
                                >
                                    Change Photo
                                </Button>
                                <input
                                    type="file"
                                    ref="fileInput"
                                    name="avatar"
                                    class="hidden"
                                    accept="image/*"
                                    @change="handleFileChange"
                                />
                            </div>
                            <InputError :message="errors.avatar" />
                        </div>
                    </div>

                    <!-- Cover Photo Upload Section -->
                    <div
                        class="mt-6 flex w-full flex-col gap-4 border-b border-border/40 pb-6"
                    >
                        <div
                            class="flex flex-col items-center justify-between gap-4 sm:flex-row"
                        >
                            <div class="space-y-1 text-center sm:text-left">
                                <h4 class="text-sm font-bold">Cover Photo</h4>
                                <p class="text-xs text-muted-foreground">
                                    Recommend: Landscape PNG, JPG, or GIF, max
                                    10MB.
                                </p>
                            </div>
                            <div
                                class="flex flex-col items-center gap-2 sm:items-end"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="triggerCoverInput"
                                >
                                    Change Cover
                                </Button>
                                <input
                                    type="file"
                                    ref="coverInput"
                                    name="cover_photo"
                                    class="hidden"
                                    accept="image/*"
                                    @change="handleCoverChange"
                                />
                            </div>
                        </div>
                        <div
                            class="group relative flex h-32 w-full items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-border/50 bg-muted sm:h-48"
                            @click="triggerCoverInput"
                        >
                            <!-- Decorative: the surrounding button carries the accessible label. -->
                            <img
                                v-if="coverPreviewUrl || user.cover_photo"
                                :src="coverPreviewUrl || user.cover_photo"
                                alt=""
                                class="h-full w-full cursor-pointer object-cover transition-opacity group-hover:opacity-80"
                            />
                            <div
                                v-else
                                class="flex cursor-pointer flex-col items-center text-muted-foreground transition-colors group-hover:text-primary"
                            >
                                <Camera class="mb-2 h-8 w-8 opacity-50" />
                                <span class="text-xs font-medium"
                                    >Click to upload cover photo</span
                                >
                            </div>
                        </div>
                        <InputError :message="errors.cover_photo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <!-- ── Section Management ── -->
            <div class="flex flex-col space-y-4">
                <Heading
                    variant="small"
                    title="Your Sections"
                    description="Manage which sections you belong to."
                />

                <div class="space-y-3">
                    <div
                        v-if="props.userSections.length === 0"
                        class="rounded-xl border border-dashed border-border/50 bg-muted/30 p-6 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            You haven&apos;t joined any sections yet.
                        </p>
                    </div>

                    <div
                        v-for="section in props.userSections"
                        :key="section.id"
                        class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/20 px-4 py-3 transition-colors hover:bg-muted/40"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <Hash class="h-4 w-4" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ section.name }}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 text-xs text-muted-foreground hover:text-destructive"
                            @click="leaveSection(section.id)"
                        >
                            <LogOut class="mr-1 h-3 w-3" />
                            Leave
                        </Button>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-2 w-full"
                        @click="openSectionModal"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Join a Section
                    </Button>
                </div>
            </div>

            <DeleteUser />
        </SettingsLayout>

        <SectionSelectionModal
            :show="showSectionModal"
            @close="closeSectionModal"
        />
    </AppLayout>
</template>
