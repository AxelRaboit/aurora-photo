import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";

export function useGalleryEditReopen(reopenPath, galleryRef) {
    const { t } = useI18n();

    const showReopenModal = ref(false);
    const { loading: reopenLoading, request } = useRequest();

    function askReopen() {
        if (!reopenPath) return;
        showReopenModal.value = true;
    }

    async function confirmReopen() {
        if (!reopenPath) return;
        const data = await request(reopenPath);
        if (!data) return;
        if (data?.success) {
            galleryRef.value = data.gallery;
            showReopenModal.value = false;
            toast.success(t("photo.galleries.admin.reopened"));
        } else {
            toast.error(t("shared.common.error"));
        }
    }

    return { showReopenModal, reopenLoading, askReopen, confirmReopen };
}
