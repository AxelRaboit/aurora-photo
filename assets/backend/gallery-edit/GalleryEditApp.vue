<script setup>
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppIconButton from "@/shared/components/action/AppIconButton.vue";
import AppOverlayIconButton from "@/shared/components/action/AppOverlayIconButton.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import AppPagination from "@/shared/components/nav/AppPagination.vue";
import AppSelectionCheck from "@/shared/components/feedback/AppSelectionCheck.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppCheckbox from "@/shared/components/form/toggle/AppCheckbox.vue";
import { useGalleryInvites } from "@photo/backend/galleries/composables/useGalleryInvites.js";
import { useGalleryFinalizations } from "@photo/backend/galleries/composables/useGalleryFinalizations.js";
import { useGalleryEditItems } from "@photo/backend/gallery-edit/composables/useGalleryEditItems.js";
import { useGalleryEditConsensus } from "@photo/backend/gallery-edit/composables/useGalleryEditConsensus.js";
import { useGalleryEditCaption } from "@photo/backend/gallery-edit/composables/useGalleryEditCaption.js";
import { useGalleryEditReopen } from "@photo/backend/gallery-edit/composables/useGalleryEditReopen.js";
import { useGalleryEditComments } from "@photo/backend/gallery-edit/composables/useGalleryEditComments.js";
import { Plus, Trash2, ExternalLink, Heart, GripVertical, Pencil, Check, X, Download, Unlock, Printer, Trash, MessageSquare, UserCheck, ChevronDown, ChevronRight, Mail, Send, AlertCircle } from "lucide-vue-next";

const { t } = useI18n();

const props = defineProps({
    gallery: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    picks: { type: Object, default: () => ({ total: 0, totalsByKind: {}, byItemId: {} }) },
    comments: { type: Array, default: () => [] },
    finalizations: { type: Array, default: () => [] },
    invites: { type: Array, default: () => [] },
    backPath: { type: String, required: true },
    previewPath: { type: String, required: true },
    reopenPath: { type: String, default: "" },
    finalizationDeletePath: { type: String, default: "" },
    invitesCreatePath: { type: String, default: "" },
    invitesSendPath: { type: String, default: "" },
    invitesDeletePath: { type: String, default: "" },
    exportPath: { type: String, default: "" },
    itemsAddPath: { type: String, required: true },
    itemsReorderPath: { type: String, required: true },
    itemsCaptionPath: { type: String, required: true },
    itemsDeletePath: { type: String, required: true },
    itemsBulkDeletePath: { type: String, required: true },
    commentDeletePath: { type: String, default: "" },
});

const galleryRef = ref({ ...props.gallery });
const comments = ref([...props.comments]);
const finalizations = ref([...props.finalizations]);
const invites = ref([...props.invites]);
const pendingInvites = computed(() => invites.value.filter((i) => !i.finalizedAt));

const { items, selected, allSelected, toggleSelect, toggleSelectAll, itemById, itemPreview, addPhotos, pendingDeleteItem, deleteOneLoading, askDeleteOne, confirmDeleteOne, pendingBulkDelete, bulkDeleteLoading, askBulkDelete, confirmBulkDelete, onDragStart, onDragOver, onDrop } =
    useGalleryEditItems(props, props.items);

const { picks, sortByConsensus, visitorCount, consensusFavorite, pickKindCount, pickCount, displayedItems } =
    useGalleryEditConsensus(props.picks, items);

const { editingCaptionId, editingCaptionDraft, startCaption, cancelCaption, saveCaption } =
    useGalleryEditCaption(props.itemsCaptionPath, items);

const { showReopenModal, reopenLoading, askReopen, confirmReopen } =
    useGalleryEditReopen(props.reopenPath, galleryRef);

const { pendingCommentDelete, commentDeleteLoading, askDeleteComment, confirmDeleteComment, commentsForItem } =
    useGalleryEditComments(props.commentDeletePath, comments);

const { inviteForm, inviteErrors, inviteCreating, inviteSendingId, pendingInviteDelete, inviteDeleting, createInvite, sendInvite, askDeleteInvite, confirmDeleteInvite } =
    useGalleryInvites({ create: props.invitesCreatePath, send: props.invitesSendPath, delete: props.invitesDeletePath }, invites);

