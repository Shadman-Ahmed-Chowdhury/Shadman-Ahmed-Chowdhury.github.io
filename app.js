(function () {
    const CANONICAL = "https://shadman.xyz";
    const currentHost = window.location.hostname;

    // Do nothing on localhost
    if (currentHost === "localhost" || currentHost === "127.0.0.1") {
        return;
    }

    // If already on canonical domain → do nothing
    if (currentHost === "shadman.xyz" || currentHost === "www.shadman.xyz") {
        return;
    }

    // Only redirect when visiting GitHub Pages
    if (currentHost === "shadman-ahmed-chowdhury.github.io") {
        // Check if canonical domain is reachable
        fetch(CANONICAL, { mode: "no-cors" })
            .then(() => {
                window.location.replace(CANONICAL + window.location.pathname);
            })
            .catch(() => {
                // shadman.xyz is not reachable → stay on GitHub Pages
                console.warn("Canonical domain not reachable. Staying here.");
            });
    }
})();
