import { ref, computed } from "vue";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { useRequest } from "@/shared/composables/http/frontend/useRequest.js";

export const KIND = {
    Favorite: "favorite",
    Print: "print",
    Discard: "discard",
};

export function useGalleryPicks({
    pickPath,
    gallery,
    visitorPicks,
    favoriteCount,
    finalized,
    identityKnown,
    pendingPick,
    showIdentityModal,
}) {
    const { t } = useI18n();

    function buildPickedSets(initial) {
        const sets = {
            favorite: new Set(),
            print: new Set(),
            discard: new Set(),
        };
        for (const [id, kinds] of Object.entries(initial ?? {})) {
            const itemId = Number(id);
            for (const kind of kinds) {
                if (sets[kind]) sets[kind].add(itemId);
            }
        }
        return sets;
    }

    const picked = ref(buildPickedSets(visitorPicks));
    const favoriteTotal = ref(favoriteCount);
    const maxPicks = computed(() => gallery.maxPicks ?? null);
    const favoriteCountReached = computed(
        () => maxPicks.value !== null && favoriteTotal.value >= maxPicks.value,
    );

    function isPicked(id, kind = KIND.Favorite) {
        return picked.value[kind]?.has(Number(id)) ?? false;
    }

    const { request: requestToggle } = useRequest();

    async function sendToggle(
        itemId,
        kind,
        { name = null, email = null } = {},
    ) {
        const data = await requestToggle(buildPath(pickPath, { id: itemId }), {
            name,
            email,
            kind,
        });
        if (!data) {
            toast.error(t("shared.common.error"));
            return;
        }
        if (data?.error === "identity_required") {
            pendingPick.value = { itemId, kind };
            showIdentityModal.value = true;
            identityKnown.value = false;
            return;
        }
        if (data?.error === "max_picks_reached") {
            toast.error(
                t("photo.galleries.errors.max_picks_reached", {
                    limit: data.limit,
                }),
            );
            return;
        }
        if (data?.error === "finalized") {
            finalized.value = true;
            toast.info(t("photo.frontend.already_finalized"));
            return;
        }
        if (!data?.success) {
            toast.error(t("shared.common.error"));
            return;
        }
        const set = picked.value[kind];
        if (data.picked) set.add(Number(itemId));
        else set.delete(Number(itemId));
        picked.value = { ...picked.value, [kind]: new Set(set) };
        if (typeof data.favoriteCount === "number")
            favoriteTotal.value = data.favoriteCount;
    }

    async function togglePick(itemId, kind = KIND.Favorite) {
        if (finalized.value) {
            toast.info(t("photo.frontend.already_finalized"));
            return;
        }
        if (gallery.picksRequireIdentity && !identityKnown.value) {
            pendingPick.value = { itemId, kind };
            showIdentityModal.value = true;
            return;
        }
        await sendToggle(itemId, kind);
    }

    return {
        picked,
        favoriteTotal,
        maxPicks,
        favoriteCountReached,
        isPicked,
        togglePick,
        sendToggle,
    };
}
