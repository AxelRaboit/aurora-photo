import { ref, onMounted, onBeforeUnmount } from "vue";

export function useGalleryLightbox(
    displayedItems,
    showCommentBox,
    commentDraft,
) {
    const lightboxIndex = ref(null);

    function openLightbox(index) {
        lightboxIndex.value = index;
        showCommentBox.value = false;
        commentDraft.value = "";
    }
    function closeLightbox() {
        lightboxIndex.value = null;
    }

    function prev() {
        if (lightboxIndex.value === null) return;
        const len = displayedItems.value.length;
        lightboxIndex.value = (lightboxIndex.value - 1 + len) % len;
        showCommentBox.value = false;
        commentDraft.value = "";
    }

    function next() {
        if (lightboxIndex.value === null) return;
        const len = displayedItems.value.length;
        lightboxIndex.value = (lightboxIndex.value + 1) % len;
        showCommentBox.value = false;
        commentDraft.value = "";
    }

    function onKeydown(event) {
        if (lightboxIndex.value === null) return;
        if (event.key === "Escape") closeLightbox();
        else if (event.key === "ArrowLeft") prev();
        else if (event.key === "ArrowRight") next();
    }

    onMounted(() => window.addEventListener("keydown", onKeydown));
    onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));

    return { lightboxIndex, openLightbox, closeLightbox, prev, next };
}
