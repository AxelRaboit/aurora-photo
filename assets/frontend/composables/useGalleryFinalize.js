import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { translateServerErrors } from "@/shared/utils/validation/translateServerErrors.js";
import { useRequest } from "@/shared/composables/http/frontend/useRequest.js";

export function useGalleryFinalize({
    finalizePath,
    gallery,
    finalized,
    identityKnown,
    visitorName,
    visitorEmail,
}) {
    const { t } = useI18n();

    const { loading: finalizing, request: requestFinalize } = useRequest();

    const showFinalizeModal = ref(false);
    const finalizeName = ref("");
    const finalizeEmail = ref("");
    const finalizeNameError = ref("");
    const finalizeEmailError = ref("");

    function openFinalize() {
        if (gallery.picksRequireIdentity || identityKnown.value) {
            finalizeName.value = visitorName.value;
            finalizeEmail.value = visitorEmail.value;
        }
        showFinalizeModal.value = true;
    }

    async function submitFinalize() {
        finalizeNameError.value = "";
        finalizeEmailError.value = "";
        const data = await requestFinalize(finalizePath, {
            name: finalizeName.value,
            email: finalizeEmail.value,
        });
        if (!data?.success) {
            const fieldErrors = translateServerErrors(t, data?.errors);
            finalizeNameError.value = fieldErrors.name ?? "";
            finalizeEmailError.value = fieldErrors.email ?? "";
            if (!fieldErrors.name && !fieldErrors.email)
                toast.error(t("shared.common.error"));
            return;
        }
        finalized.value = true;
        showFinalizeModal.value = false;
        toast.success(t("photo.frontend.finalized_toast"));
    }

    return {
        showFinalizeModal,
        finalizeName,
        finalizeEmail,
        finalizing,
        finalizeNameError,
        finalizeEmailError,
        openFinalize,
        submitFinalize,
    };
}
