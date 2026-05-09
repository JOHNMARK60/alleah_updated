const sidebar = document.querySelector("[data-sidebar]");
const sidebarButton = document.querySelector("[data-sidebar-button]");
const sidebarClose = document.querySelector("[data-sidebar-close]");
const reservationDetailsModal = document.getElementById("reservationDetailsModal") || document.getElementById("reservationModal");
const reservationDetailsTitle = document.getElementById("reservationDetailsTitle") || document.getElementById("modalTitle");
const modalDetails = document.getElementById("modalDetails");

let activeModal = null;
let previousFocus = null;
let calendarLoaded = false;
let reservationsLoaded = false;
let calendarState = {
    events: [],
    displayDate: new Date(),
    selectedDate: formatDate(new Date()),
};

if (sidebar && sidebarButton) {
    sidebarButton.addEventListener("click", () => sidebar.classList.remove("hidden"));
}

if (sidebar && sidebarClose) {
    sidebarClose.addEventListener("click", () => sidebar.classList.add("hidden"));
}

document.addEventListener("click", handleDocumentClick);
document.addEventListener("submit", handleFormSubmit);
document.addEventListener("keydown", handleKeyboard);

initPackageBudgetForms(document);
initAvailabilityForms(document);

async function handleDocumentClick(event) {
    const notificationToggle = event.target.closest("[data-notification-toggle]");
    if (notificationToggle) {
        const root = notificationToggle.closest("[data-notification-root]");
        root?.querySelector("[data-notification-menu]")?.classList.toggle("hidden");
        markNotificationsRead(root);
        return;
    }

    document.querySelectorAll("[data-notification-menu]").forEach((menu) => {
        if (!menu.closest("[data-notification-root]")?.contains(event.target)) {
            menu.classList.add("hidden");
        }
    });

    const openButton = event.target.closest("[data-dashboard-modal-open]");
    if (openButton) {
        event.preventDefault();
        openModal(openButton.dataset.dashboardModalOpen);
        sidebar?.classList.add("hidden");
        return;
    }

    const modalTargetButton = event.target.closest("[data-modal-target]");
    if (modalTargetButton) {
        event.preventDefault();
        const modal = document.getElementById(modalTargetButton.dataset.modalTarget);
        if (modal?.dataset.dashboardModal) {
            openModal(modal.dataset.dashboardModal);
            sidebar?.classList.add("hidden");
        }
        return;
    }

    const switchButton = event.target.closest("[data-dashboard-modal-switch]");
    if (switchButton) {
        closeModal(null, true);
        openModal(switchButton.dataset.dashboardModalSwitch);
        return;
    }

    const closeButton = event.target.closest("[data-dashboard-modal-close]");
    if (closeButton) {
        closeModal(closeButton.closest("[data-dashboard-modal]"));
        return;
    }

    const modalOverlay = event.target.closest("[data-dashboard-modal]");
    if (modalOverlay && event.target === modalOverlay) {
        closeModal(modalOverlay);
        return;
    }

    const detailClose = event.target.closest("[data-reservation-close]");
    if (detailClose) {
        closeReservationDetails();
        return;
    }

    if (reservationDetailsModal && event.target === reservationDetailsModal) {
        closeReservationDetails();
        return;
    }

    const reservationTab = event.target.closest("[data-reservation-tab]");
    if (reservationTab) {
        switchReservationTab(reservationTab);
        return;
    }

    const reservationView = event.target.closest("[data-reservation-view]");
    if (reservationView) {
        openReservationDetails(reservationView);
        return;
    }

    const calendarPrev = event.target.closest("[data-calendar-prev]");
    if (calendarPrev) {
        calendarState.displayDate.setMonth(calendarState.displayDate.getMonth() - 1);
        renderCalendar();
        return;
    }

    const calendarNext = event.target.closest("[data-calendar-next]");
    if (calendarNext) {
        calendarState.displayDate.setMonth(calendarState.displayDate.getMonth() + 1);
        renderCalendar();
        return;
    }

    const calendarToday = event.target.closest("[data-calendar-today]");
    if (calendarToday) {
        calendarState.displayDate = new Date();
        calendarState.selectedDate = formatDate(new Date());
        renderCalendar();
        return;
    }

    const calendarDay = event.target.closest("[data-calendar-date]");
    if (calendarDay) {
        calendarState.selectedDate = calendarDay.dataset.calendarDate;
        renderCalendar();
        return;
    }

    const pricingChoice = event.target.closest("[data-pricing-choice]");
    if (pricingChoice) {
        chooseEventPackage(pricingChoice.dataset.pricingChoice, pricingChoice.dataset.pricingEvent || "");
    }
}

