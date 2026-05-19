import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { HttpMethod } from "@/shared/utils/http/httpMethod.js";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";

export function useGalleryEditCaption(itemsCaptionPath, items) {
    const { t } = useI18n();

    const { request } = useRequest();
    const editingCaptionId = ref(null);
    const editingCaptionDraft = ref("");

    function startCaption(item) {
        editingCaptionId.value = item.id;
        editingCaptionDraft.value = item.caption ?? "";
    }

    function cancelCaption() {
        editingCaptionId.value = null;
        editingCaptionDraft.value = "";
    }

    async function saveCaption(item) {
        const data = await request(
            buildPath(itemsCaptionPath, { id: item.id }),
            { caption: editingCaptionDraft.value },
            HttpMethod.Post,
        );
        if (data?.success) {
            const target = items.value.find((i) => i.id === item.id);
            if (target) target.caption = editingCaptionDraft.value || null;
            cancelCaption();
        }
    }

    return {
        editingCaptionId,
        editingCaptionDraft,
        startCaption,
        cancelCaption,
        saveCaption,
    };
}
