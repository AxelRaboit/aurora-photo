<script setup>
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { useI18n } from "vue-i18n";
import { useListPage } from "@/shared/composables/list/useListPage.js";
import { useDateFormat } from "@/shared/composables/format/useDateFormat.js";
import { useContactSearch } from "@photo/backend/composables/useContactSearch.js";
import { galleryCoverState, onGalleryCoverChange, isExpiryInPast } from "@photo/backend/galleries/composables/useGalleryForm.js";
import { useDelete } from "@/shared/composables/form/useDelete.js";
import { useGalleriesCreate } from "@photo/backend/galleries/composables/useGalleriesCreate.js";
import { useGalleriesEdit } from "@photo/backend/galleries/composables/useGalleriesEdit.js";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppIconButton from "@/shared/components/action/AppIconButton.vue";
import AppListItemButton from "@/shared/components/action/AppListItemButton.vue";
import AppTextLinkButton from "@/shared/components/action/AppTextLinkButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppToggle from "@/shared/components/form/toggle/AppToggle.vue";
import AppDatePicker from "@/shared/components/form/picker/AppDatePicker.vue";
import AppImagePickerField from "@/shared/components/form/file/AppImagePickerField.vue";
import AppTextarea from "@/shared/components/form/input/AppTextarea.vue";
import AppSearchInput from "@/shared/components/form/input/AppSearchInput.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import AppPagination from "@/shared/components/nav/AppPagination.vue";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import AppBadge from "@/shared/components/feedback/AppBadge.vue";
import AppLoader from "@/shared/components/feedback/AppLoader.vue";
import { Plus, Pencil, Trash2, Save, Lock, Eye, EyeOff, CheckCircle, Image as ImageIcon, User, X, Images } from "lucide-vue-next";
import { usePrivileges } from "@/shared/composables/usePrivileges.js";

const { t } = useI18n();
const { can } = usePrivileges();
const { formatDateShort } = useDateFormat();

const props = defineProps({
    galleries: { type: Object, default: () => ({}) },
    search: { type: String, default: "" },
    listPath: { type: String, required: true },
    createPath: { type: String, required: true },
    updatePath: { type: String, required: true },
    deletePath: { type: String, required: true },
    editPath: { type: String, default: "" },
    crmEnabled: { type: Boolean, default: false },
    contactsSearchPath: { type: String, default: "" },
});

const { items, loading, page, totalPages, search: searchInput, onSearch, goToPage, reload } = useListPage(
    props.listPath,
    { initialSearch: props.search, initialData: props.galleries },
);

const { contactSearchQuery, contactSearchResults, contactSearchOpen, onContactQueryInput, selectContact, clearContact } =
    useContactSearch(props.contactsSearchPath);

const { showCreate, newForm, createErrors, createLoading, openCreate, onCreateTitleChange, onCreateSlugInput, submitCreate } =
    useGalleriesCreate(props.createPath, reload);

const { showEdit, editForm, editingHasPassword, editErrors, editLoading, openGallery, openEdit, submitEdit } =
    useGalleriesEdit(props, reload);

const { pendingDelete, loading: deleteLoading, confirm: confirmDelete, submit: doDelete } =
    useDelete(props.deletePath, () => reload(), "photo.galleries.deleted");

