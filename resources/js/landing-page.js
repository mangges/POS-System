const cartIcon = document.querySelector(".cart-toggle-btn");

document.addEventListener("livewire:init", () => {
    Livewire.on("trigger-cart-animation", (event) => {
        const productId = event.productId;
        const modal = document.querySelector(".product-modal-overlay");
        const productCard = document.querySelector(".product-card[data-product-id='" + productId + "']");

        if (!modal && !productCard) return;
        if (productCard) addTocartAnimation(productCard, productId);
        if (modal) modalAddToCartAnimation(modal, productId);
    });
});

function addTocartAnimation(productCard, productId) {
    const productImg = 
    productCard.querySelector(".product-img-wrapper img") ||
    productCard.querySelector(".product-placeholder");
    if (!productImg || !cartIcon) {
        executeLivewireCart(productId);
        return;
    }

    const imgRect = productImg.getBoundingClientRect();
    const cartRect = cartIcon.getBoundingClientRect();

    doFlyer(productImg, imgRect, cartRect, null, productCard, productId);
}

function modalAddToCartAnimation(modal, productId) {
    const productImg =
        modal.querySelector(".product-modal-img") ||
        modal.querySelector(".product-placeholder");

    if (!productImg || !cartIcon) {
        executeLivewireCart(productId);
        return;
    }

    const imgRect = productImg.getBoundingClientRect();
    const cartRect = cartIcon.getBoundingClientRect();

    doFlyer(productImg, imgRect, cartRect, modal, null, productId);
}

function doFlyer(productImg, imgRect, cartRect, modal=null, productCard=null, productId) {
    //flying object
    if (productImg.src) {
        var flyer = document.createElement("img");
        flyer.src = productImg.src;
    } else {
        var flyer = document.createElement("div");
        flyer.innerHTML = productImg.outerHTML;
    }
    flyer.classList.add("flying-item");

    const startWidth = imgRect.width;
    const startHeight = imgRect.height;
    flyer.style.left = `${imgRect.left + imgRect.width / 2}px`;
    flyer.style.top = `${imgRect.top + imgRect.height / 2}px`;
    flyer.style.transition = "none";
    flyer.style.width = `${startWidth}px`;
    flyer.style.height = `${startHeight}px`;
    flyer.style.transform = "translate(-50%, -50%)";

    document.body.appendChild(flyer);
    flyer.getBoundingClientRect();
    if (modal) modal.style.display = "none";

    flyer.style.transition = "all 0.6s cubic-bezier(0.25, 1, 0.5, 1)";

    setTimeout(() => {
        flyer.style.left = `${cartRect.left + cartRect.width / 2}px`;
        flyer.style.top = `${cartRect.top + cartRect.height / 2}px`;
        flyer.style.width = "50px"; 
        flyer.style.height = "50px";
        flyer.style.opacity = "0.5";
    }, 50);

    flyer.addEventListener("transitionend", () => {
        flyer.remove();
        cartIcon.style.transform = "scale(1.2)";
        setTimeout(() => (cartIcon.style.transform = "scale(1)"), 200);
        executeLivewireCart(productId);
    });
}

function executeLivewireCart(productId) {
    const livewireComponent = Livewire.find(
        document.querySelector("[wire\\:id]").getAttribute("wire:id"),
    );

    if (livewireComponent) {
        livewireComponent.call("addToCart", productId);
        livewireComponent.call("closeProductModal");
    }
}
