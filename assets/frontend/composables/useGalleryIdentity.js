import { ref } from "vue";

export function useGalleryIdentity(visitorIdentity) {
    const visitorName = ref(visitorIdentity?.name ?? "");
    const visitorEmail = ref(visitorIdentity?.email ?? "");
    const identityKnown = ref(Boolean(visitorName.value && visitorEmail.value));
    const showIdentityModal = ref(false);
    const pendingPick = ref(null);

    async function submitIdentity(sendToggle) {
        if (!visitorName.value.trim() || !visitorEmail.value.trim()) return;
        identityKnown.value = true;
        showIdentityModal.value = false;
        if (pendingPick.value !== null) {
            const { itemId, kind } = pendingPick.value;
            await sendToggle(itemId, kind, {
                name: visitorName.value,
                email: visitorEmail.value,
            });
            pendingPick.value = null;
        }
    }

    return {
        visitorName,
        visitorEmail,
        identityKnown,
        showIdentityModal,
        pendingPick,
        submitIdentity,
    };
}
