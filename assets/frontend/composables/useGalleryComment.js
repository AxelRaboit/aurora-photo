import { ref } from "vue";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { translateServerErrors } from "@/shared/utils/validation/translateServerErrors.js";
import { useRequest } from "@/shared/composables/http/frontend/useRequest.js";

// showCommentBox and commentDraft are passed in as refs (shared with useGalleryLightbox)
export function useGalleryComment({
    commentPath,
    displayedItems,
    lightboxIndex,
    showCommentBox,
    commentDraft,
    visitorName,
    visitorEmail,
    identityKnown,
}) {
    const { t } = useI18n();
    const { loading: commentSending, request: requestComment } = useRequest();

    const commentNameError = ref("");
    const commentEmailError = ref("");

    async function submitComment() {
        if (lightboxIndex.value === null) return;
        commentNameError.value = "";
        commentEmailError.value = "";
        if (!commentDraft.value.trim()) {
            toast.error(t("photo.frontend.comments.content_required"));
            return;
        }
        if (!visitorName.value.trim()) {
            commentNameError.value = t("photo.frontend.comments.name_required");
            return;
        }
        if (!visitorEmail.value.trim()) {
            commentEmailError.value = t(
                "photo.frontend.comments.email_required",
            );
            return;
        }
        const itemId = displayedItems.value[lightboxIndex.value].id;
        const data = await requestComment(
            buildPath(commentPath, { id: itemId }),
            {
                content: commentDraft.value,
                name: visitorName.value,
                email: visitorEmail.value,
            },
        );
        if (!data?.success) {
            const errors = translateServerErrors(t, data?.errors);
            if (errors.visitorEmail)
                commentEmailError.value = errors.visitorEmail;
            else toast.error(t("shared.common.error"));
            return;
        }
        identityKnown.value = true;
        toast.success(t("photo.frontend.comments.sent"));
        commentDraft.value = "";
        showCommentBox.value = false;
    }

    return {
        commentSending,
        commentNameError,
        commentEmailError,
        submitComment,
    };
}
