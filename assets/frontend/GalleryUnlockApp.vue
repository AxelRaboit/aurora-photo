<script setup>
import { ref, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { KeyRound } from "lucide-vue-next";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { useForm } from "@/shared/composables/form/useForm.js";
import { required } from "@/shared/utils/validation/validators.js";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppButton from "@/shared/components/action/AppButton.vue";

const { t } = useI18n();

const props = defineProps({
    gallery: { type: Object, required: true },
    unlockPath: { type: String, required: true },
});

const password = ref("");
const passwordInput = ref(null);

const { errors, clearErrors, setErrors } = useForm();
const { loading, request } = useRequest();

onMounted(() => {
    passwordInput.value?.focus();
});

async function submit() {
    clearErrors();
    if (required("required")(password.value)) {
        setErrors({ password: "photo.frontend.unlock.required" });
        return;
    }

    const data = await request(props.unlockPath, { password: password.value });
    if (!data?.success) {
        setErrors({ password: data?.error ?? "photo.frontend.unlock.invalid" });
        passwordInput.value?.focus();
        passwordInput.value?.select();
        return;
    }

    window.location.assign(data.redirectUrl);
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md bg-surface border border-line rounded-xl shadow-md overflow-hidden">
            <div class="relative h-20 bg-linear-to-br from-accent-400 via-accent-500 to-accent-700">
                <div
                    class="absolute inset-0 opacity-25"
                    style="background-image: radial-gradient(circle at 25% 20%, rgba(255,255,255,0.9) 0%, transparent 45%);"
                />
                <div class="absolute inset-x-0 -bottom-6 flex justify-center">
                    <div class="w-12 h-12 rounded-2xl bg-surface border border-line shadow-md flex items-center justify-center text-accent-500">
                        <KeyRound class="w-6 h-6" />
                    </div>
                </div>
            </div>
            <div class="px-6 pb-6 pt-10">
                <div class="text-center mb-5">
                    <h1 class="text-primary text-xl font-bold leading-tight">{{ gallery.title }}</h1>
                    <p class="text-muted text-sm mt-1">{{ t("photo.frontend.unlock.subtitle") }}</p>
                </div>

                <form class="space-y-4" v-on:submit.prevent="submit">
                    <AppInput
                        ref="passwordInput"
                        v-model="password"
                        type="password"
                        name="password"
                        :label="t('photo.frontend.unlock.password')"
                        :error="errors.password"
                        toggleable
                        required
                    />
                    <AppButton type="submit" variant="primary" :loading="loading" class="w-full">
                        {{ t("photo.frontend.unlock.submit") }}
                    </AppButton>
                </form>
            </div>
        </div>
    </div>
</template>
