import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { openMediaPicker } from "@/shared/utils/mediaPicker.js";

export function useGalleryEditItems(props, initialItems) {
    const { t } = useI18n();
    const { request } = useRequest();

    const items = ref([...initialItems]);
    const selected = ref(new Set());

    const allSelected = computed(
        () =>
            items.value.length > 0 &&
            selected.value.size === items.value.length,
    );

    function toggleSelect(itemId) {
        if (selected.value.has(itemId)) selected.value.delete(itemId);
        else selected.value.add(itemId);
        selected.value = new Set(selected.value);
    }

    function toggleSelectAll() {
        if (allSelected.value) selected.value = new Set();
        else selected.value = new Set(items.value.map((i) => i.id));
    }

    function itemById(itemId) {
        return items.value.find((i) => i.id === itemId);
    }

    function itemPreview(itemId) {
        return items.value.find((i) => i.id === itemId)?.thumb ?? null;
    }

    // ── Add photos ────────────────────────────────────────────────────────────
    async function addPhotos() {
        const picked = await openMediaPicker({
            imagesOnly: true,
            multiple: true,
        });
        if (!Array.isArray(picked) || picked.length === 0) return;
        const data = await request(props.itemsAddPath, {
            mediaIds: picked.map((m) => m.id),
        });
        if (!data) return;
        if (!data.success) {
            toast.error(t("shared.common.error"));
            return;
        }
        items.value = data.items ?? items.value;
        toast.success(t("photo.galleries.itemsAdded", { count: data.added }));
    }

    // ── Single delete ─────────────────────────────────────────────────────────
    const pendingDeleteItem = ref(null);
    const deleteOneLoading = ref(false);

    function askDeleteOne(item) {
        pendingDeleteItem.value = item;
    }

    async function confirmDeleteOne() {
        if (!pendingDeleteItem.value || deleteOneLoading.value) return;
        deleteOneLoading.value = true;
        const item = pendingDeleteItem.value;
        const data = await request(
            buildPath(props.itemsDeletePath, { id: item.id }),
        );
        deleteOneLoading.value = false;
        if (!data) return;
        if (data.success) {
            items.value = items.value.filter((i) => i.id !== item.id);
            selected.value.delete(item.id);
            selected.value = new Set(selected.value);
            pendingDeleteItem.value = null;
            toast.success(t("photo.galleries.itemsDeleted", { count: 1 }));
        } else {
            toast.error(t("shared.common.error"));
        }
    }

    // ── Bulk delete ───────────────────────────────────────────────────────────
    const pendingBulkDelete = ref(false);
    const bulkDeleteLoading = ref(false);

    function askBulkDelete() {
        if (selected.value.size === 0) return;
        pendingBulkDelete.value = true;
    }

    async function confirmBulkDelete() {
        if (bulkDeleteLoading.value) return;
        const ids = Array.from(selected.value);
        if (ids.length === 0) {
            pendingBulkDelete.value = false;
            return;
        }
        bulkDeleteLoading.value = true;
        const data = await request(props.itemsBulkDeletePath, { itemIds: ids });
        bulkDeleteLoading.value = false;
        if (!data) return;
        if (data.success) {
            items.value = items.value.filter((i) => !selected.value.has(i.id));
            selected.value = new Set();
            pendingBulkDelete.value = false;
            toast.success(
                t("photo.galleries.itemsDeleted", { count: data.deleted }),
            );
        } else {
            toast.error(t("shared.common.error"));
        }
    }

    // ── Drag-and-drop reorder ─────────────────────────────────────────────────
    let draggingIndex = null;

    function onDragStart(index, event) {
        draggingIndex = index;
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", String(index));
    }

    function onDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
    }

    async function onDrop(targetIndex) {
        if (draggingIndex === null || draggingIndex === targetIndex) return;
        const reordered = [...items.value];
        const [moved] = reordered.splice(draggingIndex, 1);
        reordered.splice(targetIndex, 0, moved);
        items.value = reordered;
        draggingIndex = null;
        await request(props.itemsReorderPath, {
            itemIds: reordered.map((i) => i.id),
        });
    }

    return {
        items,
        selected,
        allSelected,
        toggleSelect,
        toggleSelectAll,
        itemById,
        itemPreview,
        addPhotos,
        pendingDeleteItem,
        deleteOneLoading,
        askDeleteOne,
        confirmDeleteOne,
        pendingBulkDelete,
        bulkDeleteLoading,
        askBulkDelete,
        confirmBulkDelete,
        onDragStart,
        onDragOver,
        onDrop,
    };
}
