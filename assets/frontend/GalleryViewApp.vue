<script setup>
import { ref, computed } from "vue";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { KIND, useGalleryPicks }   from "./composables/useGalleryPicks.js";
import { useGalleryIdentity }      from "./composables/useGalleryIdentity.js";
import { useGalleryFinalize }      from "./composables/useGalleryFinalize.js";
import { useGalleryCompare }       from "./composables/useGalleryCompare.js";
import { useGalleryLightbox }      from "./composables/useGalleryLightbox.js";
import { useGalleryComment }       from "./composables/useGalleryComment.js";
import { useGalleryBurst }         from "./composables/useGalleryBurst.js";
import { HttpMethod } from "@/shared/utils/http/httpMethod.js";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppOverlayIconButton from "@/shared/components/action/AppOverlayIconButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppTextarea from "@/shared/components/form/input/AppTextarea.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import { translateServerErrors } from "@/shared/utils/validation/translateServerErrors.js";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import {
    Heart,
    Printer,
    Trash2,
    MessageSquare,
    Download,
    Archive,
    ChevronLeft,
    ChevronRight,
    X,
    Check,
    Send,
    Columns2,
    Share2,
    Copy,
} from "lucide-vue-next";

const { t } = useI18n();

const props = defineProps({
    gallery:           { type: Object,  required: true },
    items:             { type: Array,   required: true },
    visitorPicks:      { type: Object,  default: () => ({}) },
    favoriteCount:     { type: Number,  default: 0 },
    visitorIdentity:   { type: Object,  default: () => ({ name: null, email: null }) },
    finalizedByVisitor:{ type: Boolean, default: false },
    readOnly:          { type: Boolean, default: false },
    sharePath:         { type: String,  default: null },
    pickPath:          { type: String,  required: true },
    commentPath:       { type: String,  default: "" },
    finalizePath:      { type: String,  required: true },
    downloadItemPath:  { type: String,  required: true },
    downloadZipPath:   { type: String,  required: true },
});

const finalized      = ref(!!props.gallery.finalized || !!props.finalizedByVisitor);
const showCommentBox = ref(false);
const commentDraft   = ref("");

const { visitorName, visitorEmail, identityKnown, showIdentityModal, pendingPick, submitIdentity } =
    useGalleryIdentity(props.visitorIdentity);

const { picked, favoriteTotal, maxPicks, favoriteCountReached, isPicked, togglePick, sendToggle } =
    useGalleryPicks({ pickPath: props.pickPath, gallery: props.gallery, visitorPicks: props.visitorPicks, favoriteCount: props.favoriteCount, finalized, identityKnown, pendingPick, showIdentityModal });

const submitIdentityWrapped = () => submitIdentity(sendToggle);

const { showFinalizeModal, finalizeName, finalizeEmail, finalizing, finalizeNameError, finalizeEmailError, openFinalize, submitFinalize } =
    useGalleryFinalize({ finalizePath: props.finalizePath, gallery: props.gallery, finalized, identityKnown, visitorName, visitorEmail });

const { compareIds, showCompare, isCompared, toggleCompare, clearCompare, compareItems, compareGridClass } =
    useGalleryCompare(props.items);

const displayedItems = computed(() => {
    if (!props.readOnly) return props.items;
    return props.items.filter((i) => isPicked(i.id, KIND.Favorite) || isPicked(i.id, KIND.Print) || isPicked(i.id, KIND.Discard));
});

const { lightboxIndex, openLightbox, closeLightbox, prev, next } =
    useGalleryLightbox(displayedItems, showCommentBox, commentDraft);

const { commentSending, commentNameError, commentEmailError, submitComment } =
    useGalleryComment({ commentPath: props.commentPath, displayedItems, lightboxIndex, showCommentBox, commentDraft, visitorName, visitorEmail, identityKnown });

const { burstMap, burstIdOf, burstSizeOf, burstIndexOf } = useGalleryBurst(props.items);

