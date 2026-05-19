import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";

const COMPARE_MAX = 4;

export function useGalleryCompare(items) {
    const { t } = useI18n();
    const compareIds = ref([]);
    const showCompare = ref(false);

    function isCompared(itemId) {
        return compareIds.value.includes(Number(itemId));
    }

    function toggleCompare(itemId) {
        const id = Number(itemId);
        const idx = compareIds.value.indexOf(id);
        if (idx >= 0) {
            compareIds.value = compareIds.value.filter((x) => x !== id);
            return;
        }
        if (compareIds.value.length >= COMPARE_MAX) {
            toast.error(t("photo.frontend.compare.max", { max: COMPARE_MAX }));
            return;
        }
        compareIds.value = [...compareIds.value, id];
    }

    function clearCompare() {
        compareIds.value = [];
        showCompare.value = false;
    }

    const compareItems = computed(() =>
        compareIds.value
            .map((id) => items.find((i) => i.id === id))
            .filter(Boolean),
    );
    const compareGridClass = computed(() => {
        const n = compareItems.value.length;
        if (n <= 1) return "grid-cols-1";
        if (n === 2) return "grid-cols-2";
        return "grid-cols-2 grid-rows-2";
    });

    return {
        compareIds,
        showCompare,
        isCompared,
        toggleCompare,
        clearCompare,
        compareItems,
        compareGridClass,
    };
}
