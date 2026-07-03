function toggleMenu() {
    const menu = document.getElementById("dropdownMenu");
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}

document.addEventListener('click', function (event) {
    const menu = document.getElementById("dropdownMenu");
    const profileIcon = document.querySelector('.profile-icon');
    if (menu && profileIcon && !profileIcon.contains(event.target)) {
        menu.style.display = "none";
    }
});

/* ── Hamburger / mobile nav ── */
const hamburger = document.getElementById("hamburger");
const mobileNav = document.getElementById("mobileNav");

/* Inject backdrop element once */
const backdrop = document.createElement("div");
backdrop.className = "nav-backdrop";
document.body.appendChild(backdrop);

function openHamburgerMenu() {
    if (!mobileNav) return;
    mobileNav.classList.add("open");
    hamburger.classList.add("is-open");
    hamburger.setAttribute("aria-expanded", "true");
    backdrop.classList.add("visible");
    document.body.style.overflow = "hidden";
}

function closeHamburgerMenu() {
    if (!mobileNav) return;
    mobileNav.classList.remove("open");
    hamburger.classList.remove("is-open");
    hamburger.setAttribute("aria-expanded", "false");
    backdrop.classList.remove("visible");
    document.body.style.overflow = "";
}

if (hamburger && mobileNav) {
    hamburger.addEventListener("click", function (e) {
        e.stopPropagation();
        mobileNav.classList.contains("open") ? closeHamburgerMenu() : openHamburgerMenu();
    });

    backdrop.addEventListener("click", closeHamburgerMenu);

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeHamburgerMenu();
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth > 768) closeHamburgerMenu();
    });
}