const { expandedFinalizations, finalizationsPage, finalizationsTotalPages, paginatedFinalizations, goToFinalizationsPage, toggleFinalization, pendingFinalizationDelete, finalizationDeleteLoading, askDeleteFinalization, confirmDeleteFinalization } =
    useGalleryFinalizations(props.finalizationDeletePath, finalizations, invites, galleryRef);
</script>

<template>
    <div class="space-y-6">
        <!-- Toolbar -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted">{{ items.length }} {{ t("photo.galleries.itemsLabel") }}</span>
                <span v-if="picks.total > 0" class="inline-flex items-center gap-1 text-sm text-accent-500">
                    <Heart class="w-3.5 h-3.5" :stroke-width="2" fill="currentColor" />
                    {{ picks.total }} {{ t("photo.galleries.picksTotal") }}
                </span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <AppButton
                    variant="secondary"
                    size="md"
                    :href="previewPath"
                    target="_blank"
                    rel="noopener"
                >
                    <ExternalLink class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.galleries.openPublic") }}
                </AppButton>
                <AppButton v-if="exportPath" variant="secondary" size="md" :href="exportPath">
                    <Download class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.galleries.admin.export") }}
                </AppButton>
                <AppButton v-if="galleryRef.finalizedAt && reopenPath" variant="ghost" v-on:click="askReopen">
                    <Unlock class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.galleries.admin.reopen") }}
                </AppButton>
                <AppButton variant="primary" size="md" v-on:click="addPhotos">
                    <Plus class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("photo.galleries.addPhotos") }}
                </AppButton>
            </div>
        </div>

        <!-- Pick kind totals -->
        <div v-if="picks.totalsByKind && Object.keys(picks.totalsByKind).length" class="flex items-center gap-3 text-sm">
            <span v-if="picks.totalsByKind.favorite" class="inline-flex items-center gap-1 text-accent-500">
                <Heart class="w-3.5 h-3.5" :stroke-width="2" fill="currentColor" />
                {{ picks.totalsByKind.favorite }} {{ t("photo.frontend.kinds.favorite") }}
            </span>
            <span v-if="picks.totalsByKind.print" class="inline-flex items-center gap-1 text-blue-500">
                <Printer class="w-3.5 h-3.5" :stroke-width="2" />
                {{ picks.totalsByKind.print }} {{ t("photo.frontend.kinds.print") }}
            </span>
            <span v-if="picks.totalsByKind.discard" class="inline-flex items-center gap-1 text-rose-500">
                <Trash class="w-3.5 h-3.5" :stroke-width="2" />
                {{ picks.totalsByKind.discard }} {{ t("photo.frontend.kinds.discard") }}
            </span>
        </div>

        <!-- Bulk actions bar -->
        <div v-if="items.length > 0" class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg bg-surface border border-line">
            <AppCheckbox :model-value="allSelected" v-on:update:model-value="toggleSelectAll">
                {{ t("photo.galleries.selectAll") }}
                <span v-if="selected.size > 0" class="text-muted">({{ selected.size }})</span>
            </AppCheckbox>
            <div class="flex items-center gap-2">
                <AppCheckbox v-if="visitorCount > 0" v-model="sortByConsensus" :label="t('photo.galleries.admin.sortByConsensus')" />
                <AppButton
                    v-if="selected.size > 0"
                    variant="danger"
                    size="sm"
                    type="button"
                    v-on:click="askBulkDelete"
                >
                    <Trash2 class="w-4 h-4" :stroke-width="2" />
                    {{ t("photo.galleries.bulkDelete") }}
                </AppButton>
            </div>
        </div>

        <AppNoData v-if="items.length === 0">
            {{ t("photo.galleries.noPhotos") }}
        </AppNoData>

        <!-- Photo grid -->
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            <div
                v-for="(item, index) in displayedItems"
                :key="item.id"
                :draggable="!sortByConsensus"
                class="group relative bg-surface border border-line rounded-lg overflow-hidden transition-all"
                :class="selected.has(item.id) ? 'ring-2 ring-accent-500 border-accent-500' : 'hover:border-accent-400'"
                v-on:dragstart="onDragStart(index, $event)"
                v-on:dragover="onDragOver"
                v-on:drop="onDrop(index)"
            >
                <div class="aspect-square bg-surface-2 relative cursor-pointer" v-on:click="toggleSelect(item.id)">
                    <img :src="item.thumb" :alt="item.alt || ''" loading="lazy" class="w-full h-full object-cover">
                    <span class="absolute top-1.5 left-1.5 pointer-events-none">
                        <AppSelectionCheck :active="selected.has(item.id)" />
                    </span>
                    <span class="absolute top-1.5 right-1.5 inline-flex items-center justify-center w-6 h-6 rounded-full bg-black/40 text-white/70 cursor-grab active:cursor-grabbing" v-on:click.stop>
                        <GripVertical class="w-3.5 h-3.5" :stroke-width="2" />
                    </span>
                    <div class="absolute bottom-1.5 left-1.5 flex items-center gap-1 pointer-events-none">
                        <span v-if="item.number" class="px-1.5 py-0.5 rounded bg-black/55 text-white text-[10px] font-semibold tabular-nums">
                            #{{ item.number }}
                        </span>
                        <span
                            v-if="visitorCount > 1 && consensusFavorite(item.id) > 0"
                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-white text-[10px] font-bold"
                            :class="consensusFavorite(item.id) === visitorCount ? 'bg-emerald-500' : 'bg-accent-500'"
                            :title="t('photo.galleries.admin.consensusTooltip', { count: consensusFavorite(item.id), total: visitorCount })"
                        >
                            <Heart class="w-2.5 h-2.5" :stroke-width="3" fill="currentColor" />
                            {{ consensusFavorite(item.id) }}/{{ visitorCount }}
                        </span>
                        <span v-else-if="pickCount(item.id) > 0" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full bg-accent-500 text-white text-[10px] font-bold">
                            <Heart class="w-2.5 h-2.5" :stroke-width="3" fill="currentColor" />
                            {{ pickCount(item.id) }}
                        </span>
                    </div>
                    <AppOverlayIconButton
                        size="xs"
                        variant="danger"
                        class="absolute bottom-1.5 right-1.5"
                        :aria-label="t('shared.common.delete')"
                        v-on:click.stop="askDeleteOne(item)"
                    >
                        <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                    </AppOverlayIconButton>
                </div>
                <div class="p-2 text-xs">
                    <template v-if="editingCaptionId === item.id">
                        <AppInput
                            v-model="editingCaptionDraft"
                            :placeholder="t('photo.galleries.captionPlaceholder')"
                            v-on:keyup.enter="saveCaption(item)"
                            v-on:keyup.escape="cancelCaption"
                        />
                        <div class="flex justify-end gap-1 mt-1">
                            <AppIconButton size="xs" :title="t('shared.common.save')" v-on:click="saveCaption(item)">
                                <Check class="w-3.5 h-3.5" :stroke-width="2" />
                            </AppIconButton>
                            <AppIconButton size="xs" variant="ghost" :title="t('shared.common.cancel')" v-on:click="cancelCaption">
                                <X class="w-3.5 h-3.5" :stroke-width="2" />
                            </AppIconButton>
                        </div>
                    </template>
                    <AppButton
                        v-else
                        variant="ghost"
                        size="none"
                        class="w-full text-left text-muted hover:text-primary truncate flex items-center gap-1"
                        v-on:click="startCaption(item)"
                    >
                        <Pencil class="w-3 h-3 shrink-0 opacity-50" :stroke-width="2" />
                        <span class="truncate">{{ item.caption || t("photo.galleries.captionPlaceholder") }}</span>
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- Magic-link invites -->
        <section v-if="invitesCreatePath" class="space-y-3 pt-4 border-t border-line">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                <Mail class="w-4 h-4 text-blue-500" :stroke-width="2" />
                {{ t("photo.galleries.admin.invites.title") }}
                <span v-if="pendingInvites.length" class="text-muted font-normal">({{ pendingInvites.length }})</span>
            </h2>
            <p class="text-xs text-muted">{{ t("photo.galleries.admin.invites.intro") }}</p>
            <form class="flex flex-wrap items-end gap-2" v-on:submit.prevent="createInvite">
                <div class="flex-1 min-w-[12rem]">
                    <AppInput
                        v-model="inviteForm.name"
                        :label="t('photo.galleries.admin.invites.name')"
                        :placeholder="t('photo.frontend.identity.namePlaceholder')"
                        :error="inviteErrors.name"
                        required
                    />
                </div>
                <div class="flex-1 min-w-[14rem]">
                    <AppInput
                        v-model="inviteForm.email"
                        type="email"
                        :label="t('photo.galleries.admin.invites.email')"
                        :placeholder="t('photo.frontend.identity.emailPlaceholder')"
                        :error="inviteErrors.email"
                        required
                    />
                </div>
                <AppButton variant="primary" size="md" type="submit" :loading="inviteCreating">
                    <Plus class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("shared.common.add") }}
                </AppButton>
            </form>
            <ul v-if="pendingInvites.length" class="space-y-2">
                <li v-for="invite in pendingInvites" :key="invite.id" class="bg-surface border border-line rounded-lg p-3 flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-primary">
                            <span class="font-medium">{{ invite.name }}</span>
                            <span class="text-muted ml-1">&lt;{{ invite.email }}&gt;</span>
                        </p>
                        <p class="text-xs text-muted mt-0.5 flex items-center gap-3 flex-wrap">
                            <span v-if="!invite.sentAt" class="text-amber-500">{{ t("photo.galleries.admin.invites.notSent") }}</span>
                            <span v-else>{{ t("photo.galleries.admin.invites.sentAt", { date: new Date(invite.sentAt).toLocaleString() }) }}</span>
                            <span v-if="invite.lastSeenAt" class="text-emerald-500">{{ t("photo.galleries.admin.invites.lastSeen", { date: new Date(invite.lastSeenAt).toLocaleString() }) }}</span>
                            <span v-else-if="invite.sentAt" class="text-muted italic">{{ t("photo.galleries.admin.invites.notSeen") }}</span>
                        </p>
                    </div>
                    <AppIconButton
                        :title="invite.sentAt ? t('photo.galleries.admin.invites.resend') : t('photo.galleries.admin.invites.send')"
                        :loading="inviteSendingId === invite.id"
                        v-on:click="sendInvite(invite)"
                    >
                        <Send class="w-4 h-4" :stroke-width="2" />
                    </AppIconButton>
                    <AppIconButton color="rose" :title="t('shared.common.delete')" v-on:click="askDeleteInvite(invite)">
                        <Trash2 class="w-4 h-4" :stroke-width="2" />
                    </AppIconButton>
                </li>
            </ul>
        </section>

        <!-- Visitor finalizations -->
        <section v-if="finalizations.length" class="space-y-3 pt-4 border-t border-line">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                <UserCheck class="w-4 h-4 text-emerald-500" :stroke-width="2" />
                {{ t("photo.galleries.admin.finalizations.title") }}
                <span class="text-muted font-normal">({{ finalizations.length }})</span>
            </h2>
            <ul class="space-y-2">
                <li v-for="finalization in paginatedFinalizations" :key="finalization.id" class="bg-surface border border-line rounded-lg overflow-hidden">
                    <div class="p-3 flex items-center gap-3">
                        <AppIconButton
                            class="shrink-0"
                            :aria-label="expandedFinalizations.has(finalization.id) ? t('shared.common.collapse') : t('shared.common.expand')"
                            v-on:click="toggleFinalization(finalization.id)"
                        >
                            <ChevronDown v-if="expandedFinalizations.has(finalization.id)" class="w-4 h-4" :stroke-width="2" />
                            <ChevronRight v-else class="w-4 h-4" :stroke-width="2" />
                        </AppIconButton>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-primary">
                                <span v-if="finalization.visitorName" class="font-medium">{{ finalization.visitorName }}</span>
                                <span v-if="finalization.visitorEmail" class="text-muted ml-1">&lt;{{ finalization.visitorEmail }}&gt;</span>
                                <span v-if="!finalization.visitorName && !finalization.visitorEmail" class="italic text-muted">{{ t("photo.galleries.admin.comments.anonymous") }}</span>
                            </p>
                            <p v-if="finalization.invitedAs" class="text-xs text-amber-600 mt-0.5 flex items-center gap-1">
                                <Mail class="w-3 h-3" :stroke-width="2" />
                                {{ t("photo.galleries.admin.finalizations.invitedAs", { name: finalization.invitedAs.name, email: finalization.invitedAs.email }) }}
                            </p>
                            <p class="text-xs text-muted mt-0.5 flex items-center gap-3 flex-wrap">
                                <span>{{ new Date(finalization.finalizedAt).toLocaleString() }}</span>
                                <span v-if="finalization.picksByKind.favorite.length" class="inline-flex items-center gap-1 text-accent-500">
                                    <Heart class="w-3 h-3" :stroke-width="2.5" fill="currentColor" />
                                    {{ finalization.picksByKind.favorite.length }}
                                </span>
                                <span v-if="finalization.picksByKind.print.length" class="inline-flex items-center gap-1 text-blue-500">
                                    <Printer class="w-3 h-3" :stroke-width="2" />
                                    {{ finalization.picksByKind.print.length }}
                                </span>
                                <span v-if="finalization.picksByKind.discard.length" class="inline-flex items-center gap-1 text-rose-500">
                                    <Trash class="w-3 h-3" :stroke-width="2" />
                                    {{ finalization.picksByKind.discard.length }}
                                </span>
                            </p>
                        </div>
                        <AppIconButton color="rose" :title="t('photo.galleries.admin.finalizations.reopen')" v-on:click="askDeleteFinalization(finalization)">
                            <Unlock class="w-4 h-4" :stroke-width="2" />
                        </AppIconButton>
                    </div>
                    <div v-if="expandedFinalizations.has(finalization.id)" class="border-t border-line p-3 space-y-3 bg-surface-2/40">
                        <div v-for="kind in ['favorite', 'print', 'discard']" v-show="finalization.picksByKind[kind].length" :key="kind">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted mb-2">
                                {{ t('photo.frontend.kinds.' + kind) }}
                                <span class="font-normal">({{ finalization.picksByKind[kind].length }})</span>
                            </h3>
                            <div class="grid grid-cols-6 sm:grid-cols-8 lg:grid-cols-10 gap-1.5">
                                <div v-for="itemId in finalization.picksByKind[kind]" :key="itemId" class="aspect-square rounded overflow-hidden bg-surface border border-line relative">
                                    <img
                                        v-if="itemById(itemId)"
                                        :src="itemById(itemId).thumb"
                                        :alt="itemById(itemId).alt || ''"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                    >
                                    <span v-if="itemById(itemId)?.number" class="absolute bottom-0.5 left-0.5 px-1 py-px rounded bg-black/60 text-white text-[9px] font-semibold tabular-nums">
                                        #{{ itemById(itemId).number }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p v-if="!finalization.picksByKind.favorite.length && !finalization.picksByKind.print.length && !finalization.picksByKind.discard.length" class="text-xs text-muted italic">
                            {{ t("photo.galleries.admin.finalizations.empty") }}
                        </p>
                    </div>
                </li>
            </ul>
            <AppPagination v-if="finalizationsTotalPages > 1" :page="finalizationsPage" :total-pages="finalizationsTotalPages" v-on:change="goToFinalizationsPage" />
        </section>

        <!-- Visitor comments -->
        <section v-if="galleryRef.allowVisitorComments || comments.length" class="space-y-3 pt-4 border-t border-line">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                <MessageSquare class="w-4 h-4 text-accent-500" :stroke-width="2" />
                {{ t("photo.galleries.admin.comments.title") }}
                <span class="text-muted font-normal">({{ comments.length }})</span>
            </h2>
            <p v-if="!comments.length" class="text-sm text-muted">
                {{ t("photo.galleries.admin.comments.empty") }}
            </p>
            <ul v-else class="space-y-2">
                <li v-for="comment in comments" :key="comment.id" class="bg-surface border border-line rounded-lg p-3 flex items-start gap-3">
                    <div v-if="itemPreview(comment.itemId)" class="relative shrink-0">
                        <img
                            :src="itemPreview(comment.itemId)"
                            class="w-12 h-12 rounded object-cover"
                            alt=""
                        >
                        <span v-if="itemById(comment.itemId)?.number" class="absolute -top-1 -right-1 px-1 py-px rounded bg-black/70 text-white text-[9px] font-semibold tabular-nums">
                            #{{ itemById(comment.itemId).number }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-primary whitespace-pre-wrap">{{ comment.content }}</p>
                        <p class="text-xs text-muted mt-1">
                            <span v-if="comment.visitorName">{{ comment.visitorName }}</span>
                            <span v-if="comment.visitorEmail" class="ml-1">&lt;{{ comment.visitorEmail }}&gt;</span>
                            <span v-if="!comment.visitorName && !comment.visitorEmail" class="italic">{{ t("photo.galleries.admin.comments.anonymous") }}</span>
                            <span class="ml-2">· {{ new Date(comment.createdAt).toLocaleString() }}</span>
                        </p>
                    </div>
                    <AppIconButton color="rose" :title="t('photo.galleries.admin.comments.delete')" v-on:click="askDeleteComment(comment)">
                        <Trash2 class="w-4 h-4" :stroke-width="2" />
                    </AppIconButton>
                </li>
            </ul>
        </section>

        <!-- Reopen confirmation -->
        <AppModal
            :show="showReopenModal"
            max-width="sm"
            :title="t('photo.galleries.admin.reopen')"
            :icon="AlertCircle"
            :closeable="false"
            v-on:close="showReopenModal = false"
        >
            <p class="text-sm text-secondary">{{ t("photo.galleries.admin.reopenConfirm") }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showReopenModal = false"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="primary" size="md" :loading="reopenLoading" v-on:click="confirmReopen">
                        <Unlock class="w-4 h-4" :stroke-width="2" />
                        {{ t("photo.galleries.admin.reopen") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Invite delete confirmation -->
        <AppModal
            :show="!!pendingInviteDelete"
            max-width="sm"
            :title="t('photo.galleries.admin.invites.deleteConfirmTitle')"
            :icon="Trash2"
            :closeable="false"
            v-on:close="pendingInviteDelete = null"
        >
            <p class="text-sm text-secondary">{{ t("photo.galleries.admin.invites.deleteConfirm", { name: pendingInviteDelete?.name }) }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingInviteDelete = null"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="danger" size="md" :loading="inviteDeleting" v-on:click="confirmDeleteInvite"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.delete") }}</AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Finalization reopen confirmation -->
        <AppModal
            :show="!!pendingFinalizationDelete"
            max-width="sm"
            :title="t('photo.galleries.admin.finalizations.reopen')"
            :icon="AlertCircle"
            :closeable="false"
            v-on:close="pendingFinalizationDelete = null"
        >
            <p class="text-sm text-secondary">{{ t("photo.galleries.admin.finalizations.reopenConfirm") }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingFinalizationDelete = null"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="primary" size="md" :loading="finalizationDeleteLoading" v-on:click="confirmDeleteFinalization">
                        <Unlock class="w-4 h-4" :stroke-width="2" />
                        {{ t("photo.galleries.admin.finalizations.reopen") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Comment delete confirmation -->
        <AppModal
            :show="!!pendingCommentDelete"
            max-width="sm"
            :title="t('photo.galleries.admin.comments.delete')"
            :icon="Trash2"
            :closeable="false"
            v-on:close="pendingCommentDelete = null"
        >
            <p class="text-sm text-secondary">{{ t("photo.galleries.admin.comments.deleteConfirm") }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingCommentDelete = null"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="danger" size="md" :loading="commentDeleteLoading" v-on:click="confirmDeleteComment"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.delete") }}</AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Single delete confirmation -->
        <AppModal
            :show="!!pendingDeleteItem"
            max-width="sm"
            :closeable="false"
            :title="t('shared.common.delete')"
            :icon="Trash2"
            v-on:close="pendingDeleteItem = null"
        >
            <p class="text-sm text-primary">{{ t("photo.galleries.itemDeleteConfirm") }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingDeleteItem = null"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="danger" size="md" :loading="deleteOneLoading" v-on:click="confirmDeleteOne"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.delete") }}</AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Bulk delete confirmation -->
        <AppModal
            :show="pendingBulkDelete"
            max-width="sm"
            :closeable="false"
            :title="t('shared.common.delete')"
            :icon="Trash2"
            v-on:close="pendingBulkDelete = false"
        >
            <p class="text-sm text-primary">{{ t("photo.galleries.itemsBulkDeleteConfirm", { count: selected.size }) }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingBulkDelete = false"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="danger" size="md" :loading="bulkDeleteLoading" v-on:click="confirmBulkDelete"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.delete") }}</AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
    </div>
</template>
