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
