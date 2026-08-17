<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Camera, Crop, Hash, Loader2, LogOut, Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import CoverPhotoCropper from '@/components/CoverPhotoCropper.vue';
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
import { compressImage, setInputFile } from '@/lib/image-compression';
import { withForm } from '@/lib/route-helpers';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

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
const avatarName = ref<string | null>(null);
const isCompressingAvatar = ref(false);

const handleFileChange = async (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    avatarName.value = file.name;
    isCompressingAvatar.value = true;

    try {
        // A phone camera shot is far larger than the 96px–160px this is ever
        // drawn at. Shrink it before it is previewed or uploaded.
        const optimised = await compressImage(file, {
            maxSize: 512,
            quality: 0.86,
        });

        if (optimised !== file) {
            setInputFile(input, optimised);
        }

        // Each pick allocates a blob URL; without revoking the previous one
        // the old bitmap stays pinned in memory. On a low-RAM phone a few
        // re-picks of 8–12MP shots is enough to get the tab killed.
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
        }

        previewUrl.value = URL.createObjectURL(optimised);
        avatarName.value = optimised.name;
    } finally {
        isCompressingAvatar.value = false;
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const coverInput = ref<HTMLInputElement | null>(null);
const coverPreviewUrl = ref<string | null>(null);
const coverFileToCrop = ref<File | null>(null);

const handleCoverChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        // Open the cropper first — the input is only populated with the
        // framed result once the user confirms their positioning.
        coverFileToCrop.value = file;
    }
};

/**
 * Swap the cropped result into the real file input so the multipart form
 * uploads the framed image rather than the original upload.
 */
const applyCroppedCover = (file: File, previewUrl: string) => {
    if (coverInput.value) {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        coverInput.value.files = transfer.files;
    }

    if (coverPreviewUrl.value) {
        URL.revokeObjectURL(coverPreviewUrl.value);
    }

    coverPreviewUrl.value = previewUrl;
    coverFileToCrop.value = null;
};

const cancelCoverCrop = () => {
    coverFileToCrop.value = null;

    // Clear the pending selection so re-picking the same file still fires
    // a change event.
    if (coverInput.value && !coverPreviewUrl.value) {
        coverInput.value.value = '';
    }
};

const triggerCoverInput = () => {
    coverInput.value?.click();
};

const reopenCoverCropper = () => {
    const file = coverInput.value?.files?.[0];

    if (file) {
        coverFileToCrop.value = file;
        return;
    }

    triggerCoverInput();
};

// ── Section join modal state ────────────────────────────────────────
const showSectionModal = ref(false);

const openSectionModal = () => {
    showSectionModal.value = true;
};

const closeSectionModal = () => {
    showSectionModal.value = false;
};

// Blob URLs outlive the component unless released explicitly.
onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    if (coverPreviewUrl.value) URL.revokeObjectURL(coverPreviewUrl.value);
});

