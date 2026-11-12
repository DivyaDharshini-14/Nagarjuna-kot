<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $kitchen ? $kitchen->name . ' – Kitchen Display Screen' : 'Kitchen Display System (KDS)' }} | Nagarjuna</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800;900&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --canvas-bg: #f9f6ed;
            --brand-green: #0a5c2d;
            --brand-green-light: #167a3f;
            --col-new-header: #146c36;
            --col-new-border: #146c36;
            --col-prep-header: #e09015;
            --col-prep-border: #e09015;
            --col-ready-header: #249645;
            --col-ready-border: #249645;
            --card-border: #e2edd5;
            --text-main: #111827;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden !important;
            margin: 0;
            padding: 10px 18px 12px;
            box-sizing: border-box;
        }

        body {
            background-color: var(--canvas-bg);
            background-image: 
                radial-gradient(ellipse at top left, rgba(34, 139, 34, 0.05) 0%, transparent 50%),
                radial-gradient(ellipse at bottom right, rgba(218, 165, 32, 0.05) 0%, transparent 50%);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            user-select: none;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* CORNER LEAF ARTWORK - MATCHING EXACT DESIGN */
        .corner-foliage {
            position: fixed;
            pointer-events: none;
            z-index: 0;
            width: 140px;
            height: 140px;
        }
        .foliage-tl { top: 0; left: 0; }
        .foliage-tr { top: 0; right: 0; transform: scaleX(-1); }
        .foliage-bl { bottom: 0; left: 0; transform: scaleY(-1); }
        .foliage-br { bottom: 0; right: 0; transform: scale(-1, -1); }

        .kds-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* TOP HEADER: 4 HORIZONTAL CARDS */
        .top-header-grid {
            display: grid;
            grid-template-columns: 190px 1.25fr 310px 220px;
            gap: 14px;
            margin-bottom: 18px; /* EQUAL SPACING: 18px above the 3 section boxes */
            align-items: stretch;
            flex-shrink: 0;
            height: 74px;
        }

        .header-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1.5px solid #ebd9a8;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            padding: 8px 16px;
        }

        /* 1. BRAND CARD */
        .brand-card {
            justify-content: center;
            align-items: center;
            padding: 3px 8px;
            overflow: hidden;
        }
        .brand-logo-img {
            height: 100%;
            max-height: 68px;
            max-width: 100%;
            width: auto;
            object-fit: contain;
            display: block;
        }

        /* 2. ORDER SUMMARY CARD */
        .summary-card {
            background: #fffcf0;
            flex-direction: column;
            justify-content: center;
            padding: 6px 20px;
        }
        .summary-title-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.92rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 2px;
        }
        .summary-title-row svg {
            width: 16px;
            height: 16px;
            fill: #15803d;
        }
        .summary-items-grid {
            display: flex;
            justify-content: space-around;
            width: 100%;
            align-items: center;
        }
        .summary-stat-group {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .summary-stat-label {
            font-size: 0.74rem;
            font-weight: 800;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .summary-stat-num {
            font-size: 1.45rem;
            font-weight: 900;
            color: #111827;
            line-height: 1;
        }

        /* 3. KITCHEN IDENTIFIER CARD */
        .kitchen-card {
            gap: 14px;
            padding: 8px 18px;
        }
        .chef-hat-badge {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 2px solid #f6c043;
            background: #fffdf7;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .chef-hat-badge svg {
            width: 28px;
            height: 28px;
            fill: #df8811;
        }
        .kitchen-details h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--brand-green);
            margin: 0;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .kitchen-details p {
            font-size: 0.78rem;
            color: #374151;
            font-weight: 700;
            margin: 2px 0 0;
        }

        /* 4. CLOCK CARD */
        .clock-card {
            gap: 12px;
            justify-content: center;
            padding: 8px 16px;
        }
        .clock-badge {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2.2px solid #e09618;
            background: #fffdf5;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .clock-badge svg {
            width: 22px;
            height: 22px;
            fill: #e09618;
        }
        .clock-details {
            display: flex;
            flex-direction: column;
        }
        .clock-details .clock-digits {
            font-size: 1.35rem;
            font-weight: 900;
            color: #111827;
            line-height: 1.1;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }
        .clock-details .clock-dateline {
            font-size: 0.74rem;
            color: #4b5563;
            font-weight: 700;
            margin-top: 1px;
        }

        /* 3-COLUMNS BOARD - FILLS REMAINING HEIGHT WITH ZERO SCROLL */
        .board-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            align-items: stretch;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            margin-top: 0;
            margin-bottom: 18px; /* EQUAL SPACING: 18px below the 3 section boxes */
        }

        .board-column {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
            background: #fbf9f2;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
        }

        .col-new-orders {
            border: 2.5px solid var(--col-new-border);
        }
        .col-prep-orders {
            border: 2.5px solid var(--col-prep-border);
        }
        .col-ready-orders {
            border: 2.5px solid var(--col-ready-border);
        }

        /* COLUMN HEADERS */
        .col-header {
            padding: 10px 18px;
            color: #ffffff;
            font-size: 1.3rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.01em;
            flex-shrink: 0;
            height: 48px;
        }

        .col-header-new {
            background: var(--col-new-header);
        }
        .col-header-prep {
            background: var(--col-prep-header);
        }
        .col-header-ready {
            background: var(--col-ready-header);
        }

        .col-header-icon {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
        }

        /* CARDS CONTAINER */
        .cards-list {
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            justify-content: flex-start;
            scrollbar-width: thin;
            scrollbar-color: rgba(20, 108, 54, 0.25) transparent;
        }

        /* KOT CARD - EXACT REPLICA OF USER DESIGN */
        .kot-order-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1.5px solid var(--card-border);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.03);
            display: grid;
            grid-template-columns: 86px 1fr 110px;
            align-items: center;
            padding: 10px 14px 10px 10px;
            gap: 14px;
            cursor: default;
            position: relative;
            flex-shrink: 0;
        }

        /* 1. LEFT GREEN TINTED BOX (KOT, NUMBER, TABLE) */
        .kot-badge-box {
            background: linear-gradient(180deg, #f0fdf4 0%, #e1f7d5 100%);
            border: 1px solid #c8ebb6;
            border-radius: 14px;
            padding: 8px 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-width: 82px;
            flex-shrink: 0;
        }

        .kot-box-label {
            font-size: 0.76rem;
            font-weight: 800;
            color: #14532d;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            line-height: 1;
        }

        .kot-box-number {
            font-size: 1.95rem;
            font-weight: 900;
            color: #064e3b;
            line-height: 1.05;
            margin: 2px 0;
            font-family: 'Outfit', sans-serif;
        }

        .kot-box-table {
            font-size: 0.82rem;
            font-weight: 800;
            color: #1f2937;
            line-height: 1.15;
            white-space: nowrap;
        }

        /* 2. MIDDLE: DISH ITEMS LIST */
        .kot-dishes-middle {
            display: flex;
            flex-direction: column;
            gap: 3px;
            overflow: hidden;
            padding-right: 4px;
        }

        .dish-row-compact {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: baseline;
            gap: 10px;
            font-size: 0.94rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.35;
        }

        .dish-name-compact {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dish-qty-compact {
            font-size: 0.94rem;
            font-weight: 800;
            color: #111827;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .dish-note-pill {
            font-size: 0.72rem;
            color: #991b1b;
            background: #fee2e2;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 700;
            display: inline-block;
            margin-top: 2px;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* 3. RIGHT: DURATION PILL & ORDER RECEIVED TIME BELOW */
        .kot-right-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            flex-shrink: 0;
            width: 110px;
            min-width: 110px;
            max-width: 110px;
            text-align: center;
        }

        /* DURATION CAPSULES (MATCHING EXACT YELLOW PILL IN IMAGE) */
        .timer-capsule-exact {
            background-color: #fec007; /* Bright yellow as in image */
            color: #111827;
            font-size: 0.98rem;
            font-weight: 900;
            border-radius: 24px;
            padding: 6px 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 106px;
            box-sizing: border-box;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            white-space: nowrap;
            letter-spacing: 0.02em;
        }

        .timer-capsule-exact svg {
            width: 16px;
            height: 16px;
            fill: #111827;
            flex-shrink: 0;
        }

        /* PREPARING DURATION CAPSULE (AMBER) */
        .timer-capsule-prep {
            background-color: #f59e0b;
            color: #111827;
            font-size: 0.98rem;
            font-weight: 900;
            border-radius: 24px;
            padding: 6px 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 106px;
            box-sizing: border-box;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            white-space: nowrap;
        }
        .timer-capsule-prep svg {
            width: 16px;
            height: 16px;
            fill: #111827;
            flex-shrink: 0;
        }

        /* READY DURATION CAPSULE (GREEN - FROZEN) */
        .timer-capsule-ready {
            background-color: #16a34a;
            color: #ffffff;
            font-size: 0.98rem;
            font-weight: 900;
            border-radius: 24px;
            padding: 6px 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 106px;
            box-sizing: border-box;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
            white-space: nowrap;
        }
        .timer-capsule-ready svg {
            width: 16px;
            height: 16px;
            fill: #ffffff;
            flex-shrink: 0;
        }

        /* TABULAR NUMBERS (ZERO JITTER / JUMPING) */
        .live-card-timer,
        .total-duration-val {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
            font-family: 'Outfit', 'Roboto Mono', ui-monospace, SFMono-Regular, monospace;
            display: inline-block;
            text-align: center;
        }

        /* ORDER RECEIVED TIME BELOW DURATION PILL */
        .received-time-text {
            font-size: 0.74rem;
            font-weight: 700;
            color: #6b7280;
            text-align: center;
            line-height: 1;
        }

        /* EMPTY COLUMN MESSAGE */
        .empty-column-state {
            text-align: center;
            padding: 40px 14px;
            color: #9ca3af;
            font-weight: 700;
            font-size: 0.95rem;
        }

        /* FULLSCREEN EXPAND FLOATING BUTTON */
        .fullscreen-expand-btn {
            position: fixed;
            bottom: 16px;
            right: 18px;
            z-index: 1000;
            background: #ffffff;
            border: 1.5px solid #146c36;
            color: #146c36;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        .fullscreen-expand-btn:hover {
            background: #146c36;
            color: #ffffff;
            transform: scale(1.1);
        }
    </style>
</head>
<body>

    <!-- 4 CORNER FOLIAGE ARTWORK MATCHING MOCKUP -->
    <svg class="corner-foliage foliage-tl" viewBox="0 0 200 200" fill="none">
        <path d="M-10,-10 C40,10 70,5 95,20 C110,30 115,55 90,65 C65,75 40,55 10,40 Z" fill="#2d7a3f" opacity="0.85"/>
        <path d="M0,0 C30,30 50,45 80,45 C95,45 100,25 75,15 C50,5 25,10 0,0 Z" fill="#52a447" opacity="0.9"/>
        <path d="M20,0 C55,15 90,10 120,30 C135,40 130,60 105,60 C80,60 60,40 20,0 Z" fill="#8bc34a" opacity="0.75"/>
        <path d="M0,30 C25,65 55,80 85,95 C100,105 110,85 85,75 C60,65 35,50 0,30 Z" fill="#388e3c" opacity="0.85"/>
        <path d="M-5,70 C15,95 40,115 65,135 C80,145 95,130 80,115 C65,100 40,85 -5,70 Z" fill="#66bb6a" opacity="0.8"/>
        <!-- Stems -->
        <path d="M-10,-10 Q50,45 100,100" stroke="#1b5e20" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M-10,30 Q30,70 65,130" stroke="#1b5e20" stroke-width="2" stroke-linecap="round"/>
    </svg>

    <svg class="corner-foliage foliage-tr" viewBox="0 0 200 200" fill="none">
        <path d="M-10,-10 C40,10 70,5 95,20 C110,30 115,55 90,65 C65,75 40,55 10,40 Z" fill="#2d7a3f" opacity="0.85"/>
        <path d="M0,0 C30,30 50,45 80,45 C95,45 100,25 75,15 C50,5 25,10 0,0 Z" fill="#52a447" opacity="0.9"/>
        <path d="M20,0 C55,15 90,10 120,30 C135,40 130,60 105,60 C80,60 60,40 20,0 Z" fill="#8bc34a" opacity="0.75"/>
        <path d="M0,30 C25,65 55,80 85,95 C100,105 110,85 85,75 C60,65 35,50 0,30 Z" fill="#388e3c" opacity="0.85"/>
        <path d="M-5,70 C15,95 40,115 65,135 C80,145 95,130 80,115 C65,100 40,85 -5,70 Z" fill="#66bb6a" opacity="0.8"/>
        <path d="M-10,-10 Q50,45 100,100" stroke="#1b5e20" stroke-width="2.5" stroke-linecap="round"/>
    </svg>

    <svg class="corner-foliage foliage-bl" viewBox="0 0 200 200" fill="none">
        <path d="M-10,-10 C40,10 70,5 95,20 C110,30 115,55 90,65 C65,75 40,55 10,40 Z" fill="#2d7a3f" opacity="0.85"/>
        <path d="M0,0 C30,30 50,45 80,45 C95,45 100,25 75,15 C50,5 25,10 0,0 Z" fill="#52a447" opacity="0.9"/>
        <path d="M20,0 C55,15 90,10 120,30 C135,40 130,60 105,60 C80,60 60,40 20,0 Z" fill="#8bc34a" opacity="0.75"/>
        <path d="M0,30 C25,65 55,80 85,95 C100,105 110,85 85,75 C60,65 35,50 0,30 Z" fill="#388e3c" opacity="0.85"/>
        <path d="M-10,-10 Q50,45 100,100" stroke="#1b5e20" stroke-width="2.5" stroke-linecap="round"/>
    </svg>

    <svg class="corner-foliage foliage-br" viewBox="0 0 200 200" fill="none">
        <path d="M-10,-10 C40,10 70,5 95,20 C110,30 115,55 90,65 C65,75 40,55 10,40 Z" fill="#2d7a3f" opacity="0.85"/>
        <path d="M0,0 C30,30 50,45 80,45 C95,45 100,25 75,15 C50,5 25,10 0,0 Z" fill="#52a447" opacity="0.9"/>
        <path d="M20,0 C55,15 90,10 120,30 C135,40 130,60 105,60 C80,60 60,40 20,0 Z" fill="#8bc34a" opacity="0.75"/>
        <path d="M0,30 C25,65 55,80 85,95 C100,105 110,85 85,75 C60,65 35,50 0,30 Z" fill="#388e3c" opacity="0.85"/>
        <path d="M-10,-10 Q50,45 100,100" stroke="#1b5e20" stroke-width="2.5" stroke-linecap="round"/>
    </svg>

    <div class="kds-wrapper">
        <!-- TOP HEADER: 4 CARDS (MATCHING MOCKUP) -->
        <header class="top-header-grid">
            <!-- 1. BRAND CARD -->
            <div class="header-card brand-card">
                <img src="{{ asset('images/nagarjuna-logo.png') }}" alt="Nagarjuna Andhra Style" class="brand-logo-img">
            </div>

            <!-- 2. ORDER SUMMARY CARD -->
            <div class="header-card summary-card">
                <div class="summary-title-row">
                    <!-- Green Bar Chart Icon -->
                    <svg viewBox="0 0 24 24">
                        <rect x="2" y="12" width="4" height="10" rx="1"/>
                        <rect x="9" y="6" width="4" height="16" rx="1"/>
                        <rect x="16" y="2" width="4" height="20" rx="1"/>
                    </svg>
                    <span>Order Summary</span>
                </div>
                <div class="summary-items-grid">
                    <div class="summary-stat-group">
                        <span class="summary-stat-label">
                            <i class="bi bi-circle-fill text-danger" style="font-size: 0.6rem;"></i> New
                        </span>
                        <span class="summary-stat-num" id="sumNew">0</span>
                    </div>
                    <div class="summary-stat-group">
                        <span class="summary-stat-label">
                            <!-- Golden Chef Hat Icon -->
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="#e09015">
                                <path d="M19 10a3 3 0 0 0-2.2-2.9 4 4 0 0 0-7.6 0A3 3 0 0 0 5 10a3 3 0 0 0 1 2.2V17h12v-4.8a3 3 0 0 0 1-2.2zM7 19h10v2H7z"/>
                            </svg>
                            Preparing
                        </span>
                        <span class="summary-stat-num" id="sumPrep">0</span>
                    </div>
                    <div class="summary-stat-group">
                        <span class="summary-stat-label">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 0.8rem;"></i> Ready
                        </span>
                        <span class="summary-stat-num" id="sumReady">0</span>
                    </div>
                </div>
            </div>

            <!-- 3. KITCHEN IDENTIFIER CARD (DEDICATED TV DISPLAY - NO DROPDOWN) -->
            <div class="header-card kitchen-card">
                <div class="chef-hat-badge">
                    <svg viewBox="0 0 24 24">
                        <path d="M19 10a3 3 0 0 0-2.2-2.9 4 4 0 0 0-7.6 0A3 3 0 0 0 5 10a3 3 0 0 0 1 2.2V17h12v-4.8a3 3 0 0 0 1-2.2zM7 19h10v2H7z"/>
                    </svg>
                </div>
                <div class="kitchen-details">
                    <h2 id="kitchenName">{{ $kitchen ? $kitchen->name . ' Kitchen' : 'All Kitchens' }}</h2>
                    <p id="kitchenSubtitle">{{ $kitchen ? ($kitchen->branch ? $kitchen->branch->name . ' · TV Display' : 'Kitchen Display Screen') : 'All Active Stations · TV Display' }}</p>
                </div>
            </div>

            <!-- 4. CLOCK & DATE CARD -->
            <div class="header-card clock-card">
                <div class="clock-badge">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" fill="none" stroke="#e09618" stroke-width="2"/>
                        <polyline points="12 7 12 12 15 14" fill="none" stroke="#e09618" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="clock-details">
                    <span class="clock-digits" id="liveTime">{{ now()->format('g:i A') }}</span>
                    <span class="clock-dateline" id="liveDate">{{ now()->format('D, d M Y') }}</span>
                </div>
            </div>
        </header>

        <!-- 3-COLUMNS BOARD (MATCHING MOCKUP) -->
        <main class="board-grid">
            <!-- COLUMN 1: NEW ORDERS (ACCEPTED / NEW) -->
            <div class="board-column col-new-orders">
                <div class="col-header col-header-new">
                    <!-- Bell with little yellow rays -->
                    <svg class="col-header-icon" viewBox="0 0 32 32">
                        <!-- Yellow sound rays -->
                        <path d="M27,10 L30,8 M28,14 L31,14 M25,18 L28,20" stroke="#fde047" stroke-width="2.5" stroke-linecap="round"/>
                        <!-- White Bell -->
                        <path d="M15,4 C15.6,4 16,4.4 16,5 L16,6 C19.3,6.5 22,9.4 22,13 L22,19 L24,21 L24,22 L6,22 L6,21 L8,19 L8,13 C8,9.4 10.7,6.5 14,6 L14,5 C14,4.4 14.4,4 15,4 Z M12,23 L18,23 C18,24.7 16.7,26 15,26 C13.3,26 12,24.7 12,23 Z" fill="#ffffff"/>
                    </svg>
                    <span>New Orders (<span id="countNew">0</span>)</span>
                </div>
                <div class="cards-list" id="newCardsContainer">
                    <!-- Rendered dynamically -->
                </div>
            </div>

            <!-- COLUMN 2: PREPARING ORDERS -->
            <div class="board-column col-prep-orders">
                <div class="col-header col-header-prep">
                    <!-- Chef Hat Icon -->
                    <svg class="col-header-icon" viewBox="0 0 24 24" fill="#ffffff">
                        <path d="M19 10a3 3 0 0 0-2.2-2.9 4 4 0 0 0-7.6 0A3 3 0 0 0 5 10a3 3 0 0 0 1 2.2V17h12v-4.8a3 3 0 0 0 1-2.2zM7 19h10v2H7z"/>
                    </svg>
                    <span>Preparing Orders (<span id="countPrep">0</span>)</span>
                </div>
                <div class="cards-list" id="prepCardsContainer">
                    <!-- Rendered dynamically -->
                </div>
            </div>

            <!-- COLUMN 3: READY ORDERS -->
            <div class="board-column col-ready-orders">
                <div class="col-header col-header-ready">
                    <!-- Checkmark in Circle -->
                    <svg class="col-header-icon" viewBox="0 0 24 24" fill="#ffffff">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <span>Ready Orders (<span id="countReady">0</span>)</span>
                </div>
                <div class="cards-list" id="readyCardsContainer">
                    <!-- Rendered dynamically -->
                </div>
            </div>
        </main>

        <!-- FULLSCREEN EXPAND ACTION (ONLY EXPAND ICON AS REQUESTED) -->
        <button class="fullscreen-expand-btn" onclick="toggleScreenFull()" title="Full Screen TV Mode">
            <i class="bi bi-arrows-fullscreen"></i>
        </button>

        <span id="networkPill" class="badge bg-danger text-white px-2 py-1 shadow-sm" style="display:none; position: fixed; bottom: 20px; right: 68px; z-index: 1000; font-size: 0.75rem;" title="Network disconnected - attempting to reconnect...">
            <i class="bi bi-wifi-off me-1"></i>Reconnecting...
        </span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Dedicated kitchen station ID or null for Master/All Kitchens
        const initialKitchenId = {{ $kitchen ? $kitchen->id : 'null' }};
        const activeKitchenId = initialKitchenId;

        let soundEnabled = true;
        let audioContext = null;
        let knownIds = new Set();

        // Live Real-Time Clock & Date (updates every second)
        function refreshClock() {
            const now = new Date();
            let h = now.getHours();
            const m = String(now.getMinutes()).padStart(2, '0');
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12;

            const timeEl = document.getElementById('liveTime');
            const dateEl = document.getElementById('liveDate');

            if (timeEl) {
                timeEl.innerText = `${h}:${m} ${ampm}`;
            }

            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dayStr = days[now.getDay()];
            const dateNum = String(now.getDate()).padStart(2, '0');
            const monthStr = months[now.getMonth()];
            const yearStr = now.getFullYear();

            if (dateEl) {
                dateEl.innerText = `${dayStr}, ${dateNum} ${monthStr} ${yearStr}`;
            }
        }
        setInterval(refreshClock, 1000);
        refreshClock();

        // Web Audio Two-Tone Bell Chime on New Order
        function playAlertSound() {
            if (!soundEnabled) return;
            try {
                if (!audioContext) audioContext = new (window.AudioContext || window.webkitAudioContext)();
                if (audioContext.state === 'suspended') audioContext.resume();

                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, audioContext.currentTime); // D5
                osc.frequency.setValueAtTime(880.00, audioContext.currentTime + 0.12); // A5

                gain.gain.setValueAtTime(0.28, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.65);

                osc.connect(gain);
                gain.connect(audioContext.destination);
                osc.start();
                osc.stop(audioContext.currentTime + 0.65);
            } catch(e) {}
        }

        function toggleScreenFull() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        }

        document.addEventListener('fullscreenchange', () => {
            const icon = document.querySelector('.fullscreen-expand-btn i');
            if (icon) {
                if (document.fullscreenElement) {
                    icon.className = 'bi bi-fullscreen-exit';
                } else {
                    icon.className = 'bi bi-arrows-fullscreen';
                }
            }
        });

        // Format Seconds to "MM:SS" (or "HH:MM:SS" if >= 1 hour)
        function formatSecs(seconds) {
            const totalSec = Math.max(0, parseInt(seconds || 0, 10));
            const h = Math.floor(totalSec / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            if (h > 0) {
                return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            }
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }

        // Real-Time Second Increment for Active Live Timers ONLY (.live-card-timer)
        setInterval(() => {
            document.querySelectorAll('.live-card-timer').forEach(el => {
                let sec = parseInt(el.getAttribute('data-sec') || '0', 10);
                sec++;
                el.setAttribute('data-sec', sec);
                el.innerText = formatSecs(sec);
            });
        }, 1000);

        // Generate EXACT KOT Card HTML matching user design (image)
        function buildCardHtml(ticket, stage) {
            // Extract readable number from kot_number or kot_display_number
            let kotNum = ticket.kot_display_number || ticket.kot_number;

            // Table string
            let tableStr = ticket.table_name || '1';
            if (!tableStr.toLowerCase().startsWith('table')) {
                tableStr = `Table ${tableStr}`;
            }

            // Duration Capsule
            let durationHtml = '';
            const sec = parseInt(ticket.elapsed_seconds || 0, 10);
            const timeFormatted = formatSecs(sec);

            if (stage === 'new') {
                durationHtml = `
                    <div class="timer-capsule-exact" title="Duration">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M12 1a1 1 0 0 1 1 1v1.07A8.001 8.001 0 0 1 19.93 11H21a1 1 0 1 1 0 2h-1.07A8.001 8.001 0 0 1 12 21a8.001 8.001 0 0 1-7.93-8H3a1 1 0 1 1 0-2h1.07A8.001 8.001 0 0 1 11 3.07V2a1 1 0 0 1 1-1zm0 4a6 6 0 1 0 6 6 6 6 0 0 0-6-6zm0 2a1 1 0 0 1 1 1v2.586l1.707 1.707a1 1 0 0 1-1.414 1.414l-2-2A1 1 0 0 1 11 10.5V8a1 1 0 0 1 1-1z"/>
                        </svg>
                        <span class="live-card-timer" data-sec="${sec}">${timeFormatted}</span>
                    </div>
                `;
            } else if (stage === 'preparing') {
                durationHtml = `
                    <div class="timer-capsule-prep" title="Duration">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M12 1a1 1 0 0 1 1 1v1.07A8.001 8.001 0 0 1 19.93 11H21a1 1 0 1 1 0 2h-1.07A8.001 8.001 0 0 1 12 21a8.001 8.001 0 0 1-7.93-8H3a1 1 0 1 1 0-2h1.07A8.001 8.001 0 0 1 11 3.07V2a1 1 0 0 1 1-1zm0 4a6 6 0 1 0 6 6 6 6 0 0 0-6-6zm0 2a1 1 0 0 1 1 1v2.586l1.707 1.707a1 1 0 0 1-1.414 1.414l-2-2A1 1 0 0 1 11 10.5V8a1 1 0 0 1 1-1z"/>
                        </svg>
                        <span class="live-card-timer" data-sec="${sec}">${timeFormatted}</span>
                    </div>
                `;
            } else if (stage === 'ready') {
                // STOPPED: total preparation duration frozen
                durationHtml = `
                    <div class="timer-capsule-ready" title="Total Duration">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span class="total-duration-val">${timeFormatted}</span>
                    </div>
                `;
            }

            // Dishes list rows (compact, matching image)
            const dishesHtml = (ticket.items || []).map(item => `
                <div class="dish-row-compact">
                    <span class="dish-name-compact">${item.dish_name}</span>
                    <span class="dish-qty-compact">× ${item.quantity}</span>
                </div>
                ${item.special_instructions ? `<div class="dish-note-pill">${item.special_instructions}</div>` : ''}
            `).join('');

            // Special instructions callout (shown only if present)
            const specialNote = ticket.special_instructions && ticket.special_instructions.trim() !== ''
                ? `<div class="dish-note-pill mt-1">${ticket.special_instructions}</div>`
                : '';

            // Order Received Time below duration
            const receivedTimeHtml = ticket.order_received_time 
                ? `<span class="received-time-text">${ticket.order_received_time}</span>` 
                : '';

            return `
                <div class="kot-order-card" id="card-${ticket.id}" title="${ticket.kot_number}">
                    <div class="kot-badge-box">
                        <span class="kot-box-label">KOT</span>
                        <span class="kot-box-number">${kotNum}</span>
                        <span class="kot-box-table">${tableStr}</span>
                    </div>
                    <div class="kot-dishes-middle">
                        ${dishesHtml}
                        ${specialNote}
                    </div>
                    <div class="kot-right-column">
                        ${durationHtml}
                        ${receivedTimeHtml}
                    </div>
                </div>
            `;
        }

        // Real-time Feed Polling
        async function pullFeed() {
            try {
                const url = activeKitchenId ? `/kds/${activeKitchenId}/feed` : `/kds/feed`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Network error');
                const data = await res.json();

                const newTickets = data.new_tickets || [];
                const prepTickets = data.preparing_tickets || [];
                const readyTickets = data.ready_tickets || [];

                const countNew = data.counts?.new ?? 0;
                const countPrep = data.counts?.preparing ?? 0;
                const countReady = data.counts?.ready ?? 0;

                // Update Header Summary Stats
                document.getElementById('sumNew').innerText = countNew;
                document.getElementById('sumPrep').innerText = countPrep;
                document.getElementById('sumReady').innerText = countReady;

                // Update Column Header Counts
                document.getElementById('countNew').innerText = countNew;
                document.getElementById('countPrep').innerText = countPrep;
                document.getElementById('countReady').innerText = countReady;

                // Audio alert on new order arrival
                const currentNewIds = new Set(newTickets.map(t => t.id));
                let hasNew = false;
                for (let id of currentNewIds) {
                    if (!knownIds.has(id)) {
                        hasNew = true;
                        break;
                    }
                }
                if (hasNew && knownIds.size > 0) {
                    playAlertSound();
                }
                knownIds = new Set([...newTickets.map(t => t.id), ...prepTickets.map(t => t.id), ...readyTickets.map(t => t.id)]);

                // Render Column 1: New Orders (Accepted / New)
                const cNew = document.getElementById('newCardsContainer');
                if (newTickets.length === 0) {
                    cNew.innerHTML = '<div class="empty-column-state">No new orders waiting</div>';
                } else {
                    cNew.innerHTML = newTickets.map(t => buildCardHtml(t, 'new')).join('');
                }

                // Render Column 2: Preparing Orders (Preparing)
                const cPrep = document.getElementById('prepCardsContainer');
                if (prepTickets.length === 0) {
                    cPrep.innerHTML = '<div class="empty-column-state">No orders in preparation</div>';
                } else {
                    cPrep.innerHTML = prepTickets.map(t => buildCardHtml(t, 'preparing')).join('');
                }

                // Render Column 3: Ready Orders (Ready)
                const cReady = document.getElementById('readyCardsContainer');
                if (readyTickets.length === 0) {
                    cReady.innerHTML = '<div class="empty-column-state">No ready orders for pickup</div>';
                } else {
                    cReady.innerHTML = readyTickets.map(t => buildCardHtml(t, 'ready')).join('');
                }

                // Clear network warning on successful fetch
                const netPill = document.getElementById('networkPill');
                if (netPill) netPill.style.display = 'none';

            } catch (err) {
                console.error('Error fetching KDS feed:', err);
                // Show network reconnection warning
                const netPill = document.getElementById('networkPill');
                if (netPill) netPill.style.display = 'inline-flex';
            }
        }

        // Initial load & Polling
        pullFeed();
        setInterval(pullFeed, 3500);

        // Resume AudioContext on first touch/click
        document.body.addEventListener('click', () => {
            if (audioContext && audioContext.state === 'suspended') audioContext.resume();
        }, { once: true });
    </script>
</body>
</html>
