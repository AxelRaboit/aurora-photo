export function emptyGalleryForm() {
    return {
        title: "",
        slug: "",
        description: "",
        password: "",
        clearPassword: false,
        expiresAt: "",
        allowOriginals: true,
        allowZipDownload: true,
        picksRequireIdentity: false,
        maxPicks: "",
        allowVisitorComments: false,
        watermarkEnabled: false,
        watermarkText: "",
        clientContactId: null,
        clientLabel: null,
        coverMediaId: null,
        coverMediaUrl: null,
    };
}

export function galleryCoverState(form) {
    return { id: form.coverMediaId, url: form.coverMediaUrl };
}

export function onGalleryCoverChange(form, picked) {
    form.coverMediaId = picked?.id ?? null;
    form.coverMediaUrl = picked?.url ?? null;
}

export function isExpiryInPast(value) {
    if (!value) return false;
    const picked = new Date(value);
    if (isNaN(picked.getTime())) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return picked < today;
}

export function slugify(input) {
    return String(input || "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .slice(0, 80);
}
