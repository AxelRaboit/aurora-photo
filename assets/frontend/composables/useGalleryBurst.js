import { computed } from "vue";

const BURST_THRESHOLD_MS = 2000;

export function useGalleryBurst(items) {
    const burstMap = computed(() => {
        const map = new Map();
        const counts = new Map();
        let currentBurstId = 0;
        let currentBurstStart = null;
        let prevTime = null;

        for (const item of items) {
            const t = item.takenAt ? new Date(item.takenAt).getTime() : NaN;
            if (
                Number.isFinite(t) &&
                Number.isFinite(prevTime) &&
                t - prevTime <= BURST_THRESHOLD_MS
            ) {
                if (currentBurstId === 0) {
                    currentBurstId = counts.size + 1;
                    map.set(currentBurstStart, currentBurstId);
                    counts.set(currentBurstId, 1);
                }
                map.set(item.id, currentBurstId);
                counts.set(
                    currentBurstId,
                    (counts.get(currentBurstId) ?? 0) + 1,
                );
            } else {
                currentBurstId = 0;
                currentBurstStart = item.id;
            }
            prevTime = t;
        }
        return { ids: map, counts };
    });

    function burstIdOf(item) {
        return burstMap.value.ids.get(item.id) ?? null;
    }
    function burstSizeOf(burstId) {
        return burstMap.value.counts.get(burstId) ?? 0;
    }
    function burstIndexOf(item, burstId) {
        let i = 0;
        for (const it of items) {
            if (burstMap.value.ids.get(it.id) === burstId) {
                i++;
                if (it.id === item.id) return i;
            }
        }
        return 0;
    }

    return { burstMap, burstIdOf, burstSizeOf, burstIndexOf };
}
