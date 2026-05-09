const menuButton = document.querySelector("[data-mobile-menu-button]");
const mobileMenu = document.querySelector("[data-mobile-menu]");

if (menuButton && mobileMenu) {
    menuButton.addEventListener("click", () => {
        mobileMenu.classList.toggle("hidden");
    });
}

const heroVideoData = document.getElementById("heroVideoClips");
const heroVideoA = document.querySelector("[data-hero-video-a]");
const heroVideoB = document.querySelector("[data-hero-video-b]");
const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if (heroVideoData && heroVideoA && heroVideoB && !prefersReducedMotion) {
    initHeroVideoShowcase();
}

const authModal = document.getElementById("authModal");
const authClose = document.querySelector("[data-auth-modal-close]");
const authTabs = document.querySelectorAll("[data-auth-tab]");
const authPanels = document.querySelectorAll("[data-auth-panel]");

document.querySelectorAll("[data-auth-modal-open]").forEach((button) => {
    button.addEventListener("click", () => {
        openAuthModal(button.dataset.authModalOpen || "login");
        mobileMenu?.classList.add("hidden");
    });
});

authClose?.addEventListener("click", closeAuthModal);

authModal?.addEventListener("click", (event) => {
    if (event.target === authModal) {
        closeAuthModal();
    }
});

authTabs.forEach((tab) => {
    tab.addEventListener("click", () => switchAuthTab(tab.dataset.authTab));
});

document.querySelectorAll("[data-password-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
        const target = document.getElementById(button.dataset.target);

        if (!target) {
            return;
        }

        const isPassword = target.type === "password";
        target.type = isPassword ? "text" : "password";
        button.textContent = isPassword ? "Hide" : "Show";
    });
});

const galleryLightbox = document.getElementById("galleryLightbox");
const galleryLightboxImage = document.getElementById("galleryLightboxImage");
const galleryLightboxTitle = document.getElementById("galleryLightboxTitle");
const galleryLightboxCounter = document.getElementById("galleryLightboxCounter");
const galleryLightboxThumbs = document.getElementById("galleryLightboxThumbs");
const galleryClose = document.querySelector("[data-gallery-close]");
const galleryLightboxPrev = document.querySelector("[data-gallery-lightbox-prev]");
const galleryLightboxNext = document.querySelector("[data-gallery-lightbox-next]");
const galleryShowcaseData = document.getElementById("galleryShowcaseData");
const galleryPackages = {};
let activeGalleryImages = [];
let activeGalleryIndex = 0;

if (galleryShowcaseData) {
    try {
        const galleryGroups = JSON.parse(galleryShowcaseData.textContent || "[]");

        galleryGroups.forEach((group) => {
            (group.items || []).forEach((item) => {
                galleryPackages[item.key] = item;
            });
        });
    } catch (error) {
        console.error("Unable to load gallery images.", error);
    }
}

document.querySelectorAll(".gallery-carousel-prev, .gallery-carousel-next").forEach((button) => {
    button.addEventListener("click", () => {
        const carousel = document.getElementById(button.dataset.galleryTarget);

        if (!carousel) {
            return;
        }

        const direction = button.classList.contains("gallery-carousel-prev") ? -1 : 1;
        carousel.scrollBy({
            left: direction * carousel.clientWidth * 0.85,
            behavior: "smooth"
        });
    });
});

document.addEventListener("click", (event) => {
    const galleryButton = event.target.closest("[data-gallery-open]");

    if (!galleryButton) {
        return;
    }

    const galleryItem = galleryPackages[galleryButton.dataset.galleryKey];

    if (!galleryItem) {
        return;
    }

    openGalleryLightbox(galleryItem.title, galleryItem.images);
});

galleryClose?.addEventListener("click", closeGalleryLightbox);
galleryLightboxPrev?.addEventListener("click", () => showGalleryImage(activeGalleryIndex - 1));
galleryLightboxNext?.addEventListener("click", () => showGalleryImage(activeGalleryIndex + 1));

galleryLightbox?.addEventListener("click", (event) => {
    if (event.target === galleryLightbox) {
        closeGalleryLightbox();
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !galleryLightbox?.classList.contains("hidden")) {
        closeGalleryLightbox();
    }
});

function openAuthModal(tab) {
    if (!authModal) {
        return;
    }

    switchAuthTab(tab);
    authModal.classList.remove("hidden");
}

function closeAuthModal() {
    authModal?.classList.add("hidden");
}

function openGalleryLightbox(title, images) {
    if (!galleryLightbox || !galleryLightboxImage || !galleryLightboxTitle) {
        return;
    }

    activeGalleryImages = Array.isArray(images) ? images : [];

    if (activeGalleryImages.length === 0) {
        activeGalleryImages = [{ src: "", label: title || "Event design preview" }];
    }

    activeGalleryIndex = 0;
    galleryLightboxTitle.textContent = title || "Event Design";
    renderGalleryThumbs();
    showGalleryImage(activeGalleryIndex);
    galleryLightbox.classList.remove("hidden");
    document.body.classList.add("overflow-hidden");
}

