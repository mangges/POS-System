document.addEventListener("alpine:init", () => {
    Alpine.data("currencyInput", (model) => ({
        raw: model,
        display: "",

        init() {
            this.display = this.format(this.raw);
        },

        format(value) {
            if (value === null || value === undefined || value === "") {
                return "";
            }

            return "Rp " + Number(value).toLocaleString("id-ID");
        },

        handleInput(event) {
            const value = event.target.value.replace(/\D/g, "");

            this.raw = value || null;
            this.display = this.format(value);
        },
    }));
});
