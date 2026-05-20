const adminSidebar = document.querySelector("[data-admin-sidebar]");
const adminSidebarButton = document.querySelector("[data-admin-sidebar-button]");
const adminSidebarClose = document.querySelector("[data-admin-sidebar-close]");

document.addEventListener("click", (event) => {
    const toggle = event.target.closest("[data-notification-toggle]");
    if (toggle) {
        const root = toggle.closest("[data-notification-root]");
        root?.querySelector("[data-notification-menu]")?.classList.toggle("hidden");
        markNotificationsRead(root);
        return;
    }

    document.querySelectorAll("[data-notification-menu]").forEach((menu) => {
        if (!menu.closest("[data-notification-root]")?.contains(event.target)) {
            menu.classList.add("hidden");
        }
    });
});

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

if (adminSidebar && adminSidebarButton) {
    adminSidebarButton.addEventListener("click", () => adminSidebar.classList.remove("hidden"));
}

if (adminSidebar && adminSidebarClose) {
    adminSidebarClose.addEventListener("click", () => adminSidebar.classList.add("hidden"));
}

document.querySelectorAll("[data-table-search]").forEach((input) => {
    input.addEventListener("input", () => {
        const table = document.getElementById(input.dataset.tableTarget);

        if (!table) {
            return;
        }

        const query = input.value.toLowerCase();
        table.querySelectorAll("tbody tr").forEach((row) => {
            row.classList.toggle("hidden", !row.textContent.toLowerCase().includes(query));
        });
    });
});

document.querySelectorAll("[data-package-budget-form]").forEach((form) => {
    const packageSelect = form.querySelector("[data-package-select]");
    const budgetInput = form.querySelector("[data-budget-input]");

    if (!budgetInput) {
        return;
    }

    packageSelect?.addEventListener("change", () => {
        syncPackageBudget(packageSelect, budgetInput);
    });
    syncPackageBudget(packageSelect, budgetInput);
});

document.querySelectorAll("form[data-confirm-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (form.dataset.confirmed === "true") {
            return;
        }

        event.preventDefault();
        const message = form.dataset.confirmMessage || "Continue?";

        if (!window.Swal) {
            if (window.confirm(message)) {
                form.dataset.confirmed = "true";
                setFormLoading(form);
                form.submit();
            }
            return;
        }

        Swal.fire({
            title: message,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#7C00D8",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Yes",
            cancelButtonText: "No",
        }).then((result) => {
            if (result.isConfirmed) {
                form.dataset.confirmed = "true";
                setFormLoading(form);
                form.submit();
            }
        });
    });
});

document.querySelectorAll("form[data-loading-form]").forEach((form) => {
    form.addEventListener("submit", () => setFormLoading(form));
});

const adminCalendarGrid = document.getElementById("adminCalendarGrid");
const adminCalendarMonth = document.getElementById("adminCalendarMonth");
const adminSelectedDateTitle = document.getElementById("adminSelectedDateTitle");
const adminSelectedDateEvents = document.getElementById("adminSelectedDateEvents");

