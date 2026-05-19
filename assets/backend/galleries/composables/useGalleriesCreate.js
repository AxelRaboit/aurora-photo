import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { useFormAction } from "@/shared/composables/form/useFormAction.js";
import { required } from "@/shared/utils/validation/validators.js";
import { emptyGalleryForm, slugify } from "./useGalleryForm.js";

export function useGalleriesCreate(createPath, reload) {
    const { t } = useI18n();

    const showCreate = ref(false);
    const newForm = ref(emptyGalleryForm());
    const slugManuallyEdited = ref(false);

    const {
        errors: createErrors,
        loading: createLoading,
        submit: submitCreate,
        clearErrors,
    } = useFormAction({
        rules: () => ({
            title: () =>
                required(t("photo.galleries.errors.title_required"))(
                    newForm.value.title,
                ),
            slug: () =>
                required(t("photo.galleries.errors.slug_required"))(
                    newForm.value.slug,
                ),
        }),
        url: () => createPath,
        body: () => newForm.value,
        onSuccess: () => {
            toast.success(t("photo.galleries.created"));
            showCreate.value = false;
            reload();
        },
    });

    function openCreate() {
        newForm.value = emptyGalleryForm();
        slugManuallyEdited.value = false;
        clearErrors();
        showCreate.value = true;
    }

    function onCreateTitleChange(value) {
        newForm.value.title = value;
        if (!slugManuallyEdited.value) newForm.value.slug = slugify(value);
    }

    function onCreateSlugInput(value) {
        newForm.value.slug = value;
        slugManuallyEdited.value = true;
    }

    return {
        showCreate,
        newForm,
        createErrors,
        createLoading,
        openCreate,
        onCreateTitleChange,
        onCreateSlugInput,
        submitCreate,
    };
}