async function handleFormSubmit(event) {
    const form = event.target;

    if (form.matches("[data-confirm-form]")) {
        event.preventDefault();
        await submitConfirmedForm(form);
        return;
    }

    if (form.matches("[data-loading-form]") && form.action.includes("reservation.php")) {
        event.preventDefault();
        await submitReservationForm(form);
        return;
    }

    if (form.matches("[data-loading-form]")) {
        setFormLoading(form);
    }
}

function handleKeyboard(event) {
    const topModal = getTopOpenModal();

    if (event.key === "Escape") {
        if (reservationDetailsModal && !reservationDetailsModal.classList.contains("hidden")) {
            closeReservationDetails();
            return;
        }

        closeModal();
        return;
    }

    if (event.key === "Tab" && topModal) {
        trapFocus(event, topModal);
    }
}

async function openModal(modalId) {
    const modal = document.querySelector(`[data-dashboard-modal="${modalId}"]`);
    if (!modal) {
        return;
    }

    closeModal(null, true);
    previousFocus = document.activeElement;
    activeModal = modal;
    modal.classList.remove("hidden");
    modal.setAttribute("aria-hidden", "false");

    requestAnimationFrame(() => {
        modal.classList.add("is-open");
        focusFirstElement(modal);
    });

    document.body.classList.add("modal-open");

    if (modalId === "calendar") {
        await loadCalendarData();
    }

    if (modalId === "reservations") {
        await loadReservationsContent();
    }
}

function openPricingModal() {
    openModal("pricing");
}

async function chooseEventPackage(packageId, eventType = "") {
    closeModal(document.querySelector('[data-dashboard-modal="pricing"]'), true);
    await openModal("reservation");

    const reservationModalElement = document.querySelector('[data-dashboard-modal="reservation"]');
    const form = reservationModalElement?.querySelector("form");
    const eventTypeInput = form?.querySelector("[name='event_type']");
    const packageSelect = form?.querySelector("[data-package-select]");
    const budgetInput = form?.querySelector("[data-budget-input]");

    if (eventTypeInput && eventType) {
        eventTypeInput.value = eventType;
    }

    if (packageSelect) {
        packageSelect.value = packageId;
    }

    if (packageSelect) {
        syncPackageBudget(packageSelect, budgetInput);
        checkAvailability(form);
    }
}

function closeModal(target = null, immediate = false) {
    const modals = target
        ? [target]
        : Array.from(document.querySelectorAll("[data-dashboard-modal]"));

    modals.forEach((modal) => {
        if (!modal || modal.classList.contains("hidden")) {
            return;
        }

        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");

        const finishClose = () => {
            modal.classList.add("hidden");
            if (activeModal === modal) {
                activeModal = null;
            }
            syncBodyScrollLock();
        };

        if (immediate) {
            finishClose();
        } else {
            window.setTimeout(finishClose, 180);
        }
    });

    if (!target && previousFocus instanceof HTMLElement) {
        previousFocus.focus();
        previousFocus = null;
    }
}

async function loadCalendarData(force = false) {
    if (calendarLoaded && !force) {
        renderCalendar();
        return;
    }

    const loading = document.querySelector("[data-calendar-loading]");
    loading?.classList.remove("hidden");

    try {
        const html = await fetchText("calendar.php");
        const match = html.match(/window\.eventifyCalendarEvents\s*=\s*(\[.*?\]);/s);
        calendarState.events = match ? JSON.parse(match[1]) : [];
        calendarLoaded = true;
        renderCalendar();
    } catch (error) {
        showAlert("error", "Calendar unavailable", "Please try opening the calendar again.");
    } finally {
        loading?.classList.add("hidden");
    }
}

