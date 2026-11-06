document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const menuToggle = document.getElementById("menuToggle");
    const overlay = document.getElementById("sidebarOverlay");

    function closeSidebar() {
        sidebar?.classList.remove("open");
        overlay?.classList.remove("open");
        menuToggle?.setAttribute("aria-expanded", "false");
    }

    menuToggle?.addEventListener("click", () => {
        const isOpen = sidebar?.classList.toggle("open") ?? false;

        overlay?.classList.toggle("open", isOpen);
        menuToggle.setAttribute("aria-expanded", String(isOpen));
    });

    overlay?.addEventListener("click", closeSidebar);

    document.querySelectorAll(".sidebar-nav a").forEach(link => {
        link.addEventListener("click", closeSidebar);
    });

    const searchInput = document.getElementById("blogSearch");
    const statusFilter = document.getElementById("statusFilter");
    const tableBody = document.getElementById("blogsTableBody");
    const emptyState = document.getElementById("emptyState");

    function filterBlogs() {
        if (!tableBody) return;

        const search = (searchInput?.value ?? "").trim().toLowerCase();
        const selectedStatus = statusFilter?.value ?? "all";
        const rows = Array.from(tableBody.querySelectorAll("tr"));

        let visibleRows = 0;

        rows.forEach(row => {
            const title = row.dataset.title ?? "";
            const status = row.dataset.status ?? "draft";

            const matchesTitle = title.includes(search);
            const matchesStatus =
                selectedStatus === "all" || status === selectedStatus;

            const visible = matchesTitle && matchesStatus;

            row.hidden = !visible;

            if (visible) visibleRows++;
        });

        if (emptyState) {
            emptyState.hidden = visibleRows > 0;
            emptyState.textContent = rows.length === 0
                ? "No blog posts yet. Create your first blog to get started."
                : "No blogs match your search.";
        }
    }

    searchInput?.addEventListener("input", filterBlogs);
    statusFilter?.addEventListener("change", filterBlogs);
});
