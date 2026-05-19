import { ref, computed } from "vue";

export function useGalleryEditConsensus(initialPicks, items) {
    const picks = ref({ ...initialPicks });
    const sortByConsensus = ref(false);

    const visitorCount = computed(() => picks.value.visitorCount ?? 0);

    function consensusFavorite(itemId) {
        return picks.value.consensusByItemId?.[itemId]?.favorite ?? 0;
    }

    function pickKindCount(itemId, kind) {
        return picks.value.byItemId?.[itemId]?.[kind] ?? 0;
    }

    function pickCount(itemId) {
        return picks.value.byItemId?.[itemId] ?? 0;
    }

    const displayedItems = computed(() => {
        if (!sortByConsensus.value) return items.value;
        return [...items.value].sort((a, b) => {
            const diff = consensusFavorite(b.id) - consensusFavorite(a.id);
            if (diff !== 0) return diff;
            return a.position - b.position;
        });
    });

    return {
        picks,
        sortByConsensus,
        visitorCount,
        consensusFavorite,
        pickKindCount,
        pickCount,
        displayedItems,
    };
}