async function loadReservationsContent(force = false) {
    const content = document.querySelector("[data-reservations-content]");
    const loading = document.querySelector("[data-reservations-loading]");

    if (!content) {
        return;
    }

    if (reservationsLoaded && !force) {
        return;
    }

    loading?.classList.remove("hidden");
    content.classList.add("opacity-50");

    try {
        const html = await fetchText("my_reservations.php");
        const parsed = new DOMParser().parseFromString(html, "text/html");
        const reservationBlock = parsed.querySelector("main > div.mt-8") || parsed.querySelector("main");

        content.innerHTML = reservationBlock
            ? reservationBlock.outerHTML
            : `<div class="mt-6 rounded-[2rem] bg-indigo-50 p-8 text-center">
                <h3 class="text-2xl font-semibold">No reservations yet</h3>
                <p class="mt-2 text-slate-600">Your bookings will appear here after you submit a request.</p>
            </div>`;

        reservationsLoaded = true;
        initPackageBudgetForms(content);
        initAvailabilityForms(content);
    } catch (error) {
        content.innerHTML = `<div class="mt-6 rounded-[2rem] bg-red-50 p-8 text-center text-red-700">
            <h3 class="text-2xl font-semibold">Reservations unavailable</h3>
            <p class="mt-2">Please try again in a moment.</p>
        </div>`;
    } finally {
        loading?.classList.add("hidden");
        content.classList.remove("opacity-50");
    }
}

async function submitReservationForm(form) {
    if (!form.reportValidity()) {
        return;
    }

    setFormLoading(form);

    try {
        const response = await fetch(form.action, {
            method: "POST",
            body: new FormData(form),
            headers: {"X-Requested-With": "XMLHttpRequest"},
        });
        const html = await response.text();

        if (response.redirected && response.url.includes("dashboard.php")) {
            form.reset();
            initPackageBudgetForms(form);
            initAvailabilityForms(form);
            showAlert("success", "Reservation submitted", "Waiting for admin approval.");
            closeModal(document.querySelector('[data-dashboard-modal="reservation"]'));
            reservationsLoaded = false;
            await loadReservationsContent(true);
            return;
        }

        const parsed = new DOMParser().parseFromString(html, "text/html");
        const message = parsed.querySelector(".text-red-700")?.textContent?.trim()
            || "Please check the form and try again.";
        showAlert("error", "Reservation failed", message);
    } catch (error) {
        showAlert("error", "Reservation failed", "Please try again.");
    } finally {
        restoreFormLoading(form);
    }
}

async function submitConfirmedForm(form) {
    const message = form.dataset.confirmMessage || "Continue?";
    const confirmed = await confirmAction(message);

    if (!confirmed) {
        return;
    }

    setFormLoading(form);

    try {
        const response = await fetch(form.action, {
            method: "POST",
            body: new FormData(form),
            headers: {"X-Requested-With": "XMLHttpRequest"},
        });

        if (!response.ok) {
            throw new Error("Request failed");
        }

        showAlert("success", "Reservation cancelled", "Your reservation was updated.");
        reservationsLoaded = false;
        await loadReservationsContent(true);
    } catch (error) {
        showAlert("error", "Action failed", "Please try again.");
    } finally {
        restoreFormLoading(form);
    }
}

