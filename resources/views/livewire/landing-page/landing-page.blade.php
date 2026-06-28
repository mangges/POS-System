<div class="landing-page" x-data="{ cartOpen: false, scrolled: false }"
    x-effect="document.body.style.overflow = (cartOpen || {{ $selectedProduct ? 'true' : 'false' }}) ? 'hidden' : ''"
    @scroll.window="scrolled = (window.pageYOffset > 40)">
    @vite('resources/css/landing-page.css')

    <!-- Announcement Bar -->
    <div class="announcement-bar">
        <div class="announcement-inner">
            <span class="announcement-dot"></span>
            <span>Selamat datang &mdash; Pesan langsung dari meja Anda, tanpa antri, tanpa ribet</span>
            <span class="announcement-dot"></span>
        </div>
    </div>

    <!-- Navbar -->
    <nav :class="scrolled ? 'navbar' : 'nav-bar'">
        <div class="nav-left">
            <a href="#" class="brand-text">POS System</a>
            <div class="table-number">
                <span>{{ $table->name }}</span>
            </div>
        </div>
        <button @click="cartOpen = true" class="cart-toggle-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            @if (count($cart) > 0)
                <span class="cart-badge">{{ count($cart) }}</span>
            @endif
        </button>
    </nav>

    <div class="main-container">

        <!-- Hero Section -->
        <div class="hero">
            <div class="hero-badge">
                <span class="hero-badge-dot"></span>
                <span>Menu Eksklusif Kami</span>
                <span class="hero-badge-dot"></span>
            </div>
            <h1 class="hero-title">Eksplorasi Rasa<br><em class="hero-title-italic">yang Sempurna</em></h1>
            <p class="hero-subtitle">Temukan menu favoritmu, pesan langsung dari meja, dan nikmati hidangan berkualitas
                dengan pengalaman bersantap yang elegan.</p>
            <div class="hero-divider">
                <span class="divider-line"></span>
                <span class="divider-ornament">&#9670;</span>
                <span class="divider-line"></span>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value">{{ $products->count() }}+</span>
                    <span class="stat-label">Pilihan Menu</span>
                </div>
            </div>
            <div class="stat-sep"></div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value">08.00 &ndash; 22.00</span>
                    <span class="stat-label">Jam Operasional</span>
                </div>
            </div>
            <div class="stat-sep"></div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value">Segar Setiap Hari</span>
                    <span class="stat-label">Bahan Berkualitas Pilihan</span>
                </div>
            </div>
        </div>

        <!-- Menu Section Header -->
        <div class="section-header-block">
            <div class="section-ornament">
                <span class="ornament-line"></span>
                <span class="ornament-icon">&#9670;</span>
                <span class="ornament-line"></span>
            </div>
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
                            @else
                                <div class="product-hover-overlay">
                                    <span class="product-hover-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            style="width:18px;height:18px;display:inline-block;vertical-align:middle;margin-right:5px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Lihat Detail
                                    </span>
                                </div>
                            @endif
                            @if ($product->category)
                                <span class="product-category-badge">{{ $product->category->name }}</span>
                            @endif
                        </div>
                        <div class="product-content">
                            <h3 class="product-title">{{ $product->name }}</h3>
                            <p class="product-desc">{{ $product->description }}</p>
                            <div class="product-footer">
                                <span class="product-price">Rp
                                    {{ number_format($product->price, 0, ',', '.') }}</span>
                                @if (!$product->is_out_of_stock)
                                    <span class="product-order-cta"
                                        wire:click.stop="dispatch('trigger-cart-animation', { productId: {{ $product->id }} })">+ Pesan</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Features Section -->
        <div class="features-section">
            <div class="section-header-block">
                <div class="section-ornament">
                    <span class="ornament-line"></span>
                    <span class="ornament-icon">&#9670;</span>
                    <span class="ornament-line"></span>
                </div>
                <h2 class="section-main-title">Mengapa Memilih Kami</h2>
                <p class="section-main-sub">Komitmen kami untuk menghadirkan pengalaman bersantap yang tak terlupakan.
                </p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <h3 class="feature-title">Bahan Segar Pilihan</h3>
                    <p class="feature-desc">Kami menggunakan bahan-bahan berkualitas tinggi yang dipilih setiap harinya
                        untuk menjamin cita rasa terbaik di setiap hidangan yang kami sajikan.</p>
                </div>
                <div class="feature-card feature-card-center">
                    <div class="feature-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z" />
                        </svg>
                    </div>
                    <h3 class="feature-title">Chef Berpengalaman</h3>
                    <p class="feature-desc">Tim koki profesional kami menghadirkan cita rasa autentik dengan sentuhan
                        modern yang kreatif, memanjakan selera setiap tamu yang datang.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="feature-title">Pelayanan Cepat &amp; Tepat</h3>
                    <p class="feature-desc">Sistem pemesanan digital kami memastikan setiap pesanan diproses dengan
                        cepat dan akurat, langsung dari genggaman tangan Anda.</p>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <div class="section-header-block">
                <div class="section-ornament">
                    <span class="ornament-line"></span>
                    <span class="ornament-icon">&#9670;</span>
                    <span class="ornament-line"></span>
                </div>
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
        </div>

        <!-- Profile Section -->
        <div class="profile-section">
            <div class="section-header-block">
                <div class="section-ornament">
                    <span class="ornament-line"></span>
                    <span class="ornament-icon">&#9670;</span>
                    <span class="ornament-line"></span>
                </div>
                <h2 class="section-main-title">Tentang POS System</h2>
            </div>
            <div class="profile-card">
                <div class="profile-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z" />
                    </svg>
                </div>
                <h3>Elegansi dalam Setiap Pesanan</h3>
                <p>POS System menghadirkan pengalaman bersantap yang modern dan elegan. Pesan langsung dari meja Anda,
                    nikmati momen berharga bersama orang-orang tersayang, dan biarkan kami mengurus sisanya.</p>
                <div class="profile-hours">
                    <div class="hours-row">
                        <div class="hours-item">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <span class="hours-day">Senin &ndash; Jumat</span>
                                <span class="hours-time">08.00 &ndash; 22.00 WIB</span>
                            </div>
                        </div>
                        <div class="hours-item">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <span class="hours-day">Sabtu &ndash; Minggu</span>
                                <span class="hours-time">07.00 &ndash; 23.00 WIB</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                <div class="social-icons">
                    <a href="#" class="social-link" aria-label="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </a>
                    <a href="#" class="social-link" aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                        </svg>
                    </a>
                    <a href="#" class="social-link" aria-label="Twitter">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                                d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z">
                            </path>
                        </svg>
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
                        <div class="cart-property">
                            <span>PPN(11%)</span>
                            <span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                        </div>
                        <div class="total-price">
                            <span>Total Keseluruhan</span>
                            <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <a href="#" class="checkout-btn">Pesan Sekarang</a>
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
            <div class="product-modal-content">
                <button class="product-modal-close" wire:click="closeProductModal">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="product-modal-img-container">
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
                    @if ($selectedProduct->category)
                        <span class="modal-category-tag">{{ $selectedProduct->category->name }}</span>
                    @endif
                    <h2 class="product-modal-title">{{ $selectedProduct->name }}</h2>
                    <div class="product-modal-price">Rp {{ number_format($selectedProduct->price, 0, ',', '.') }}
                    </div>
                    <div class="product-modal-divider"></div>
                    <div class="product-modal-desc">
                        {{ $selectedProduct->description ?? 'Tidak ada deskripsi tersedia untuk menu ini.' }}
                    </div>

                    <button wire:click="dispatch('trigger-cart-animation', { productId: {{ $selectedProduct->id }} })"
                        @if ($selectedProduct->is_out_of_stock) disabled @endif class="modal-add-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:24px; height:24px;" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $selectedProduct->is_out_of_stock ? 'Stok Habis' : 'Tambah ke Keranjang' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
@push('scripts')
    @vite('resources/js/landing-page.js')
@endpush
