import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createTestI18n } from "@/tests/helpers/createTestI18n.js";

vi.mock("vue-sonner", () => ({
    toast: { error: vi.fn(), success: vi.fn() },
}));

import GalleryUnlockApp from "./GalleryUnlockApp.vue";

const messages = {
    photo: {
        frontend: {
            unlock: {
                subtitle: "Saisissez le mot de passe",
                password: "Mot de passe",
                submit: "Accéder",
                invalid: "Mot de passe incorrect.",
                required: "Veuillez saisir le mot de passe.",
            },
        },
    },
    shared: { common: { error: "Erreur" } },
};

function mountUnlock() {
    return mount(GalleryUnlockApp, {
        props: {
            gallery: { id: 1, title: "Wedding 2026" },
            unlockPath: "/g/wedding-2026/unlock",
        },
        global: { plugins: [createTestI18n(messages)] },
    });
}

describe("GalleryUnlockApp", () => {
    let originalAssign;

    beforeEach(() => {
        vi.restoreAllMocks();
        originalAssign = window.location.assign;
        Object.defineProperty(window, "location", {
            configurable: true,
            value: { ...window.location, assign: vi.fn() },
        });
    });

    it("renders the gallery title and subtitle", () => {
        const wrapper = mountUnlock();
        expect(wrapper.text()).toContain("Wedding 2026");
        expect(wrapper.text()).toContain("Saisissez le mot de passe");
    });

    it("blocks submit and shows required error when password is empty", async () => {
        const fetchMock = vi.fn();
        global.fetch = fetchMock;

        const wrapper = mountUnlock();
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("Veuillez saisir le mot de passe.");
    });

    it("posts password and redirects on success", async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                redirectUrl: "/g/wedding-2026",
            }),
        });
        global.fetch = fetchMock;

        const wrapper = mountUnlock();
        await wrapper.find('input[type="password"]').setValue("secret");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, opts] = fetchMock.mock.calls[0];
        expect(url).toBe("/g/wedding-2026/unlock");
        expect(opts.method).toBe("POST");
        expect(JSON.parse(opts.body)).toEqual({ password: "secret" });
        expect(window.location.assign).toHaveBeenCalledWith("/g/wedding-2026");
    });

    it("displays the invalid translation on 401", async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: false,
            status: 401,
            json: async () => ({
                success: false,
                error: "photo.frontend.unlock.invalid",
            }),
        });

        const wrapper = mountUnlock();
        await wrapper.find('input[type="password"]').setValue("wrong");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();

        expect(wrapper.text()).toContain("Mot de passe incorrect.");
        expect(window.location.assign).not.toHaveBeenCalled();
    });
});