function renderCalendar() {
    const calendarGrid = document.getElementById("calendarGrid");
    const calendarMonth = document.getElementById("calendarMonth");
    const selectedDateTitle = document.getElementById("selectedDateTitle");
    const selectedDateEvents = document.getElementById("selectedDateEvents");

    if (!calendarGrid || !calendarMonth || !selectedDateTitle || !selectedDateEvents) {
        return;
    }

    const year = calendarState.displayDate.getFullYear();
    const month = calendarState.displayDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const leadingDays = (firstDay.getDay() + 6) % 7;

    calendarMonth.textContent = firstDay.toLocaleDateString("en-US", {
        month: "long",
        year: "numeric",
    });

    calendarGrid.innerHTML = "";

    for (let i = 0; i < leadingDays; i++) {
        calendarGrid.appendChild(makeEmptyDay());
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
        const bookedEvents = calendarState.events.filter((event) => event.event_date === date);
        const isSelected = date === calendarState.selectedDate;
        const button = document.createElement("button");

        button.type = "button";
        button.dataset.calendarDate = date;
        button.className = [
            "calendar-day rounded-3xl border p-3 text-left shadow-sm transition hover:-translate-y-0.5",
            isSelected ? "border-primary bg-gradient-to-br from-primary to-secondary text-white shadow-soft" : "border-purple-100 bg-white text-dark",
            bookedEvents.length ? "ring-1 ring-red-100" : "",
        ].join(" ");
        button.innerHTML = `
            <span class="block text-xl font-semibold">${day}</span>
            <span class="mt-5 inline-flex rounded-full px-2 py-1 text-xs font-semibold ${isSelected ? "bg-white/20 text-white" : bookedEvents.length ? "bg-red-50 text-red-600" : "bg-emerald-50 text-emerald-600"}">
                ${bookedEvents.length ? "Full" : "Free"}
            </span>
        `;
        calendarGrid.appendChild(button);
    }

    renderSelectedDate();
}

function renderSelectedDate() {
    const selectedDateTitle = document.getElementById("selectedDateTitle");
    const selectedDateEvents = document.getElementById("selectedDateEvents");

    if (!selectedDateTitle || !selectedDateEvents) {
        return;
    }

    const selected = new Date(calendarState.selectedDate + "T00:00:00");
    const selectedEvents = calendarState.events.filter((event) => event.event_date === calendarState.selectedDate);

    selectedDateTitle.textContent = selected.toLocaleDateString("en-US", {
        month: "long",
        day: "numeric",
        year: "numeric",
    });

    if (!selectedEvents.length) {
        selectedDateEvents.innerHTML = `
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                <p class="font-semibold text-emerald-700">Available</p>
                <p class="mt-1 text-sm text-emerald-700">No approved event is scheduled on this date.</p>
            </div>
        `;
        return;
    }

    selectedDateEvents.innerHTML = selectedEvents.map((event) => `
        <article class="rounded-2xl border border-purple-100 bg-indigo-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">${escapeHtml(event.event_type || "Event")}</p>
            <h3 class="mt-2 text-xl font-semibold">${escapeHtml(event.event_name || "Untitled Event")}</h3>
            <p class="mt-2 text-sm text-slate-600">${escapeHtml(event.event_time || "")} | ${escapeHtml(event.venue || "Venue TBA")}</p>
        </article>
    `).join("");
}

function switchReservationTab(button) {
    const container = button.closest("[data-reservations-content]") || document;
    const group = button.dataset.reservationTab;

    container.querySelectorAll("[data-reservation-tab]").forEach((tab) => {
        const isActive = tab === button;
        tab.classList.toggle("bg-white", isActive);
        tab.classList.toggle("text-primary", isActive);
        tab.classList.toggle("shadow-sm", isActive);
        tab.classList.toggle("text-slate-500", !isActive);
    });

    container.querySelectorAll("[data-reservation-group]").forEach((card) => {
        card.classList.toggle("hidden", card.dataset.reservationGroup !== group);
    });

    container.querySelectorAll("[data-reservation-empty]").forEach((emptyState) => {
        emptyState.classList.toggle("hidden", emptyState.dataset.reservationEmpty !== group);
    });
}

