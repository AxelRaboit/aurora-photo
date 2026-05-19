import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";

export function useGalleryEditComments(commentDeletePath, comments) {
    const { t } = useI18n();

    const pendingCommentDelete = ref(null);
    const { loading: commentDeleteLoading, request } = useRequest();

    function askDeleteComment(comment) {
        if (!commentDeletePath) return;
        pendingCommentDelete.value = comment;
    }

    async function confirmDeleteComment() {
        if (!pendingCommentDelete.value) return;
        const comment = pendingCommentDelete.value;
        const url = commentDeletePath.replace("__id__", comment.id);
        const data = await request(url);
        if (!data) return;
        if (data?.success) {
            comments.value = comments.value.filter((c) => c.id !== comment.id);
            pendingCommentDelete.value = null;
            toast.success(t("photo.galleries.admin.comments.deleted"));
        } else {
            toast.error(t("shared.common.error"));
        }
    }

    function commentsForItem(itemId) {
        return comments.value.filter((c) => c.itemId === itemId);
    }

    return {
        pendingCommentDelete,
        commentDeleteLoading,
        askDeleteComment,
        confirmDeleteComment,
        commentsForItem,
    };
}