const coverState = galleryCoverState;
const onCoverChange = onGalleryCoverChange;
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <AppSearchInput
                :model-value="searchInput"
                :placeholder="t('photo.galleries.searchPlaceholder')"
                class="flex-1"
                v-on:update:model-value="onSearch"
            />
            <AppButton v-if="can('photo.galleries.create')" variant="primary" v-on:click="openCreate">
                <Plus class="w-4 h-4" :stroke-width="2" />
                {{ t("photo.galleries.add") }}
            </AppButton>
        </div>

        <div class="relative space-y-4">
            <AppNoData v-if="!items.length">
                {{ t("photo.galleries.empty") }}
            </AppNoData>

            <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                <article
                    v-for="g in items"
                    :key="g.id"
                    class="bg-surface border border-line rounded-lg overflow-hidden hover:border-accent transition-colors"
                    :class="{ 'cursor-pointer': editPath }"
                    v-on:click="editPath && openGallery(g)"
                >
                    <div class="aspect-4/3 bg-surface-2 relative overflow-hidden">
                        <img v-if="g.coverMediaUrl" :src="g.coverMediaUrl" :alt="g.title" class="w-full h-full object-cover">
                        <div v-else class="w-full h-full flex items-center justify-center text-muted">
                            <ImageIcon class="w-7 h-7" :stroke-width="1.5" />
                        </div>
                        <div class="absolute top-1.5 right-1.5 flex gap-1">
                            <AppBadge v-if="g.hasPassword" variant="warning" size="sm">
                                <Lock class="w-3 h-3" :stroke-width="2.5" />
                            </AppBadge>
                            <AppBadge v-if="g.finalizedAt" color="emerald" size="sm">
                                <CheckCircle class="w-3 h-3" :stroke-width="2.5" />
                            </AppBadge>
                        </div>
                    </div>
                    <div class="p-2.5 space-y-1">
                        <h3 class="text-sm font-semibold text-primary truncate">{{ g.title }}</h3>
                        <p class="text-xs text-muted truncate">/{{ g.slug }} · {{ g.itemCount }}</p>
                        <p v-if="g.client" class="text-xs text-muted flex items-center gap-1">
                            <User class="w-3 h-3 shrink-0" :stroke-width="2" />
                            <span class="truncate">{{ g.client.name }}</span>
                        </p>
                        <div class="flex items-center gap-0.5 pt-1" v-on:click.stop>
                            <AppIconButton v-if="editPath" color="sky" :title="t('photo.galleries.openEditor')" :href="buildPath(editPath, { id: g.id })">
                                <ImageIcon class="w-3.5 h-3.5" :stroke-width="2" />
                            </AppIconButton>
                            <AppIconButton v-if="can('photo.galleries.edit')" :title="t('shared.common.edit')" v-on:click="openEdit(g)">
                                <Pencil class="w-3.5 h-3.5" :stroke-width="2" />
                            </AppIconButton>
                            <AppIconButton v-if="can('photo.galleries.delete')" color="rose" :title="t('shared.common.delete')" v-on:click="confirmDelete(g)">
                                <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                            </AppIconButton>
                        </div>
                    </div>
                </article>
            </div>

            <AppPagination v-if="totalPages > 1" :page="page" :total-pages="totalPages" v-on:change="goToPage" />
            <AppLoader :active="loading" />
        </div>

        <AppModal
            :show="showCreate"
            :title="t('photo.galleries.create')"
            :icon="Images"
            :closeable="false"
            v-on:close="showCreate = false"
        >
            <form class="space-y-4" v-on:submit.prevent="submitCreate">
                <AppImagePickerField
                    :label="t('photo.galleries.fields.coverMedia')"
                    :hint="t('photo.galleries.fields.coverMediaHint')"
                    :model-value="coverState(newForm)"
                    :size="120"
                    v-on:update:model-value="onCoverChange(newForm, $event)"
                />
                <AppInput
                    :label="t('photo.galleries.fields.title')"
                    :placeholder="t('photo.galleries.fields.titlePlaceholder')"
                    :model-value="newForm.title"
                    :error="createErrors.title"
                    required
                    v-on:update:model-value="onCreateTitleChange"
                />
                <AppInput
                    :label="t('photo.galleries.fields.slug')"
                    :placeholder="t('photo.galleries.fields.slugPlaceholder')"
                    :model-value="newForm.slug"
                    :error="createErrors.slug"
                    :hint="t('photo.galleries.fields.slugHint')"
                    required
                    v-on:update:model-value="onCreateSlugInput"
                />
                <AppTextarea
                    v-model="newForm.description"
                    :label="t('photo.galleries.fields.description')"
                    :placeholder="t('photo.galleries.fields.descriptionPlaceholder')"
                    :rows="3"
                />
                <AppInput
                    v-model="newForm.password"
                    type="password"
                    :toggleable="true"
                    :label="t('photo.galleries.fields.password')"
                    :placeholder="t('photo.galleries.fields.passwordPlaceholder')"
                    :hint="t('photo.galleries.fields.passwordHint')"
                    autocomplete="new-password"
                />
                <div>
                    <AppDatePicker
                        v-model="newForm.expiresAt"
                        :label="t('photo.galleries.fields.expiresAt')"
                        :placeholder="t('photo.galleries.fields.expiresAtPlaceholder')"
                    />
                    <p v-if="isExpiryInPast(newForm.expiresAt)" class="mt-1 text-xs text-amber-500">
                        ⚠ {{ t("photo.galleries.fields.expiresAtPastWarning") }}
                    </p>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.allowOriginals") }}</span>
                    <AppToggle v-model="newForm.allowOriginals" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.allowZipDownload") }}</span>
                    <AppToggle v-model="newForm.allowZipDownload" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.picksRequireIdentity") }}</span>
                    <AppToggle v-model="newForm.picksRequireIdentity" />
                </div>
                <AppInput
                    v-model="newForm.maxPicks"
                    type="number"
                    min="1"
                    :label="t('photo.galleries.fields.maxPicks')"
                    :placeholder="t('photo.galleries.fields.maxPicksPlaceholder')"
                    :hint="t('photo.galleries.fields.maxPicksHint')"
                />
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.allowVisitorComments") }}</span>
                    <AppToggle v-model="newForm.allowVisitorComments" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.watermarkEnabled") }}</span>
                    <AppToggle v-model="newForm.watermarkEnabled" />
                </div>
                <AppInput
                    v-if="newForm.watermarkEnabled"
                    v-model="newForm.watermarkText"
                    :label="t('photo.galleries.fields.watermarkText')"
                    :placeholder="t('photo.galleries.fields.watermarkTextPlaceholder')"
                    :hint="t('photo.galleries.fields.watermarkHint')"
                    maxlength="100"
                />
                <div v-if="crmEnabled">
                    <p class="text-xs font-medium text-secondary uppercase tracking-wide mb-1.5">{{ t("photo.galleries.fields.clientContact") }}</p>
                    <div v-if="newForm.clientLabel" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-surface-2 border border-line">
                        <User class="w-4 h-4 text-accent-500 shrink-0" :stroke-width="2" />
                        <span class="flex-1 text-sm text-primary truncate">{{ newForm.clientLabel }}</span>
                        <AppIconButton color="rose" :title="t('shared.common.remove')" v-on:click="clearContact(newForm)"><X class="w-4 h-4" :stroke-width="2" /></AppIconButton>
                    </div>
                    <div v-else class="relative">
                        <AppInput
                            type="text"
                            :placeholder="t('photo.galleries.fields.clientContactPlaceholder')"
                            :model-value="contactSearchQuery"
                            v-on:update:model-value="onContactQueryInput(newForm, $event)"
                        />
                        <ul v-if="contactSearchOpen && contactSearchResults.length" class="absolute z-20 left-0 right-0 mt-1 bg-surface border border-line rounded-lg shadow-lg overflow-hidden max-h-60 overflow-y-auto">
                            <li v-for="c in contactSearchResults" :key="c.id">
                                <AppListItemButton v-on:click="selectContact(c)">
                                    <span class="font-medium">{{ c.fullName ?? (c.firstName + ' ' + c.lastName) }}</span>
                                    <template v-if="c.email" #meta>{{ c.email }}</template>
                                </AppListItemButton>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" type="button" v-on:click="showCreate = false"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton type="submit" variant="primary" size="md" :loading="createLoading">
                        <Save class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.create") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <!-- Edit modal -->
        <AppModal
            :show="showEdit"
            :title="t('photo.galleries.edit')"
            :icon="Pencil"
            :closeable="false"
            v-on:close="showEdit = false"
        >
            <form class="space-y-4" v-on:submit.prevent="submitEdit">
                <AppImagePickerField
                    :label="t('photo.galleries.fields.coverMedia')"
                    :hint="t('photo.galleries.fields.coverMediaHint')"
                    :model-value="coverState(editForm)"
                    :size="120"
                    v-on:update:model-value="onCoverChange(editForm, $event)"
                />
                <AppInput
                    v-model="editForm.title"
                    :label="t('photo.galleries.fields.title')"
                    :placeholder="t('photo.galleries.fields.titlePlaceholder')"
                    :error="editErrors.title"
                    required
                />
                <AppInput
                    v-model="editForm.slug"
                    :label="t('photo.galleries.fields.slug')"
                    :placeholder="t('photo.galleries.fields.slugPlaceholder')"
                    :error="editErrors.slug"
                    required
                />
                <AppTextarea
                    v-model="editForm.description"
                    :label="t('photo.galleries.fields.description')"
                    :placeholder="t('photo.galleries.fields.descriptionPlaceholder')"
                    :rows="3"
                />
                <div v-if="editingHasPassword && !editForm.clearPassword" class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-primary"><Lock class="w-4 h-4" :stroke-width="2" />{{ t("photo.galleries.passwordSet") }}</span>
                    <AppTextLinkButton color="danger" size="xs" v-on:click="editForm.clearPassword = true">{{ t("photo.galleries.clearPassword") }}</AppTextLinkButton>
                </div>
                <AppInput
                    v-model="editForm.password"
                    type="password"
                    :toggleable="true"
                    :label="editingHasPassword ? t('photo.galleries.fields.passwordChange') : t('photo.galleries.fields.password')"
                    :placeholder="t('photo.galleries.fields.passwordPlaceholder')"
                    :hint="t('photo.galleries.fields.passwordHint')"
                    autocomplete="new-password"
                />
                <div>
                    <AppDatePicker
                        v-model="editForm.expiresAt"
                        :label="t('photo.galleries.fields.expiresAt')"
                        :placeholder="t('photo.galleries.fields.expiresAtPlaceholder')"
                    />
                    <p v-if="isExpiryInPast(editForm.expiresAt)" class="mt-1 text-xs text-amber-500">
                        ⚠ {{ t("photo.galleries.fields.expiresAtPastWarning") }}
                    </p>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.allowOriginals") }}</span>
                    <AppToggle v-model="editForm.allowOriginals" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.allowZipDownload") }}</span>
                    <AppToggle v-model="editForm.allowZipDownload" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.picksRequireIdentity") }}</span>
                    <AppToggle v-model="editForm.picksRequireIdentity" />
                </div>
                <AppInput
                    v-model="editForm.maxPicks"
                    type="number"
                    min="1"
                    :label="t('photo.galleries.fields.maxPicks')"
                    :placeholder="t('photo.galleries.fields.maxPicksPlaceholder')"
                    :hint="t('photo.galleries.fields.maxPicksHint')"
                />
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.allowVisitorComments") }}</span>
                    <AppToggle v-model="editForm.allowVisitorComments" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-primary">{{ t("photo.galleries.fields.watermarkEnabled") }}</span>
                    <AppToggle v-model="editForm.watermarkEnabled" />
                </div>
                <AppInput
                    v-if="editForm.watermarkEnabled"
                    v-model="editForm.watermarkText"
                    :label="t('photo.galleries.fields.watermarkText')"
                    :placeholder="t('photo.galleries.fields.watermarkTextPlaceholder')"
                    :hint="t('photo.galleries.fields.watermarkHint')"
                    maxlength="100"
                />
                <div v-if="crmEnabled">
                    <p class="text-xs font-medium text-secondary uppercase tracking-wide mb-1.5">{{ t("photo.galleries.fields.clientContact") }}</p>
                    <div v-if="editForm.clientLabel" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-surface-2 border border-line">
                        <User class="w-4 h-4 text-accent-500 shrink-0" :stroke-width="2" />
                        <span class="flex-1 text-sm text-primary truncate">{{ editForm.clientLabel }}</span>
                        <AppIconButton color="rose" :title="t('shared.common.remove')" v-on:click="clearContact(editForm)"><X class="w-4 h-4" :stroke-width="2" /></AppIconButton>
                    </div>
                    <div v-else class="relative">
                        <AppInput
                            type="text"
                            :placeholder="t('photo.galleries.fields.clientContactPlaceholder')"
                            :model-value="contactSearchQuery"
                            v-on:update:model-value="onContactQueryInput(editForm, $event)"
                        />
                        <ul v-if="contactSearchOpen && contactSearchResults.length" class="absolute z-20 left-0 right-0 mt-1 bg-surface border border-line rounded-lg shadow-lg overflow-hidden max-h-60 overflow-y-auto">
                            <li v-for="c in contactSearchResults" :key="c.id">
                                <AppListItemButton v-on:click="selectContact(c)">
                                    <span class="font-medium">{{ c.fullName ?? (c.firstName + ' ' + c.lastName) }}</span>
                                    <template v-if="c.email" #meta>{{ c.email }}</template>
                                </AppListItemButton>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" type="button" v-on:click="showEdit = false"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton type="submit" variant="primary" size="md" :loading="editLoading">
                        <Save class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.save") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <AppModal
            :show="!!pendingDelete"
            max-width="sm"
            :closeable="false"
            :title="t('shared.common.delete')"
            :icon="Trash2"
            v-on:close="pendingDelete = null"
        >
            <p class="text-sm text-primary">{{ t("photo.galleries.deleteConfirm", { title: pendingDelete?.title ?? '' }) }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingDelete = null"><X class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.cancel") }}</AppButton>
                    <AppButton variant="danger" size="md" :loading="deleteLoading" v-on:click="doDelete"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.delete") }}</AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
    </div>
</template>
