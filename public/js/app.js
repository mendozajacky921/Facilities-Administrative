/**
 * app.js
 * General site behavior: mobile sidebar toggle, auto-dismiss flash
 * messages. Module-specific JS lives in the module's own file,
 * loaded conditionally from templates/footer.php - keep this file
 * generic, not module-aware.
 *
 * REDESIGN ADDITION: a second, independent block below adds the
 * optional desktop sidebar collapse toggle (#t8SidebarCollapseToggle,
 * templates/sidebar.php). It only touches a new class
 * (.t8-sidebar-collapsed on .t8-shell) and does not modify or
 * interfere with the mobile toggle logic above it.
 */

document.addEventListener("DOMContentLoaded", function () {
    var toggleBtn = document.getElementById("t8SidebarToggle");
    var sidebar = document.getElementById("t8Sidebar");

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("t8-sidebar-open");
        });
    }

    var alerts = document.querySelectorAll(".t8-flash-stack .t8-alert");
    alerts.forEach(function (alertEl) {
        setTimeout(function () {
            alertEl.style.transition = "opacity 0.3s ease";
            alertEl.style.opacity = "0";
            setTimeout(function () {
                alertEl.remove();
            }, 300);
        }, 5000);
    });
});

document.addEventListener("DOMContentLoaded", function () {
    var collapseBtn = document.getElementById("t8SidebarCollapseToggle");
    var shell = document.querySelector(".t8-shell");

    if (collapseBtn && shell) {
        collapseBtn.addEventListener("click", function () {
            shell.classList.toggle("t8-sidebar-collapsed");
        });
    }
});
