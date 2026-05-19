import { ref } from "vue";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { useDebounce } from "@/shared/composables/useDebounce.js";
import { HttpMethod } from "@/shared/utils/http/httpMethod.js";

export function useContactSearch(searchPath) {
    const contactSearchQuery = ref("");
    const contactSearchResults = ref([]);
    const contactSearchOpen = ref(false);
    let activeForm = null;

    const { request } = useRequest();

    async function searchContacts(query) {
        if (!searchPath || !query.trim()) {
            contactSearchResults.value = [];
            contactSearchOpen.value = false;
            return;
        }

        const url = new URL(searchPath, window.location.origin);
        url.searchParams.set("search", query);

        const data = await request(url.toString(), null, HttpMethod.Get);
        if (data) {
            contactSearchResults.value = data?.items ?? [];
            contactSearchOpen.value = contactSearchResults.value.length > 0;
        }
    }

    const debouncedSearch = useDebounce(searchContacts, 200);

    function onContactQueryInput(form, value) {
        activeForm = form;
        contactSearchQuery.value = value;
        debouncedSearch(value);
    }

    function selectContact(contact) {
        if (!activeForm) return;
        activeForm.clientContactId = contact.id;
        activeForm.clientLabel = `${contact.fullName ?? contact.firstName + " " + contact.lastName}${contact.email ? " — " + contact.email : ""}`;
        contactSearchQuery.value = "";
        contactSearchResults.value = [];
        contactSearchOpen.value = false;
    }

    function clearContact(form) {
        form.clientContactId = null;
        form.clientLabel = null;
    }

    return {
        contactSearchQuery,
        contactSearchResults,
        contactSearchOpen,
        onContactQueryInput,
        selectContact,
        clearContact,
    };
}