function openReservationDetails(button) {
    if (!reservationDetailsModal || !reservationDetailsTitle || !modalDetails) {
        return;
    }

    previousFocus = document.activeElement;
    reservationDetailsTitle.textContent = button.dataset.title || "Reservation";
    const details = [
        ["Type", button.dataset.type],
        ["Date", button.dataset.date],
        ["Time", button.dataset.time],
        ["Venue", button.dataset.venue],
        ["Guests", button.dataset.guests],
        ["Client", button.dataset.client],
        ["Contact", button.dataset.contact],
        ["Package", button.dataset.package],
        ["Budget", formatPeso(button.dataset.budget)],
        ["Services", button.dataset.services],
        ["Status", button.dataset.status],
        ["Submitted", button.dataset.created],
        ["Approved", button.dataset.approved],
        ["Rejected", button.dataset.rejected],
        ["Cancelled", button.dataset.cancelled],
        ["Last Updated", button.dataset.updated],
    ];

    modalDetails.innerHTML = details.map(([label, value]) => `
        <div class="rounded-2xl bg-indigo-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">${label}</p>
            <p class="mt-1 font-bold">${escapeHtml(value || "Not specified")}</p>
        </div>
    `).join("");

    reservationDetailsModal.classList.remove("hidden");
    reservationDetailsModal.setAttribute("aria-hidden", "false");
    requestAnimationFrame(() => {
        reservationDetailsModal.classList.add("is-open");
        focusFirstElement(reservationDetailsModal);
    });
    document.body.classList.add("modal-open");
}

function closeReservationDetails() {
    if (!reservationDetailsModal || reservationDetailsModal.classList.contains("hidden")) {
        return;
    }

    reservationDetailsModal.classList.remove("is-open");
    reservationDetailsModal.setAttribute("aria-hidden", "true");

    window.setTimeout(() => {
        reservationDetailsModal.classList.add("hidden");
        syncBodyScrollLock();
        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus();
            previousFocus = null;
        }
    }, 180);
}

function initPackageBudgetForms(root) {
    root.querySelectorAll("[data-package-budget-form]").forEach((form) => {
        const packageSelect = form.querySelector("[data-package-select]");
        const budgetInput = form.querySelector("[data-budget-input]");

        if (!budgetInput) {
            return;
        }

        if (packageSelect && !packageSelect.dataset.budgetBound) {
            packageSelect.addEventListener("change", () => {
                syncPackageBudget(packageSelect, budgetInput);
                checkAvailability(form);
            });
            packageSelect.dataset.budgetBound = "true";
        }

        syncPackageBudget(packageSelect, budgetInput);
    });
}

function syncPackageBudget(packageSelect, budgetInput) {
    const prices = window.eventifyPackagePrices || {};
    const packagePrice = packageSelect ? prices[String(packageSelect.value)] : null;

    if (budgetInput) {
        budgetInput.value = packagePrice ? Number(packagePrice).toFixed(2) : "";
    }
}

function initAvailabilityForms(root) {
    root.querySelectorAll("[data-package-budget-form]").forEach((form) => {
        const fields = form.querySelectorAll("[data-availability-field], input[name='event_date']");

        if (!fields.length || form.dataset.availabilityBound === "true") {
            return;
        }

        form.dataset.availabilityBound = "true";
        let timeout = null;
        fields.forEach((field) => {
            field.addEventListener("input", () => {
                window.clearTimeout(timeout);
                timeout = window.setTimeout(() => checkAvailability(form), 350);
            });
            field.addEventListener("change", () => {
                checkAvailability(form);
            });
        });
    });
}