function showGalleryImage(index) {
    if (!galleryLightboxImage || activeGalleryImages.length === 0) {
        return;
    }

    activeGalleryIndex = (index + activeGalleryImages.length) % activeGalleryImages.length;
    const image = activeGalleryImages[activeGalleryIndex];
    galleryLightboxImage.src = image.src || "";
    galleryLightboxImage.alt = image.label || galleryLightboxTitle?.textContent || "Event design preview";

    if (galleryLightboxCounter) {
        galleryLightboxCounter.textContent = `Photo ${activeGalleryIndex + 1} of ${activeGalleryImages.length}`;
    }

    galleryLightboxThumbs?.querySelectorAll("[data-gallery-thumb]").forEach((thumb) => {
        const isActive = Number(thumb.dataset.galleryThumb) === activeGalleryIndex;
        thumb.classList.toggle("ring-2", isActive);
        thumb.classList.toggle("ring-primary", isActive);
        thumb.classList.toggle("opacity-60", !isActive);
    });
}

function renderGalleryThumbs() {
    if (!galleryLightboxThumbs) {
        return;
    }

    galleryLightboxThumbs.innerHTML = activeGalleryImages.map((image, index) => `
        <button type="button" class="overflow-hidden rounded-2xl border border-purple-100 bg-purple-50 transition hover:opacity-100" data-gallery-thumb="${index}" aria-label="View package photo ${index + 1}">
            <img src="${image.src || ""}" alt="${image.label || "Package thumbnail"}" class="h-20 w-full object-cover">
        </button>
    `).join("");

    galleryLightboxThumbs.querySelectorAll("[data-gallery-thumb]").forEach((thumb) => {
        thumb.addEventListener("click", () => showGalleryImage(Number(thumb.dataset.galleryThumb)));
    });
}

function closeGalleryLightbox() {
    galleryLightbox?.classList.add("hidden");
    activeGalleryImages = [];
    activeGalleryIndex = 0;

    if (galleryLightboxImage) {
        galleryLightboxImage.src = "";
        galleryLightboxImage.alt = "";
    }

    if (galleryLightboxThumbs) {
        galleryLightboxThumbs.innerHTML = "";
    }

    document.body.classList.remove("overflow-hidden");
}

function switchAuthTab(activeTab) {
    authTabs.forEach((tab) => {
        const isActive = tab.dataset.authTab === activeTab;
        tab.classList.toggle("bg-white", isActive);
        tab.classList.toggle("text-primary", isActive);
        tab.classList.toggle("shadow-sm", isActive);
        tab.classList.toggle("text-slate-500", !isActive);
    });

    authPanels.forEach((panel) => {
        panel.classList.toggle("hidden", panel.dataset.authPanel !== activeTab);
    });
}

function initHeroVideoShowcase() {
    let clips = [];

    try {
        clips = JSON.parse(heroVideoData.textContent || "[]");
    } catch (error) {
        clips = [];
    }

    if (!Array.isArray(clips) || clips.length === 0) {
        return;
    }

    let activeIndex = 0;
    let showingA = true;
    const rotateEvery = 9000;

    loadHeroVideo(heroVideoA, clips[activeIndex]);
    playHeroVideoWhenReady(heroVideoA, () => {
        heroVideoA.classList.add("is-active");
    });

    window.setInterval(() => {
        activeIndex = (activeIndex + 1) % clips.length;
        const currentVideo = showingA ? heroVideoA : heroVideoB;
        const nextVideo = showingA ? heroVideoB : heroVideoA;

        loadHeroVideo(nextVideo, clips[activeIndex]);
        nextVideo.currentTime = 0;

        playHeroVideoWhenReady(nextVideo, () => {
            nextVideo.classList.add("is-active");
            currentVideo.classList.remove("is-active");
            showingA = !showingA;
        });
    }, rotateEvery);
}

function loadHeroVideo(video, clip) {
    if (!video || !clip) {
        return;
    }

    const preferredSource = supportsWebmVideo() && clip.webm ? clip.webm : clip.mp4;

    if (!preferredSource || video.dataset.currentSource === preferredSource) {
        return;
    }

    video.dataset.currentSource = preferredSource;
    video.innerHTML = "";

    const source = document.createElement("source");
    source.src = preferredSource;
    source.type = preferredSource.endsWith(".webm") ? "video/webm" : "video/mp4";
    video.appendChild(source);
    video.setAttribute("aria-label", clip.label || "Eventify event showcase background video");
    video.load();
}

function playHeroVideoWhenReady(video, onReady) {
    const reveal = () => {
        const playPromise = video.play();

        if (playPromise && typeof playPromise.then === "function") {
            playPromise.then(onReady).catch(() => {});
        } else {
            onReady();
        }
    };

    if (video.readyState >= 3) {
        reveal();
        return;
    }

    video.addEventListener("canplay", reveal, { once: true });
    video.addEventListener("error", () => video.classList.remove("is-active"), { once: true });
}

function supportsWebmVideo() {
    const video = document.createElement("video");
    return Boolean(video.canPlayType && video.canPlayType("video/webm; codecs=\"vp8, vorbis\"").replace("no", ""));
}
