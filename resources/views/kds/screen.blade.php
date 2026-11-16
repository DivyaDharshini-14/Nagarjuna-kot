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
            max-width: 96px;
            width: 86px;
            overflow: hidden;
            box-sizing: border-box;
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
            font-size: 0.80rem;
            font-weight: 800;
            color: #1f2937;
            line-height: 1.15;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
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
            <!-- COLUMN 1: ACCEPTED ORDERS; ACCEPT NEW TICKETS IN THE STAFF QUEUE -->
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

        <div class="d-flex gap-2 align-items-center" style="flex-shrink:0; min-height:32px;">
            <button id="soundToggle" type="button" class="btn btn-sm btn-outline-success">Enable sound</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#ticketHistory">Completed / cancelled</button>
            <span id="kdsActionError" class="text-danger small" role="alert"></span>
        </div>
        <div class="offcanvas offcanvas-bottom" tabindex="-1" id="ticketHistory" aria-labelledby="historyTitle" style="height:75vh;">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5" id="historyTitle">Ticket history</h2>
                <div class="btn-group ms-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-history="cancelled">Cancelled items</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-history="completed">Completed orders</button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div id="historyCardsContainer"></div>
                <div class="d-flex gap-3 align-items-center my-3">
                    <button id="historyPrevious" class="btn btn-sm btn-outline-secondary" type="button">Previous</button>
                    <span id="historyPage"></span>
                    <button id="historyNext" class="btn btn-sm btn-outline-secondary" type="button">Next</button>
                </div>
            </div>
        </div>
        <!-- Fullscreen control -->
        <button class="fullscreen-expand-btn" onclick="toggleScreenFull()" title="Full Screen TV Mode">
            <i class="bi bi-arrows-fullscreen"></i>
        </button>

        <span id="networkPill" class="badge bg-danger text-white px-2 py-1 shadow-sm" style="display:none; position: fixed; bottom: 20px; right: 68px; z-index: 1000; font-size: 0.75rem;" title="Network disconnected - attempting to reconnect...">
            <i class="bi bi-wifi-off me-1"></i>Reconnecting...
        </span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        window.nagarjunaKds = {
            kitchenId: @json($kitchen?->id),
            branchId: @json($branchId ?? $kitchen?->branch_id),
            kitchenIds: @json($kitchen ? [$kitchen->id] : $allKitchens->pluck('id')->values()),
            feedUrl: @json($kitchen ? route('kds.feed', $kitchen) : route('kds.feed.all')),
            authUrl: @json(route('web.broadcasting.auth')),
            canEdit: @json(auth()->user()->hasPermission('kot.edit')),
            soundAllowed: @json(isset($branchId) || $kitchen ? (bool) app(\App\Services\BranchSettings::class)->forBranch((int) ($branchId ?? $kitchen->branch_id))['sound_enabled'] : true),
            live: {
                key: @json(config('broadcasting.connections.reverb.key')),
                host: @json(config('broadcasting.connections.reverb.options.host')),
                port: @json(config('broadcasting.connections.reverb.options.port')),
                secure: @json(config('broadcasting.connections.reverb.options.scheme') === 'https'),
            },
        };
    </script>
    <script src="{{ asset('js/kds-screen.js') }}"></script>
</body>
</html>
