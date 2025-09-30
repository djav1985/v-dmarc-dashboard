(function () {
    window.showToast = function (message) {
        var toast = document.createElement("div");
        toast.className = "toast";
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.remove();
        }, 3000);
    };

    function initNavbarToggle() {
        var toggle = document.querySelector('.navbar-toggle');
        var collapsible = document.getElementById('navbarCollapsible');

        if (!toggle || !collapsible) {
            return;
        }

        var isListeningOutside = false;

        function closeMenu() {
            collapsible.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            if (isListeningOutside) {
                document.removeEventListener('click', handleOutsideClick);
                isListeningOutside = false;
            }
        }

        function handleOutsideClick(event) {
            if (!collapsible.contains(event.target) && !toggle.contains(event.target)) {
                closeMenu();
            }
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            var isOpen = collapsible.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (isOpen && !isListeningOutside) {
                document.addEventListener('click', handleOutsideClick);
                isListeningOutside = true;
            } else if (!isOpen && isListeningOutside) {
                document.removeEventListener('click', handleOutsideClick);
                isListeningOutside = false;
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 960) {
                closeMenu();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNavbarToggle);
    } else {
        initNavbarToggle();
    }
})();