async function checkAvailability(form) {
    const status = form.querySelector("[data-availability-status]");

    if (!status) {
        return;
    }

    const eventDate = form.querySelector("input[name='event_date']")?.value || "";
    const startTime = form.querySelector("input[name='start_time'], input[name='event_time']")?.value || "";
    const endTime = form.querySelector("input[name='end_time']")?.value || "";
    const venue = form.querySelector("input[name='venue']")?.value || "";

    if (!eventDate || !startTime || !endTime || !venue) {
        status.className = "mt-4 rounded-2xl bg-indigo-50 p-4 text-sm font-semibold text-slate-600";
        status.textContent = "Select a date, time range, and venue to check availability.";
        return;
    }

    const params = new URLSearchParams({event_date: eventDate, start_time: startTime, end_time: endTime, venue});
    status.className = "mt-4 rounded-2xl bg-indigo-50 p-4 text-sm font-semibold text-primary";
    status.textContent = "Checking availability...";

    try {
        const response = await fetch(`ajax/check_availability.php?${params.toString()}`, {
            headers: {"X-Requested-With": "XMLHttpRequest"},
        });
        const data = await response.json();
        status.className = data.available
            ? "mt-4 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-700"
            : "mt-4 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-700";
        status.textContent = data.message || "Availability checked.";
    } catch (error) {
        status.className = "mt-4 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-700";
        status.textContent = "Availability check failed. Please try again.";
    }
}

function setFormLoading(form) {
    form.querySelectorAll("button[type='submit']").forEach((button) => {
        button.disabled = true;
        button.dataset.originalText = button.dataset.originalText || button.textContent;
        button.textContent = "Please wait...";
        button.classList.add("opacity-70", "cursor-not-allowed");
    });
}

function restoreFormLoading(form) {
    form.querySelectorAll("button[type='submit']").forEach((button) => {
        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
        button.classList.remove("opacity-70", "cursor-not-allowed");
    });
}

function syncBodyScrollLock() {
    const hasOpenModal = Array.from(document.querySelectorAll(".dashboard-modal"))
        .some((modal) => !modal.classList.contains("hidden"));

    document.body.classList.toggle("modal-open", hasOpenModal);
}

function getTopOpenModal() {
    if (reservationDetailsModal && !reservationDetailsModal.classList.contains("hidden")) {
        return reservationDetailsModal;
    }

    return activeModal && !activeModal.classList.contains("hidden") ? activeModal : null;
}

function focusFirstElement(modal) {
    const focusable = getFocusableElements(modal);
    (focusable[0] || modal).focus();
}

function trapFocus(event, modal) {
    const focusable = getFocusableElements(modal);

    if (!focusable.length) {
        event.preventDefault();
        modal.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function getFocusableElements(root) {
    return Array.from(root.querySelectorAll([
        "a[href]",
        "button:not([disabled])",
        "input:not([disabled])",
        "select:not([disabled])",
        "textarea:not([disabled])",
        "[tabindex]:not([tabindex='-1'])",
    ].join(","))).filter((element) => element.offsetParent !== null);
}

async function confirmAction(title) {
    if (!window.Swal) {
        return window.confirm(title);
    }

    const result = await Swal.fire({
        title,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#7C00D8",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Yes",
        cancelButtonText: "No",
    });

    return result.isConfirmed;
}

function showAlert(icon, title, text = "") {
    if (!window.Swal) {
        window.alert(text ? `${title}\n${text}` : title);
        return;
    }

    Swal.fire({icon, title, text});
}

async function fetchText(url) {
    const response = await fetch(url, {
        headers: {"X-Requested-With": "XMLHttpRequest"},
    });

    if (!response.ok) {
        throw new Error("Request failed");
    }

    return response.text();
}

function makeEmptyDay() {
    const element = document.createElement("div");
    element.className = "calendar-day rounded-3xl bg-white/40";
    return element;
}

function formatDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function formatPeso(value) {
    if (value === undefined || value === null || value === "") {
        return "";
    }

    const amount = Number(String(value).replace(/[^0-9.-]/g, ""));

    if (!Number.isFinite(amount)) {
        return "";
    }

    return `₱${amount.toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function markNotificationsRead(root) {
    const form = root?.querySelector("[data-notification-read-form]");

    if (!form || form.dataset.submitted === "true") {
        return;
    }

    form.dataset.submitted = "true";
    fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: {"X-Requested-With": "XMLHttpRequest"},
    }).then(() => {
        root.querySelector("[data-notification-dot]")?.remove();
        const count = root.querySelector("[data-notification-unread-count]");
        if (count) {
            count.textContent = "0";
        }
    }).catch(() => {
        form.dataset.submitted = "false";
    });
}