if (adminCalendarGrid && adminCalendarMonth) {
    const events = window.eventifyAdminEvents || [];
    let displayDate = new Date();
    let selectedDate = formatDate(displayDate);

    document.querySelector("[data-admin-calendar-prev]")?.addEventListener("click", () => {
        displayDate.setMonth(displayDate.getMonth() - 1);
        renderAdminCalendar();
    });

    document.querySelector("[data-admin-calendar-next]")?.addEventListener("click", () => {
        displayDate.setMonth(displayDate.getMonth() + 1);
        renderAdminCalendar();
    });

    document.querySelector("[data-admin-calendar-today]")?.addEventListener("click", () => {
        displayDate = new Date();
        selectedDate = formatDate(displayDate);
        renderAdminCalendar();
    });

    renderAdminCalendar();

    function renderAdminCalendar() {
        const year = displayDate.getFullYear();
        const month = displayDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const leadingDays = (firstDay.getDay() + 6) % 7;

        adminCalendarMonth.textContent = firstDay.toLocaleDateString("en-US", {
            month: "long",
            year: "numeric",
        });

        adminCalendarGrid.innerHTML = "";

        for (let i = 0; i < leadingDays; i++) {
            adminCalendarGrid.appendChild(makeAdminEmptyDay());
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const date = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
            const dateEvents = events.filter((event) => event.event_date === date);
            const isSelected = date === selectedDate;

            const button = document.createElement("button");
            button.type = "button";
            button.className = [
                "admin-calendar-day rounded-3xl border p-3 text-left shadow-sm transition hover:-translate-y-0.5",
                isSelected ? "border-primary bg-gradient-to-br from-primary to-secondary text-white shadow-soft" : "border-purple-100 bg-white text-dark",
            ].join(" ");
            button.innerHTML = `
                <span class="block text-xl font-semibold">${day}</span>
                <span class="mt-5 inline-flex rounded-full px-2 py-1 text-xs font-semibold ${isSelected ? "bg-white/20 text-white" : dateEvents.length ? "bg-red-50 text-red-600" : "bg-emerald-50 text-emerald-600"}">
                    ${dateEvents.length ? `${dateEvents.length} event` : "Free"}
                </span>
            `;
            button.addEventListener("click", () => {
                selectedDate = date;
                renderAdminCalendar();
            });
            adminCalendarGrid.appendChild(button);
        }

        renderAdminSelectedDate();
    }

    function renderAdminSelectedDate() {
        const selected = new Date(selectedDate + "T00:00:00");
        const selectedEvents = events.filter((event) => event.event_date === selectedDate);

        adminSelectedDateTitle.textContent = selected.toLocaleDateString("en-US", {
            month: "long",
            day: "numeric",
            year: "numeric",
        });

        if (!selectedEvents.length) {
            adminSelectedDateEvents.innerHTML = `
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                    <p class="font-semibold text-emerald-700">Available</p>
                    <p class="mt-1 text-sm text-emerald-700">No approved event is scheduled on this date.</p>
                </div>
            `;
            return;
        }

        adminSelectedDateEvents.innerHTML = selectedEvents.map((event) => {
            const eventId = Number(event.id || 0);

            return `
                <article class="rounded-2xl border border-purple-100 bg-indigo-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">${escapeHtml(event.event_type || "Event")}</p>
                    <h3 class="mt-2 text-xl font-semibold">${escapeHtml(event.event_name || "Untitled Event")}</h3>
                    <p class="mt-2 text-sm text-slate-600">${escapeHtml(event.event_time || "")} | ${escapeHtml(event.venue || "Venue TBA")}</p>
                    <p class="mt-1 text-sm text-slate-600">Client: ${escapeHtml(event.client_name || "Not specified")}</p>
                    ${eventId ? `<a href="event_records.php?event_id=${eventId}" class="mt-4 inline-flex rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary hover:bg-purple-50">Records</a>` : ""}
                </article>
            `;
        }).join("");
    }
}

function makeAdminEmptyDay() {
    const element = document.createElement("div");
    element.className = "admin-calendar-day rounded-3xl bg-white/40";
    return element;
}

function syncPackageBudget(packageSelect, budgetInput) {
    const prices = window.eventifyPackagePrices || {};
    const packagePrice = packageSelect ? prices[String(packageSelect.value)] : null;
    budgetInput.value = packagePrice ? Number(packagePrice).toFixed(2) : "";
}

function setFormLoading(form) {
    form.querySelectorAll("button[type='submit']").forEach((button) => {
        button.disabled = true;
        button.dataset.originalText = button.dataset.originalText || button.textContent;
        button.textContent = "Please wait...";
        button.classList.add("opacity-70", "cursor-not-allowed");
    });
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