// Share
const showShareModal = ref(false);
const shareCopied    = ref(false);
const shareFullUrl   = computed(() => props.sharePath ? window.location.origin + props.sharePath : "");

async function copyShareUrl() {
    if (!shareFullUrl.value) return;
    try { await navigator.clipboard.writeText(shareFullUrl.value); shareCopied.value = true; setTimeout(() => { shareCopied.value = false; }, 2500); }
    catch { toast.error(t("shared.common.error")); }
}

function downloadAll(onlyPicks = false) {
    const url = new URL(props.downloadZipPath, window.location.origin);
    if (onlyPicks) url.searchParams.set("picks", "1");
    if (props.gallery.allowOriginals) url.searchParams.set("variant", "original");
    window.location.href = url.toString();
}

function downloadOne(itemId) {
    const url = new URL(buildPath(props.downloadItemPath, { id: itemId }), window.location.origin);
    if (props.gallery.allowOriginals) url.searchParams.set("variant", "original");
    window.location.href = url.toString();
}
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-8 photo-protected">
        <!-- Header -->
        <header class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-primary">{{ gallery.title }}</h1>
                <p v-if="gallery.description" class="text-muted text-sm mt-1 max-w-2xl">{{ gallery.description }}</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <span v-if="maxPicks" class="text-sm" :class="favoriteCountReached ? 'text-rose-500' : 'text-muted'">
                    {{ t("photo.frontend.maxPicksProgress", { count: favoriteTotal, limit: maxPicks }) }}
                </span>
                <span v-else class="text-sm text-muted flex items-center gap-1.5">
                    <Heart class="w-4 h-4 text-accent-500" :stroke-width="2" fill="currentColor" />
                    {{ favoriteTotal }} {{ t("photo.frontend.picked") }}
                </span>
                <AppButton
                    v-if="gallery.allowZipDownload && !readOnly"
                    variant="secondary"
                    size="sm"
                    type="button"
                    v-on:click="downloadAll(false)"
                >
                    <Archive class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.frontend.downloadAll") }}
                </AppButton>
                <AppButton
                    v-if="gallery.allowZipDownload && favoriteTotal > 0 && !readOnly"
                    variant="secondary"
                    size="sm"
                    type="button"
                    v-on:click="downloadAll(true)"
                >
                    <Download class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.frontend.downloadPicks") }}
                </AppButton>
                <AppButton
                    v-if="!finalized && favoriteTotal > 0 && !readOnly"
                    variant="primary"
                    size="sm"
                    type="button"
                    v-on:click="openFinalize"
                >
                    <Send class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.frontend.finalize") }}
                </AppButton>
                <AppButton
                    v-if="sharePath && favoriteTotal > 0 && !readOnly"
                    variant="ghost"
                    size="sm"
                    type="button"
                    v-on:click="showShareModal = true"
                >
                    <Share2 class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.frontend.share.button") }}
                </AppButton>
                <span v-if="finalized && !readOnly" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-500 text-xs font-medium">
                    <Check class="w-3.5 h-3.5" :stroke-width="2.5" />
                    {{ t("photo.frontend.finalizedBadge") }}
                </span>
                <span v-if="readOnly" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-500/15 text-blue-500 text-xs font-medium">
                    <Share2 class="w-3.5 h-3.5" :stroke-width="2.5" />
                    {{ t("photo.frontend.share.readOnlyBadge") }}
                </span>
            </div>
        </header>

        <!-- Grid -->
        <AppNoData v-if="displayedItems.length === 0" :message="t('photo.frontend.empty')" />
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
            <AppButton
                v-for="(item, index) in displayedItems"
                :key="item.id"
                type="button"
                variant="ghost"
                size="none"
                :class="'group relative aspect-square overflow-hidden rounded-md bg-surface-2 focus:outline-none focus:ring-2 focus:ring-accent-500'"
                v-on:click="openLightbox(index)"
            >
                <img
                    :src="item.thumb"
                    :alt="item.alt || ''"
                    loading="lazy"
                    draggable="false"
                    class="w-full h-full object-cover transition-transform group-hover:scale-105 photo-protected-img"
                    v-on:contextmenu.prevent
                >
                <span v-if="item.number" class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded bg-black/55 text-white text-[10px] font-semibold tabular-nums tracking-wide">
                    #{{ item.number }}
                </span>
                <span
                    v-if="burstIdOf(item)"
                    class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded bg-amber-500/90 text-white text-[10px] font-semibold tabular-nums shadow"
                    :title="t('photo.frontend.burst.tooltip', { size: burstSizeOf(burstIdOf(item)) })"
                >
                    {{ burstIndexOf(item, burstIdOf(item)) }}/{{ burstSizeOf(burstIdOf(item)) }}
                </span>
                <AppOverlayIconButton
                    v-if="!finalized"
                    size="sm"
                    :active="isPicked(item.id, KIND.Favorite)"
                    :aria-label="isPicked(item.id, KIND.Favorite) ? t('photo.frontend.unpick') : t('photo.frontend.pick')"
                    class="absolute top-1.5 right-1.5"
                    v-on:click.stop="togglePick(item.id, KIND.Favorite)"
                >
                    <Heart class="w-4 h-4" :stroke-width="2" :fill="isPicked(item.id, KIND.Favorite) ? 'currentColor' : 'none'" />
                </AppOverlayIconButton>
                <span
                    v-else-if="isPicked(item.id, KIND.Favorite)"
                    class="absolute top-1.5 right-1.5 inline-flex items-center justify-center w-8 h-8 rounded-full bg-black/40 text-accent-500"
                >
                    <Heart class="w-4 h-4" :stroke-width="2" fill="currentColor" />
                </span>
                <AppOverlayIconButton
                    size="sm"
                    :active="isCompared(item.id)"
                    :aria-label="isCompared(item.id) ? t('photo.frontend.compare.remove') : t('photo.frontend.compare.add')"
                    class="absolute top-1.5 left-1.5 transition-opacity"
                    :class="isCompared(item.id) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                    v-on:click.stop="toggleCompare(item.id)"
                >
                    <Columns2 class="w-4 h-4" :stroke-width="2" />
                </AppOverlayIconButton>
            </AppButton>
        </div>

        <!-- Sticky favorites progress -->
        <div
            v-if="maxPicks && lightboxIndex === null && !showCompare"
            class="fixed top-4 right-4 z-30 flex items-center gap-2 px-3 py-1.5 rounded-full bg-surface/90 backdrop-blur border border-line shadow-md"
        >
            <Heart class="w-4 h-4" :stroke-width="2" :fill="favoriteCountReached ? 'currentColor' : 'none'" :class="favoriteCountReached ? 'text-rose-500' : 'text-accent-500'" />
            <span class="text-sm font-semibold tabular-nums" :class="favoriteCountReached ? 'text-rose-500' : 'text-primary'">
                {{ favoriteTotal }}<span class="text-muted font-normal">/{{ maxPicks }}</span>
            </span>
        </div>

        <!-- Floating compare bar -->
        <div
            v-if="compareIds.length > 0 && !showCompare"
            class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 flex items-center gap-2 px-4 py-2 rounded-full bg-accent-500 text-white shadow-lg"
        >
            <Columns2 class="w-4 h-4" :stroke-width="2" />
            <span class="text-sm font-medium">{{ t("photo.frontend.compare.selected", { count: compareIds.length, max: COMPARE_MAX }) }}</span>
            <AppButton
                type="button"
                variant="ghost"
                size="none"
                :class="'text-sm font-medium underline ml-2 text-white'"
                v-on:click="showCompare = true"
            >
                {{ t("photo.frontend.compare.open") }}
            </AppButton>
            <AppButton
                type="button"
                variant="ghost"
                size="none"
                :class="'ml-1 opacity-80 hover:opacity-100'"
                :aria-label="t('photo.frontend.compare.clear')"
                v-on:click="clearCompare"
            >
                <X class="w-4 h-4" :stroke-width="2" />
            </AppButton>
        </div>

        <!-- Compare modal -->
        <div
            v-if="showCompare"
            class="fixed inset-0 z-50 bg-black/95 flex flex-col"
            v-on:click.self="showCompare = false"
        >
            <div class="flex items-center justify-between p-4 text-white">
                <span class="text-sm font-medium">{{ t("photo.frontend.compare.title") }} ({{ compareItems.length }})</span>
                <div class="flex items-center gap-2">
                    <AppButton
                        type="button"
                        variant="ghost"
                        size="none"
                        :class="'text-sm underline text-white'"
                        v-on:click="clearCompare"
                    >
                        {{ t("photo.frontend.compare.clear") }}
                    </AppButton>
                    <AppOverlayIconButton size="md" variant="light" :title="t('shared.common.close')" v-on:click="showCompare = false">
                        <X class="w-5 h-5" :stroke-width="2" />
                    </AppOverlayIconButton>
                </div>
            </div>
            <div class="flex-1 min-h-0 p-3 overflow-auto">
                <div class="grid h-full gap-2" :class="compareGridClass">
                    <div v-for="item in compareItems" :key="item.id" class="relative bg-black rounded overflow-hidden">
                        <img
                            :src="item.medium ?? item.thumb"
                            :alt="item.alt || ''"
                            class="w-full h-full object-contain photo-protected-img"
                            draggable="false"
                            v-on:contextmenu.prevent
                        >
                        <span v-if="item.number" class="absolute top-2 left-2 px-2 py-1 rounded bg-black/60 text-white text-xs font-semibold tabular-nums">
                            #{{ item.number }}
                        </span>
                        <AppButton
                            type="button"
                            variant="ghost"
                            size="none"
                            :class="'absolute top-2 right-2 inline-flex items-center justify-center w-8 h-8 rounded-full bg-black/60 text-white hover:bg-black/80'"
                            :title="t('photo.frontend.compare.remove')"
                            v-on:click="toggleCompare(item.id)"
                        >
                            <X class="w-4 h-4" :stroke-width="2" />
                        </AppButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <div
            v-if="lightboxIndex !== null"
            class="fixed inset-0 z-50 bg-black/95 flex flex-col"
            v-on:click.self="closeLightbox"
        >
            <div class="flex items-center justify-between p-4 text-white">
                <span class="text-sm tabular-nums">
                    <span v-if="displayedItems[lightboxIndex].number" class="font-semibold mr-2">#{{ displayedItems[lightboxIndex].number }}</span>
                    <span class="opacity-70">{{ lightboxIndex + 1 }} / {{ displayedItems.length }}</span>
                </span>
                <div class="flex items-center gap-2">
                    <template v-if="!finalized">
                        <AppOverlayIconButton
                            size="md"
                            variant="light"
                            :active="isPicked(displayedItems[lightboxIndex].id, KIND.Favorite)"
                            :title="t('photo.frontend.kinds.favorite')"
                            v-on:click="togglePick(displayedItems[lightboxIndex].id, KIND.Favorite)"
                        >
                            <Heart class="w-5 h-5" :stroke-width="2" :fill="isPicked(displayedItems[lightboxIndex].id, KIND.Favorite) ? 'currentColor' : 'none'" />
                        </AppOverlayIconButton>
                        <AppOverlayIconButton
                            size="md"
                            variant="light"
                            :active="isPicked(displayedItems[lightboxIndex].id, KIND.Print)"
                            :title="t('photo.frontend.kinds.print')"
                            v-on:click="togglePick(displayedItems[lightboxIndex].id, KIND.Print)"
                        >
                            <Printer class="w-5 h-5" :stroke-width="2" />
                        </AppOverlayIconButton>
                        <AppOverlayIconButton
                            size="md"
                            variant="light"
                            :active="isPicked(displayedItems[lightboxIndex].id, KIND.Discard)"
                            :title="t('photo.frontend.kinds.discard')"
                            v-on:click="togglePick(displayedItems[lightboxIndex].id, KIND.Discard)"
                        >
                            <Trash2 class="w-5 h-5" :stroke-width="2" />
                        </AppOverlayIconButton>
                    </template>
                    <!-- Read-only state on finalized galleries: surface what was picked, no toggling -->
                    <template v-else>
                        <span
                            v-if="isPicked(displayedItems[lightboxIndex].id, KIND.Favorite)"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-accent-500"
                            :title="t('photo.frontend.kinds.favorite')"
                        >
                            <Heart class="w-5 h-5" :stroke-width="2" fill="currentColor" />
                        </span>
                        <span
                            v-if="isPicked(displayedItems[lightboxIndex].id, KIND.Print)"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-blue-400"
                            :title="t('photo.frontend.kinds.print')"
                        >
                            <Printer class="w-5 h-5" :stroke-width="2" />
                        </span>
                        <span
                            v-if="isPicked(displayedItems[lightboxIndex].id, KIND.Discard)"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-rose-400"
                            :title="t('photo.frontend.kinds.discard')"
                        >
                            <Trash2 class="w-5 h-5" :stroke-width="2" />
                        </span>
                    </template>
                    <AppOverlayIconButton
                        v-if="gallery.allowVisitorComments && !finalized"
                        size="md"
                        variant="light"
                        :active="showCommentBox"
                        :title="t('photo.frontend.comments.add')"
                        v-on:click="showCommentBox = !showCommentBox"
                    >
                        <MessageSquare class="w-5 h-5" :stroke-width="2" />
                    </AppOverlayIconButton>
                    <AppOverlayIconButton
                        size="md"
                        variant="light"
                        :title="t('photo.frontend.download')"
                        v-on:click="downloadOne(displayedItems[lightboxIndex].id)"
                    >
                        <Download class="w-5 h-5" :stroke-width="2" />
                    </AppOverlayIconButton>
                    <AppOverlayIconButton
                        size="md"
                        variant="light"
                        :title="t('shared.common.close')"
                        v-on:click="closeLightbox"
                    >
                        <X class="w-5 h-5" :stroke-width="2" />
                    </AppOverlayIconButton>
                </div>
            </div>
            <div class="flex-1 flex items-center justify-center px-4 pb-4 relative">
                <AppOverlayIconButton
                    size="lg"
                    variant="light"
                    :title="t('shared.common.previous')"
                    class="absolute left-2 top-1/2 -translate-y-1/2"
                    v-on:click="prev"
                >
                    <ChevronLeft class="w-6 h-6" :stroke-width="2" />
                </AppOverlayIconButton>
                <div class="relative max-h-[80vh] max-w-full">
                    <img
                        :src="displayedItems[lightboxIndex].full"
                        :alt="displayedItems[lightboxIndex].alt || ''"
                        draggable="false"
                        class="max-h-[80vh] max-w-full object-contain photo-protected-img"
                        v-on:contextmenu.prevent
                    >
                </div>
                <AppOverlayIconButton
                    size="lg"
                    variant="light"
                    :title="t('shared.common.next')"
                    class="absolute right-2 top-1/2 -translate-y-1/2"
                    v-on:click="next"
                >
                    <ChevronRight class="w-6 h-6" :stroke-width="2" />
                </AppOverlayIconButton>
            </div>

            <!-- Caption -->
            <p
                v-if="displayedItems[lightboxIndex].caption"
                class="text-center text-white/80 text-sm pb-6 px-4 max-w-3xl mx-auto"
            >
                {{ displayedItems[lightboxIndex].caption }}
            </p>
        </div>

        <!-- Identity modal -->
        <AppModal :show="showIdentityModal" :title="t('photo.frontend.identity.title')" :closeable="false" v-on:close="showIdentityModal = false">
            <form class="space-y-4" v-on:submit.prevent="submitIdentity">
                <p class="text-sm text-muted">{{ t("photo.frontend.identity.intro") }}</p>
                <AppInput v-model="visitorName" :label="t('photo.frontend.identity.name')" required />
                <AppInput v-model="visitorEmail" type="email" :label="t('photo.frontend.identity.email')" required />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" type="button" v-on:click="showIdentityModal = false"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton type="submit" variant="primary" size="md">{{ t("photo.frontend.identity.submit") }}</AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Comment modal -->
        <AppModal :show="showCommentBox" :closeable="false" v-on:close="showCommentBox = false">
            <h3 class="text-lg font-semibold text-primary mb-4 flex items-center gap-2">
                <MessageSquare class="w-5 h-5 text-accent-500" :stroke-width="2" />
                {{ t("photo.frontend.comments.add") }}
            </h3>
            <form class="space-y-4" v-on:submit.prevent="submitComment">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <AppInput
                        v-model="visitorName"
                        :label="t('photo.frontend.identity.name')"
                        :placeholder="t('photo.frontend.identity.namePlaceholder')"
                        :error="commentNameError"
                        required
                    />
                    <AppInput
                        v-model="visitorEmail"
                        type="email"
                        :label="t('photo.frontend.identity.email')"
                        :placeholder="t('photo.frontend.identity.emailPlaceholder')"
                        :error="commentEmailError"
                        required
                    />
                </div>
                <AppTextarea
                    v-model="commentDraft"
                    :label="t('photo.frontend.comments.label')"
                    :placeholder="t('photo.frontend.comments.placeholder')"
                    :rows="4"
                    maxlength="2000"
                />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" type="button" v-on:click="showCommentBox = false">
                        <X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("photo.frontend.comments.cancel") }}
                    </AppButton>
                    <AppButton variant="primary" size="md" type="submit" :loading="commentSending">
                        <Send class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("photo.frontend.comments.submit") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Share my selection modal -->
        <AppModal :show="showShareModal" :title="t('photo.frontend.share.title')" :closeable="false" v-on:close="showShareModal = false">
            <p class="text-sm text-muted mb-4">{{ t("photo.frontend.share.intro", { count: favoriteTotal }) }}</p>
            <div class="flex items-center gap-2 p-2 rounded-lg bg-surface-2 border border-line">
                <input
                    type="text"
                    readonly
                    :value="shareFullUrl"
                    class="flex-1 bg-transparent text-sm text-primary outline-none truncate"
                    v-on:focus="$event.target.select()"
                >
                <AppButton variant="primary" size="sm" type="button" v-on:click="copyShareUrl">
                    <Copy class="w-4 h-4" :stroke-width="2" />
                    {{ shareCopied ? t("photo.frontend.share.copied") : t("photo.frontend.share.copy") }}
                </AppButton>
            </div>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" type="button" v-on:click="showShareModal = false"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.close") }}</AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Finalize modal -->
        <AppModal :show="showFinalizeModal" :title="t('photo.frontend.finalize')" :closeable="false" v-on:close="showFinalizeModal = false">
            <form class="space-y-4" v-on:submit.prevent="submitFinalize">
                <p class="text-sm text-muted">{{ t("photo.frontend.finalizeIntro", { count: favoriteTotal }) }}</p>
                <AppInput
                    v-model="finalizeName"
                    :label="t('photo.frontend.identity.name')"
                    :placeholder="t('photo.frontend.identity.namePlaceholder')"
                    :error="finalizeNameError"
                    required
                />
                <AppInput
                    v-model="finalizeEmail"
                    type="email"
                    :label="t('photo.frontend.identity.email')"
                    :placeholder="t('photo.frontend.identity.emailPlaceholder')"
                    :error="finalizeEmailError"
                    required
                />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" type="button" v-on:click="showFinalizeModal = false"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="primary" size="md" type="submit" :loading="finalizing">
                        <Send class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("photo.frontend.finalize") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
    </div>
</template>

<style scoped>
/* Visual deterrents — the photos can still be reached via the URL or DevTools,
   but casual save-to-disk paths (right-click, drag, text-select) are blocked. */
.photo-protected {
    user-select: none;
    -webkit-user-select: none;
}

.photo-protected-img {
    -webkit-user-drag: none;
    user-drag: none;
    pointer-events: auto;
}

@media print {
    .photo-protected-img { display: none !important; }
}
</style>
