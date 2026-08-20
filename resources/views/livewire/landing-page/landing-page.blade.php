<div class="landing-page" x-data="{ cartOpen: false }"
    x-effect="document.body.style.overflow = (cartOpen || {{ $selectedProduct || $showPaymentModal ? 'true' : 'false' }}) ? 'hidden' : ''">
    @vite('resources/css/landing-page.css')

    <!-- Navbar -->
    <nav class="navbar">
        <div class="table-number">
            <span>{{ $table->name }}</span>
        </div>
        <x-notification-bell :qr-token="$table->qr_token" />
    </nav>

    <div class="main-container">

        <!-- Hero Banner -->
        <div class="hero">
            <div class="hero-media">
                <img src="{{ asset('images/coffee.jpg') }}" alt="Suasana Restoran" class="hero-banner-img">
                <div class="hero-scrim"></div>
            </div>
            <div class="hero-content">
                <span class="hero-eyebrow">Menu Digital</span>
                <h1 class="hero-title">Selamat Datang di {{ $table->name }}</h1>
                <p class="hero-subtitle">Pilih menu favorit Anda dan pesan langsung dari meja, tanpa perlu menunggu
                    pelayan.</p>
                <a href="#menu" class="hero-cta">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                    Lihat Menu
                </a>
            </div>
        </div>

        <!-- Feature Strip -->
        {{-- <div class="feature-strip">
            <div class="feature-item">
                <span class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
                <span class="feature-label">Pesan Realtime</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <span class="feature-label">Higienis &amp; Fresh</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-12V7a4 4 0 00-8 0v2" />
                    </svg>
                </span>
                <span class="feature-label">Pembayaran Aman</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <span class="feature-label">Tanpa Antre</span>
            </div>
        </div> --}}

        <!-- Menu Section Header -->
        <div class="section-header-block" id="menu">
            <h2 class="section-main-title">Daftar Menu Kami</h2>
            <p class="section-main-sub">Setiap hidangan disiapkan dengan dedikasi penuh menggunakan bahan-bahan pilihan
                terbaik.</p>
        </div>

        <!-- Search & Filter -->
        <div class="search-filter-section">
            <div class="search-container">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari menu favorit..."
                    class="search-input">
                <svg xmlns="http://www.w3.org/2000/svg" class="search-icon" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <div class="category-chips">
                <button wire:click="setCategory(null)"
                    class="chip-btn {{ $selectedCategory === null ? 'active' : '' }}">Semua</button>
                @foreach ($categories as $category)
                    <button wire:click="setCategory({{ $category->id }})"
                        class="chip-btn {{ $selectedCategory == $category->id ? 'active' : '' }}">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Product Grid -->
        @if ($products->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3>Menu Tidak Ditemukan</h3>
                <p>Silakan coba kata kunci pencarian yang berbeda atau pilih kategori lain.</p>
            </div>
        @else
            <div class="product-grid">
                @foreach ($products as $product)
                    <div class="product-card" data-product-id="{{ $product->id }}" wire:click="selectProduct({{ $product->id }})">
                        <div class="product-img-wrapper">
                            @if ($product->image)
                                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}"
                                    class="product-img">
                            @else
                                <div class="product-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif
                            @if ($product->is_out_of_stock)
                                <div class="out-of-stock-overlay">
                                    <span class="out-of-stock-badge">Habis</span>
                                </div>
                            @endif
                        </div>
                        <div class="product-content">
                            <h3 class="product-title">{{ $product->name }}</h3>
                            @if ($product->category)
                                <span class="product-category">{{ $product->category->name }}</span>
                            @endif
                            <div class="product-footer">
                                <span class="product-price">Rp
                                    {{ number_format($product->price, 0, ',', '.') }}</span>
                                @if (!$product->is_out_of_stock)
                                    <button type="button" class="add-to-cart-btn"
                                        wire:click.stop="dispatch('trigger-cart-animation', { productId: {{ $product->id }} })"
                                        aria-label="Tambah ke keranjang">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- FAQ Section -->
        {{-- <div class="faq-section">
            <div class="section-header-block">
                <h2 class="section-main-title">Pertanyaan Umum</h2>
                <p class="section-main-sub">Temukan jawaban dari pertanyaan yang paling sering ditanyakan pelanggan
                    kami.</p>
            </div>
            <div class="faq-list">

                <div x-data="{ expanded: false }" class="faq-item">
                    <button @click="expanded = !expanded" class="faq-question" :class="expanded ? 'expanded' : ''">
                        <span>Bagaimana cara memesan?</span>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </button>
                    <div x-show="expanded" x-collapse style="display: none;">
                        <div class="faq-answer">
                            Pilih menu yang Anda inginkan, klik untuk melihat detail, lalu tekan <strong>"Tambah ke
                                Keranjang"</strong>. Setelah selesai, buka ikon keranjang di navbar untuk menyelesaikan
                            pesanan Anda.
                        </div>
                    </div>
                </div>

                <div x-data="{ expanded: false }" class="faq-item">
                    <button @click="expanded = !expanded" class="faq-question" :class="expanded ? 'expanded' : ''">
                        <span>Metode pembayaran apa saja yang didukung?</span>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </button>
                    <div x-show="expanded" x-collapse style="display: none;">
                        <div class="faq-answer">
                            Kami mendukung berbagai metode: <strong>QRIS</strong>, <strong>Transfer Bank</strong>, dan
                            <strong>Tunai</strong> di meja kasir. Pilih metode yang paling nyaman untuk Anda.
                        </div>
                    </div>
                </div>

                <div x-data="{ expanded: false }" class="faq-item">
                    <button @click="expanded = !expanded" class="faq-question" :class="expanded ? 'expanded' : ''">
                        <span>Berapa lama waktu penyajian?</span>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </button>
                    <div x-show="expanded" x-collapse style="display: none;">
                        <div class="faq-answer">
                            Umumnya hidangan tersaji dalam <strong>10&ndash;20 menit</strong> setelah pesanan
                            dikonfirmasi. Waktu dapat bervariasi tergantung keramaian dan kompleksitas menu.
                        </div>
                    </div>
                </div>

                <div x-data="{ expanded: false }" class="faq-item">
                    <button @click="expanded = !expanded" class="faq-question" :class="expanded ? 'expanded' : ''">
                        <span>Apakah saya bisa mengubah atau membatalkan pesanan?</span>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </button>
                    <div x-show="expanded" x-collapse style="display: none;">
                        <div class="faq-answer">
                            Anda dapat mengubah item dari keranjang <strong>sebelum</strong> konfirmasi pesanan. Setelah
                            pesanan dikonfirmasi, silakan hubungi staf kami di meja kasir.
                        </div>
                    </div>
                </div>
            </div>
        </div> --}}
    </div>

    <!-- Footer Section -->
    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-profile">
                <h3 class="footer-brand">POS System</h3>
                <p class="footer-desc">Menghadirkan pengalaman bersantap yang elegan, modern, dan tanpa hambatan. POS
                    System adalah solusi point of sale terpercaya untuk bisnis kuliner Anda.</p>
            </div>
            <div class="footer-links">
                <h4 class="footer-heading">Sosial Media</h4>
                <div class="social-list">
                    <a href="#" class="social-handle">
                        <span class="social-link" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                            </svg>
                        </span>
                        <span>@pos-system</span>
                    </a>
                    <a href="#" class="social-handle">
                        <span class="social-link" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                            </svg>
                        </span>
                        <span>@pos-system</span>
                    </a>
                    <a href="#" class="social-handle">
                        <span class="social-link" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path
                                    d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z">
                                </path>
                            </svg>
                        </span>
                        <span>@pos-system</span>
                    </a>
                </div>
            </div>
            <div class="footer-address">
                <h4 class="footer-heading">Alamat</h4>
                <p>Jl. Jendral Sudirman No. 123<br>Kawasan SCBD, Jakarta Selatan<br>DKI Jakarta, 12190<br>Indonesia</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; {{ date('Y') }} POS System. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <!-- Bottom Cart Bar -->
    @if (count($cart) > 0)
        <div class="bottom-cart-bar">
            <div class="bottom-cart-info">
                <span class="bottom-cart-count">{{ count($cart) }} item</span>
                <span class="bottom-cart-total">Rp {{ number_format($this->total, 0, ',', '.') }}</span>
            </div>
            <button type="button" class="bottom-cart-btn" @click="cartOpen = true">Lihat Keranjang</button>
        </div>
    @endif

    <!-- Cart Slide-over Menu -->
    <div x-show="cartOpen" class="cart-overlay" style="display: none;">
        <!-- Backdrop -->
        <div class="cart-overlay" @click="cartOpen = false" x-show="cartOpen" x-transition.opacity></div>

        <div class="cart-panel" x-show="cartOpen" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="transform translate-x-full" x-transition:enter-end="transform translate-x-0"
            x-transition:leave="transition ease-in duration-300" x-transition:leave-start="transform translate-x-0"
            x-transition:leave-end="transform translate-x-full">

            <div class="cart-header">
                <h2 class="cart-title">Keranjang Anda</h2>
                <button @click="cartOpen = false" class="close-cart-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="cart-body">
                @if (count($cart) === 0)
                    <div class="cart-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p>Keranjang Anda masih kosong.</p>
                        <button @click="cartOpen = false" class="start-order-btn">Mulai Pesan</button>
                    </div>
                @else
                    <div class="cart-items">
                        @foreach ($cart as $key => $item)
                            <div class="cart-item">
                                @if ($item['image'])
                                    <img src="{{ Storage::url($item['image']) }}" alt="{{ $item['name'] }}"
                                        class="cart-item-img">
                                @else
                                    <div class="cart-item-img"
                                        style="display:flex; align-items:center; justify-content:center; color:#ccc;">
                                        <svg xmlns="http://www.w3.org/2000/svg" style="width:32px; height:32px;"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif

                                <div class="cart-item-body">
                                    <h3 class="cart-item-name">{{ $item['name'] }}</h3>
                                    <span class="cart-item-price">Rp
                                        {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</span>
                                </div>
                                <div class="cart-item-actions">
                                    <div class="quantity-control">
                                        <button wire:click="decrementQuantity({{ $key }})"
                                            class="qty-btn">-</button>
                                        <span class="qty-value">{{ $item['qty'] }}</span>
                                        <button wire:click="incrementQuantity({{ $key }})"
                                            class="qty-btn">+</button>
                                    </div>
                                    <button wire:click="removeFromCart({{ $key }})" class="remove-btn"
                                        aria-label="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 6h18"></path>
                                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17">
                                            </line>
                                            <line x1="14" y1="11" x2="14" y2="17">
                                            </line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if (count($cart) > 0)
                <div class="cart-footer">
                    <div class="cart-total-row">
                        <div class="cart-summary-row">
                            <span>Subtotal</span>
                            <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="cart-summary-row">
                            <span>PPN (11%)</span>
                            <span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                        </div>
                        <div class="total-price">
                            <span>Total</span>
                            <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <a wire:click="openPaymentModal" @click="cartOpen = false" class="checkout-btn">Pesan Sekarang</a>
                    @if ($table)
                        <p class="cart-table-info">Pesanan akan diantar ke <strong>{{ $table->name }}</strong></p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Product Detail Modal -->
    @if ($selectedProduct)
        <div class="product-modal-overlay" wire:click.self="closeProductModal">
            <div class="product-modal-content" wire:key="product-modal-{{ $selectedProduct->id }}">
                <div class="product-modal-img-container">
                    <button class="product-modal-close" wire:click="closeProductModal" aria-label="Kembali">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    @if ($selectedProduct->image)
                        <img src="{{ Storage::url($selectedProduct->image) }}" alt="{{ $selectedProduct->name }}"
                            class="product-modal-img">
                    @else
                        <div class="product-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif
                    @if ($selectedProduct->is_out_of_stock)
                        <div class="out-of-stock-overlay">
                            <span class="out-of-stock-badge">Habis</span>
                        </div>
                    @endif
                </div>

                <div class="product-modal-details">
                    <div class="product-modal-title-row">
                        <h2 class="product-modal-title">{{ $selectedProduct->name }}</h2>
                        <span class="product-modal-price">Rp
                            {{ number_format($selectedProduct->price, 0, ',', '.') }}</span>
                    </div>
                    @if ($selectedProduct->category)
                        <span class="product-modal-category">{{ $selectedProduct->category->name }}</span>
                    @endif
                    <div class="product-modal-desc">
                        {{ $selectedProduct->description ?? 'Tidak ada deskripsi tersedia untuk menu ini.' }}
                    </div>

                    @if ($selectedProduct->is_out_of_stock)
                        <button disabled class="modal-add-btn modal-add-btn-full">Stok Habis</button>
                    @else
                        <div class="modal-action-row" x-data="{ qty: 1 }">
                            <div class="modal-qty-stepper">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" class="modal-qty-btn"
                                    aria-label="Kurangi jumlah">&minus;</button>
                                <span class="modal-qty-value" x-text="qty">1</span>
                                <button type="button" @click="qty++" class="modal-qty-btn"
                                    aria-label="Tambah jumlah">+</button>
                            </div>
                            <button type="button" class="modal-add-btn"
                                @click="(() => { for (let i = 0; i < qty; i++) { $wire.dispatch('trigger-cart-animation', { productId: {{ $selectedProduct->id }} }) } qty = 1 })()">
                                <i class="bi bi-cart-plus"></i>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Payment Modal -->
    @if ($showPaymentModal)
        @include('livewire.landing-page.payment_modal')
    @endif

    <!-- Order Detail Modal (from notification click) -->
    @if ($viewingOrderId)
        @include('livewire.landing-page.order_detail_modal')
    @endif
</div>
@push('scripts')
    @vite('resources/js/landing-page.js')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const myOrdersKey = 'pos_my_orders_{{ $table->qr_token }}';
            const myOrders = () => {
                try {
                    return JSON.parse(sessionStorage.getItem(myOrdersKey) || '[]');
                } catch (e) {
                    return [];
                }
            };

            Livewire.on('order-placed', (event) => {
                const ids = myOrders();
                ids.push(event.orderId);
                sessionStorage.setItem(myOrdersKey, JSON.stringify(ids));
            });

            window.Echo.channel('table.{{ $table->qr_token }}')
                .stopListening('.OrderStatusUpdated')
                .listen('.OrderStatusUpdated', (e) => {
                    // Table channel is shared by every order ever placed at this table,
                    // so only forward events for orders this browser session itself placed
                    // — otherwise a still-open tab from a previous customer at the same
                    // table would get notified about the next customer's order.
                    if (!myOrders().includes(e.order_id)) {
                        return;
                    }
                    Livewire.dispatch('notify', { message: e.message, type: e.type, orderId: e.order_id, status: e.status });
                    Livewire.dispatch('order-status-updated', { orderId: e.order_id, status: e.status });
                });
        });
    </script>
@endpush
