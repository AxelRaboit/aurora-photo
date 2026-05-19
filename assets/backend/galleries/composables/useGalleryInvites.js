import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { translateServerErrors } from "@/shared/utils/validation/translateServerErrors.js";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { HttpMethod } from "@/shared/utils/http/httpMethod.js";

export function useGalleryInvites(paths, invites) {
    const { t } = useI18n();
    const { request } = useRequest();

    const inviteForm = ref({ name: "", email: "" });
    const inviteErrors = ref({ name: "", email: "" });
    const inviteCreating = ref(false);
    const inviteSendingId = ref(null);
    const pendingInviteDelete = ref(null);
    const inviteDeleting = ref(false);

    async function createInvite() {
        if (!paths.create || inviteCreating.value) return;
        inviteErrors.value = { name: "", email: "" };
        inviteCreating.value = true;
        try {
            const data = await request(paths.create, inviteForm.value);
            if (data?.success) {
                invites.value = data.invites;
                inviteForm.value = { name: "", email: "" };
                toast.success(t("photo.galleries.admin.invites.created"));
            } else if (data?.errors) {
                const errs = translateServerErrors(t, data.errors);
                inviteErrors.value = {
                    name: errs.name ?? "",
                    email: errs.email ?? "",
                };
                if (!errs.name && !errs.email)
                    toast.error(t("shared.common.error"));
            }
        } finally {
            inviteCreating.value = false;
        }
    }

    async function sendInvite(invite) {
        if (!paths.send || inviteSendingId.value) return;
        inviteSendingId.value = invite.id;
        try {
            const data = await request(paths.send.replace("__id__", invite.id));
            if (data?.success) {
                invites.value = data.invites;
                toast.success(t("photo.galleries.admin.invites.sent"));
            } else {
                toast.error(t("shared.common.error"));
            }
        } finally {
            inviteSendingId.value = null;
        }
    }

    function askDeleteInvite(invite) {
        if (!paths.delete) return;
        pendingInviteDelete.value = invite;
    }

    async function confirmDeleteInvite() {
        if (!pendingInviteDelete.value || inviteDeleting.value) return;
        inviteDeleting.value = true;
        const invite = pendingInviteDelete.value;
        try {
            const data = await request(
                paths.delete.replace("__id__", invite.id),
                null,
                HttpMethod.Delete,
            );
            if (data?.success) {
                invites.value =
                    data.invites ??
                    invites.value.filter((i) => i.id !== invite.id);
                pendingInviteDelete.value = null;
                toast.success(t("photo.galleries.admin.invites.deleted"));
            } else {
                toast.error(t("shared.common.error"));
            }
        } finally {
            inviteDeleting.value = false;
        }
    }

    return {
        inviteForm,
        inviteErrors,
        inviteCreating,
        inviteSendingId,
        pendingInviteDelete,
        inviteDeleting,
        createInvite,
        sendInvite,
        askDeleteInvite,
        confirmDeleteInvite,
    };
}
