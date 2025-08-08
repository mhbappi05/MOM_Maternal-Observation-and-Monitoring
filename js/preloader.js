    window.addEventListener("load", function () {
        const preloader = document.getElementById("preloader");
        if (preloader) {
            preloader.classList.add("fade-out");
            setTimeout(() => {
                preloader.style.display = "none";
            }, 500); // match fade-out duration
        }
    });

        window.addEventListener("load", function () {
        const preloader = document.getElementById("preloader");
        const welcome = document.getElementById("welcomeMessage");

        if (preloader) {
            preloader.classList.add("fade-out");

            setTimeout(() => {
                preloader.style.display = "none";

                // Show welcome message after preloader disappears
                if (welcome) {
                    welcome.classList.add("show");
                    setTimeout(() => {
                        welcome.classList.remove("show");
                    }, 5000); // Hide after 5 seconds
                }

            }, 600); // fade-out time
        }
    });