const leaveSection = (sectionId: number) => {
    const remaining = props.userSections
        .filter((s) => s.id !== sectionId)
        .map((s) => s.id);

    router.patch(
        '/profile/section',
        {
            section_ids: remaining,
            section_passwords: {},
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['userSections'] });
            },
        },
    );
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
                    v-bind="withForm(ProfileController.update).form()"
                    class="space-y-6"
                    enc-type="multipart/form-data"
                    v-slot="{
                        errors,
                        processing,
                        progress,
                        recentlySuccessful,
                    }"
                >
                    <!-- Avatar Upload Section -->
                    <div
                        class="flex flex-col items-center gap-6 border-b border-border/40 pb-6 sm:flex-row"
                    >
                        <div class="group relative shrink-0">
                            <Avatar
                                class="h-24 w-24 border-2 border-border/50 transition-colors duration-300 group-hover:border-primary/50"
                            >
                                <AvatarImage
                                    v-if="previewUrl || user.avatar"
                                    :src="previewUrl || user.avatar || ''"
                                    :alt="user.name"
                                    class="object-cover"
                                />
                                <AvatarFallback
                                    class="bg-muted text-xl font-bold text-foreground"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <!-- The dimming overlay is hover-only, so on a
                                 touch screen it never appears; the badge below
                                 is the always-visible affordance there. -->
                            <button
                                type="button"
                                aria-label="Change profile picture"
                                @click="triggerFileInput"
                                class="absolute inset-0 hidden items-center justify-center rounded-full bg-black/40 text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100 sm:flex"
                            >
                                <Camera class="h-6 w-6" />
                            </button>

                            <button
                                type="button"
                                aria-label="Change profile picture"
                                @click="triggerFileInput"
                                class="absolute right-0 bottom-0 flex h-9 w-9 items-center justify-center rounded-full border-2 border-background bg-primary text-primary-foreground shadow-sm active:scale-95 sm:hidden"
                            >
                                <Camera class="h-4 w-4" />
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
                                <!-- `accept` is restricted to the formats the
                                     backend validates, so the picker cannot
                                     offer a HEIC/AVIF file that would only be
                                     rejected after a slow mobile upload. -->
                                <input
                                    type="file"
                                    ref="fileInput"
                                    name="avatar"
                                    class="sr-only"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    @change="handleFileChange"
                                />
                            </div>
                            <p
                                v-if="isCompressingAvatar"
                                class="flex items-center justify-center gap-1.5 text-xs text-muted-foreground sm:justify-start"
                            >
                                <Loader2 class="h-3 w-3 animate-spin" />
                                Optimising image…
                            </p>
                            <p
                                v-else-if="avatarName"
                                class="truncate text-xs text-muted-foreground"
                            >
                                Selected: {{ avatarName }} — press Save to
                                apply.
                            </p>
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
                                    10MB. You&apos;ll be able to position it
                                    before saving.
                                </p>
                            </div>
                            <div
                                class="flex flex-col items-center gap-2 sm:flex-row sm:items-end"
                            >
                                <Button
                                    v-if="coverPreviewUrl"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="reopenCoverCropper"
                                >
                                    <Crop class="mr-1.5 h-3.5 w-3.5" />
                                    Reposition
                                </Button>
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
                                    class="sr-only"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    @change="handleCoverChange"
                                />
                            </div>
                        </div>

                        <!-- Preview rendered at the same 3:1 frame the profile
                             banner uses, so what you see is what gets saved. -->
                        <div
                            class="group relative flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-2xl border border-border/50 bg-muted"
                            style="aspect-ratio: 3"
                            @click="triggerCoverInput"
                        >
                            <!-- Decorative: the surrounding button carries the accessible label. -->
                            <img
                                v-if="coverPreviewUrl || user.cover_photo"
                                :src="
                                    coverPreviewUrl ||
                                    String(user.cover_photo ?? '')
                                "
                                alt=""
                                class="h-full w-full object-cover transition-opacity group-hover:opacity-80"
                            />
                            <div
                                v-else
                                class="flex flex-col items-center text-muted-foreground transition-colors group-hover:text-primary"
                            >
                                <Camera class="mb-2 h-8 w-8 opacity-50" />
                                <span class="text-xs font-medium"
                                    >Click to upload cover photo</span
                                >
                            </div>

                            <!-- Avatar ghost mirrors the profile page overlap -->
                            <div
                                v-if="coverPreviewUrl || user.cover_photo"
                                class="pointer-events-none absolute bottom-0 left-4 aspect-square h-1/2 translate-y-1/3 rounded-full border-4 border-background/80 bg-background/20 sm:left-6"
                                aria-hidden="true"
                            ></div>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            This is how your cover will appear on your profile.
                        </p>
                        <InputError :message="errors.cover_photo" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="first_name">First name</Label>
                            <Input
                                id="first_name"
                                class="mt-1 block w-full"
                                name="first_name"
                                :default-value="user.first_name ?? ''"
                                required
                                autocomplete="given-name"
                                placeholder="First name"
                            />
                            <InputError
                                class="mt-2"
                                :message="errors.first_name"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                class="mt-1 block w-full"
                                name="last_name"
                                :default-value="user.last_name ?? ''"
                                required
                                autocomplete="family-name"
                                placeholder="Last name"
                            />
                            <InputError
                                class="mt-2"
                                :message="errors.last_name"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="middle_name">Middle name (optional)</Label>
                        <Input
                            id="middle_name"
                            class="mt-1 block w-full"
                            name="middle_name"
                            :default-value="user.middle_name ?? ''"
                            autocomplete="additional-name"
                            placeholder="Middle name"
                        />
                        <InputError
                            class="mt-2"
                            :message="errors.middle_name"
                        />
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

                    <div class="grid gap-2">
                        <div class="flex items-baseline justify-between gap-3">
                            <Label for="bio">About me</Label>
                            <span class="text-xs text-muted-foreground">
                                Up to 280 characters
                            </span>
                        </div>
                        <textarea
                            id="bio"
                            name="bio"
                            rows="3"
                            maxlength="280"
                            class="mt-1 block w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Share a little about yourself, your interests, or what you enjoy learning."
                            :value="String(user.bio ?? '')"
                        ></textarea>
                        <InputError class="mt-2" :message="errors.bio" />
                    </div>

                    <div
                        class="space-y-4 rounded-xl border border-border/50 p-4"
                    >
                        <div>
                            <p class="text-sm font-semibold">Profile privacy</p>
                            <p class="text-xs text-muted-foreground">
                                Choose what classmates in your sections can see.
                                You and authorized staff always retain access.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="profile_visibility"
                                >Who can open your profile</Label
                            >
                            <select
                                id="profile_visibility"
                                name="profile_visibility"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :value="user.profile_visibility || 'section'"
                            >
                                <option value="section">
                                    Classmates in my sections
                                </option>
                                <option value="private">
                                    Only me and authorized staff
                                </option>
                            </select>
                            <InputError :message="errors.profile_visibility" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="setting in [
                                    {
                                        name: 'profile_show_activity',
                                        label: 'Activity and XP history',
                                        checked:
                                            user.profile_show_activity ?? false,
                                    },
                                    {
                                        name: 'profile_show_sections',
                                        label: 'Shared section names',
                                        checked:
                                            user.profile_show_sections ?? true,
                                    },
                                    {
                                        name: 'profile_show_social',
                                        label: 'Followers, kudos, and social actions',
                                        checked:
                                            user.profile_show_social ?? true,
                                    },
                                    {
                                        name: 'profile_show_achievements',
                                        label: 'Achievements and badges',
                                        checked:
                                            user.profile_show_achievements ??
                                            true,
                                    },
                                ]"
                                :key="setting.name"
                                class="flex items-start gap-3 rounded-lg bg-muted/30 p-3 text-sm"
                            >
                                <input
                                    type="hidden"
                                    :name="setting.name"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    :name="setting.name"
                                    value="1"
                                    :checked="setting.checked"
                                    class="mt-0.5 size-4 rounded border-input accent-primary"
                                />
                                <span>{{ setting.label }}</span>
                            </label>
                        </div>
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

                    <div class="space-y-3">
                        <div
                            class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <!-- Submitting mid-compression would upload the
                                 original file the input still holds. -->
                            <Button
                                :disabled="processing || isCompressingAvatar"
                                data-test="update-profile-button"
                                class="w-full sm:w-auto"
                            >
                                <Loader2
                                    v-if="processing"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                {{ processing ? 'Saving…' : 'Save' }}
                            </Button>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="recentlySuccessful"
                                    class="text-center text-sm text-neutral-600 sm:text-left"
                                >
                                    Saved.
                                </p>
                            </Transition>
                        </div>

                        <!-- Uploading a photo over mobile data can take many
                             seconds. Without a percentage the page looks
                             frozen and users re-tap Save or navigate away,
                             which aborts the upload. -->
                        <div
                            v-if="processing && progress"
                            class="space-y-1"
                            role="status"
                            aria-live="polite"
                        >
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-[width] duration-150 ease-out"
                                    :style="{
                                        width: `${progress.percentage ?? 0}%`,
                                    }"
                                ></div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Uploading… {{ progress.percentage ?? 0 }}%
                            </p>
                        </div>
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

        <CoverPhotoCropper
            :file="coverFileToCrop"
            @cropped="applyCroppedCover"
            @cancel="cancelCoverCrop"
        />
    </AppLayout>
</template>
