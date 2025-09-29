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
})();
