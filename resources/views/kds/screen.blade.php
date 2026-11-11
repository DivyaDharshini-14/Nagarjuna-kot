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
            grid-template-columns: 260px 1.25fr 320px 230px;
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
            gap: 12px;
            padding: 6px 14px;
        }
        .brand-logo-svg {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
        }
        .brand-headings {
            display: flex;
            flex-direction: column;
        }
        .brand-name {
            color: var(--brand-green);
            font-size: 1.42rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            line-height: 1.05;
            margin: 0;
            text-transform: uppercase;
        }
        .brand-tagline {
            color: var(--brand-green);
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            margin-top: 3px;
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

        /* CARDS CONTAINER - SCROLLBAR REMOVED, CARDS FIT PROPORTIONALLY */
        .cards-list {
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            justify-content: flex-start;
        }

        /* KOT CARD - EXACT REPLICA PROPORTIONED TO FIT VIEWPORT */
        .kot-order-card {
            background: #fffffc;
            border-radius: 16px;
            border: 1.5px solid var(--card-border);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.03);
            display: grid;
            grid-template-columns: 80px 1fr auto;
            align-items: center;
            padding: 10px 14px 10px 12px;
            gap: 12px;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            position: relative;
            flex-shrink: 0;
        }

        .kot-order-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.06);
        }

        /* 1. LEFT ZONE: KOT & TABLE */
        .kot-left-zone {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
        }
        .kot-word {
            font-size: 0.88rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
            letter-spacing: 0.04em;
        }
        .kot-big-num {
            font-size: 1.95rem;
            font-weight: 900;
            color: var(--brand-green);
            line-height: 1.05;
            margin: 2px 0;
            font-family: 'Outfit', sans-serif;
        }
        .table-word {
            font-size: 0.86rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }
        .kot-kitchen-tag {
            font-size: 0.65rem;
            font-weight: 800;
            color: #166534;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 1px 6px;
            margin-top: 3px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            max-width: 82px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 2. MIDDLE ZONE: DISH ITEMS */
        .kot-middle-zone {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding-right: 4px;
            overflow: hidden;
        }
        .dish-item-row {
            display: flex;
            align-items: baseline;
            font-size: 0.94rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.3;
        }
        .dish-item-name {
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dish-item-qty {
            margin-left: 6px;
            font-size: 0.94rem;
            font-weight: 800;
            color: #111827;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .special-note-badge {
            font-size: 0.7rem;
            color: #b91c1c;
            background: #fee2e2;
            padding: 1px 5px;
            border-radius: 4px;
            font-weight: 700;
            display: inline-block;
            margin-top: 1px;
        }

        /* 3. RIGHT ZONE: TIMER / READY BADGE */
        .kot-right-zone {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-width: 100px;
        }

        /* TIMER CAPSULE (YELLOW) */
        .timer-capsule {
            background-color: #f7d235;
            color: #111827;
            font-size: 0.92rem;
            font-weight: 900;
            border-radius: 20px;
            padding: 6px 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            white-space: nowrap;
        }
        .timer-capsule svg {
            width: 16px;
            height: 16px;
            fill: #111827;
        }

        /* READY CAPSULE (GREEN) */
        .ready-capsule {
            background-color: #1f9441;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 900;
            border-radius: 20px;
            padding: 6px 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 5px rgba(31, 148, 65, 0.28);
            white-space: nowrap;
        }
        .ready-capsule svg {
            width: 16px;
            height: 16px;
            fill: #ffffff;
        }

        /* EMPTY COLUMN MESSAGE */
        .empty-column-state {
            text-align: center;
            padding: 40px 14px;
            color: #9ca3af;
            font-weight: 700;
            font-size: 0.95rem;
        }

        /* KITCHEN 86 AVAILABILITY SECTION - DISTINCT PROPER GAP & ROUNDED DESIGN */
        .availability-drawer-section {
            margin-top: 0 !important;
            border-radius: 14px;
            background: #ffffff;
            border: 1.5px solid #146c36;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .availability-drawer-header {
            background: linear-gradient(135deg, #0d5e30 0%, #15803d 100%);
            color: #ffffff;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }

        .availability-drawer-header:hover {
            background: linear-gradient(135deg, #094723 0%, #126b33 100%);
        }

        .availability-drawer-body {
            display: none;
            background: #fffef7;
            padding: 10px 16px;
            border-top: 1px solid #d1fae5;
            max-height: 180px;
            overflow-y: auto;
        }

        .availability-drawer-body.expanded {
            display: block;
        }

        .dish-chip-item {
            background: #ffffff;
            border: 1px solid #dcd5c0;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .dish-chip-item:hover {
            border-color: #0d5e30;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        .dish-chip-soldout {
            background: #fee2e2;
            border-color: #f87171;
            color: #b91c1c;
        }

        /* FLOATING UTILITIES (SOUND, 86, FULLSCREEN, EXIT) */
        .floating-action-bar {
            position: fixed;
            bottom: 12px;
            right: 18px;
            display: flex;
            gap: 8px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.94);
            padding: 5px 10px;
            border-radius: 30px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(8px);
        }
        .floating-btn {
            background: none;
            border: none;
            outline: none;
            padding: 6px;
            cursor: pointer;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            color: #4b5563;
            transition: color 0.15s, transform 0.15s;
        }
        .floating-btn:hover {
            color: #111827;
            transform: scale(1.1);
        }

        /* 86 DISH AVAILABILITY MODAL */
        .modal-custom-header {
            background: var(--brand-green);
            color: #fff;
            padding: 14px 20px;
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
                <!-- Nagarjuna Leaf Logo -->
                <svg class="brand-logo-svg" viewBox="0 0 100 80">
                    <path d="M25,58 C15,38 28,15 52,15 C56,32 46,58 25,58 Z" fill="#0d5e30"/>
                    <path d="M42,60 C52,42 66,28 85,28 C82,46 68,64 42,60 Z" fill="#55b340"/>
                    <path d="M18,65 Q40,52 65,22" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"/>
                </svg>
                <div class="brand-headings">
                    <h1 class="brand-name">NAGARJUNÁ</h1>
                    <span class="brand-tagline">ANDHRA STYLE</span>
                </div>
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

            <!-- 3. KITCHEN IDENTIFIER CARD WITH QUICK STATION SWITCHER -->
            <div class="header-card kitchen-card dropdown">
                <div class="chef-hat-badge" data-bs-toggle="dropdown" role="button" aria-expanded="false" style="cursor: pointer;" title="Switch Kitchen Station">
                    <svg viewBox="0 0 24 24">
                        <path d="M19 10a3 3 0 0 0-2.2-2.9 4 4 0 0 0-7.6 0A3 3 0 0 0 5 10a3 3 0 0 0 1 2.2V17h12v-4.8a3 3 0 0 0 1-2.2zM7 19h10v2H7z"/>
                    </svg>
                </div>
                <div class="kitchen-details dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false" style="cursor: pointer; width: 100%;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h2 id="kitchenName">{{ $kitchen ? $kitchen->name . ' Kitchen' : 'All Kitchens' }}</h2>
                            <p id="kitchenSubtitle">{{ $kitchen ? ($kitchen->branch ? $kitchen->branch->name . ' · TV Display' : 'Kitchen Display Screen') : 'All Active Stations · TV Display' }}</p>
                        </div>
                        <i class="bi bi-chevron-down text-muted ms-1" style="font-size: 0.8rem;"></i>
                    </div>
                </div>
                <ul class="dropdown-menu shadow border-0 py-2" style="font-size: 0.88rem; min-width: 290px; border-radius: 12px; z-index: 1050;">
                    <li>
                        <a class="dropdown-item station-dropdown-item fw-bold {{ !$kitchen ? 'active' : '' }}" id="station-item-all" href="javascript:void(0)" onclick="selectKitchenStation(null, 'All Kitchens', 'All Active Stations · TV Display')">
                            <i class="bi bi-grid-fill me-2 text-primary"></i>All Kitchens (All KOTs)
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    @if(isset($allKitchens))
                        @foreach($allKitchens as $k)
                            <li>
                                <a class="dropdown-item station-dropdown-item d-flex justify-content-between align-items-center {{ $kitchen && $kitchen->id === $k->id ? 'active' : '' }}" id="station-item-{{ $k->id }}" href="javascript:void(0)" onclick="selectKitchenStation({{ $k->id }}, '{{ addslashes($k->name) }} Kitchen', '{{ addslashes($k->branch?->name ?? 'Kitchen') }} · TV Display')">
                                    <div class="pe-2">
                                        <div class="fw-bold text-dark"><i class="bi bi-tv me-2 text-secondary"></i>{{ $k->name }} Kitchen</div>
                                        <div class="text-muted" style="font-size: 0.72rem; padding-left: 22px;">{{ $k->code }} · {{ $k->branch?->name ?? 'Main Branch' }}</div>
                                    </div>
                                    <span class="badge bg-light text-dark border">{{ $k->code }}</span>
                                </a>
                            </li>
                        @endforeach
                    @endif
                </ul>
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

        <!-- KITCHEN 86 AVAILABILITY SECTION WITH EQUAL GAP BELOW 3 BOXES -->
        <section class="availability-drawer-section" id="availabilitySection">
            <div class="availability-drawer-header">
                <div class="d-flex align-items-center gap-2" onclick="toggleDrawer86()" style="cursor: pointer; flex-grow: 1;">
                    <i class="bi bi-slash-circle fs-6 text-warning"></i>
                    <span class="fw-bold" style="font-size: 0.95rem;">Kitchen 86 List (Manage Availability)</span>
                    <span class="badge bg-warning text-dark ms-2" id="badgeSoldOut" style="font-size: 0.72rem;">0 Sold Out</span>
                    <span class="text-white-50 ms-3 small" id="drawerHint" style="font-size: 0.78rem;">Click to view / manage dishes</span>
                    <i class="bi bi-chevron-down text-white-50 ms-1" id="drawerArrow"></i>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="networkPill" class="badge bg-danger text-white px-2 py-1" style="display:none; font-size: 0.72rem;" title="Network disconnected - attempting to reconnect...">
                        <i class="bi bi-wifi-off me-1"></i>Reconnecting...
                    </span>
                    <button class="btn btn-sm btn-outline-light border-0 py-0 px-2" id="soundBtn" onclick="toggleSoundAlert()" title="Toggle Sound Alert">
                        <i class="bi bi-volume-up-fill text-warning" id="soundIcon"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-light border-0 py-0 px-2 text-white" onclick="toggleScreenFull()" title="Full Screen TV Mode">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>
                    <a href="{{ route('kds.index') }}" class="btn btn-sm btn-outline-light border-0 py-0 px-2 text-white" title="Exit KDS">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="availability-drawer-body" id="drawerBody86">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <input type="text" id="dishQuery" class="form-control form-control-sm" placeholder="Search dish..." onkeyup="filterDishes86()" style="max-width: 250px; border-radius: 8px;">
                    <small class="text-secondary" style="font-size: 0.8rem;">Click any dish chip to toggle between Available and Sold Out.</small>
                </div>
                <div class="d-flex flex-wrap gap-2 py-1" id="dishList86"></div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initial station: specific kitchen ID if launched for a kitchen, or null for Master/All Kitchens
        const initialKitchenId = {{ $kitchen ? $kitchen->id : 'null' }};
        let activeKitchenId = initialKitchenId;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let soundEnabled = true;
        let audioContext = null;
        let knownIds = new Set();
        let dishesCache = [];

        // Switch Kitchen Station dynamically without reloading page
        function selectKitchenStation(id, title, subtitle) {
            activeKitchenId = id;
            document.getElementById('kitchenName').innerText = title;
            document.getElementById('kitchenSubtitle').innerText = subtitle;
            document.title = `${title} – Kitchen Display Screen | Nagarjuna`;

            document.querySelectorAll('.station-dropdown-item').forEach(el => el.classList.remove('active'));
            const targetItem = document.getElementById(id ? `station-item-${id}` : 'station-item-all');
            if (targetItem) targetItem.classList.add('active');

            pullFeed();
            loadDishes86();
        }

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

        function toggleSoundAlert() {
            soundEnabled = !soundEnabled;
            const icon = document.getElementById('soundIcon');
            if (soundEnabled) {
                icon.className = 'bi bi-volume-up-fill text-warning';
                playAlertSound();
            } else {
                icon.className = 'bi bi-volume-mute-fill text-white-50';
            }
        }

        function toggleScreenFull() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        }

        // Format Seconds to "MM:SS"
        function formatSecs(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }

        // Real-Time Second Increment for Active Card Timers
        setInterval(() => {
            document.querySelectorAll('.live-card-timer').forEach(el => {
                let sec = parseInt(el.getAttribute('data-sec') || '0', 10);
                sec++;
                el.setAttribute('data-sec', sec);
                el.innerText = formatSecs(sec);
            });
        }, 1000);

        // Generate EXACT KOT Card HTML
        function buildCardHtml(ticket, stage) {
            // Extract short sequence or readable number from kot_number
            let kotNum = ticket.kot_number;
            const match = String(kotNum).match(/(\d+)$/);
            if (match) {
                kotNum = parseInt(match[1], 10);
            }

            // Table string (e.g. "Table Two 2")
            let tableStr = ticket.table_name || '1';
            if (!tableStr.toLowerCase().startsWith('table')) {
                tableStr = `Table ${tableStr}`;
            }

            // Right Zone Badge & Click Action
            let rightZoneHtml = '';
            let clickAction = '';

            if (stage === 'new') {
                const timeText = ticket.elapsed_display || formatSecs(ticket.elapsed_seconds || 0);
                rightZoneHtml = `
                    <div class="timer-capsule" title="Click to Start Preparing">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" fill="none" stroke="#111827" stroke-width="2"/>
                            <polyline points="12 7 12 12 15 14" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span class="live-card-timer" data-sec="${ticket.elapsed_seconds || 0}">${timeText}</span>
                    </div>
                `;
                clickAction = `onclick="progressTicket(${ticket.id}, 'preparing')"`;
            } else if (stage === 'preparing') {
                const timeText = ticket.elapsed_display || formatSecs(ticket.elapsed_seconds || 0);
                rightZoneHtml = `
                    <div class="timer-capsule" title="Click to Mark Ready">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" fill="none" stroke="#111827" stroke-width="2"/>
                            <polyline points="12 7 12 12 15 14" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span class="live-card-timer" data-sec="${ticket.elapsed_seconds || 0}">${timeText}</span>
                    </div>
                `;
                clickAction = `onclick="progressTicket(${ticket.id}, 'ready')"`;
            } else if (stage === 'ready') {
                rightZoneHtml = `
                    <div class="ready-capsule" title="Click to Mark Served">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>Ready</span>
                    </div>
                `;
                clickAction = `onclick="progressTicket(${ticket.id}, 'served')"`;
            }

            // Middle Dishes list: "Veg meals × 2"
            const dishesHtml = (ticket.items || []).map(item => `
                <div class="dish-item-row">
                    <span class="dish-item-name">${item.dish_name}</span>
                    <span class="dish-item-qty">× ${item.quantity}</span>
                </div>
                ${item.special_instructions ? `<div class="special-note-badge">${item.special_instructions}</div>` : ''}
            `).join('');

            const kitchenTag = ticket.kitchen_name ? `<span class="kot-kitchen-tag">${ticket.kitchen_name}</span>` : '';

            return `
                <div class="kot-order-card" id="card-${ticket.id}" ${clickAction} title="${ticket.kot_number}">
                    <!-- 1. LEFT ZONE -->
                    <div class="kot-left-zone">
                        <span class="kot-word">KOT</span>
                        <span class="kot-big-num">${kotNum}</span>
                        <span class="table-word">${tableStr}</span>
                        ${kitchenTag}
                    </div>

                    <!-- 2. MIDDLE ZONE -->
                    <div class="kot-middle-zone">
                        ${dishesHtml}
                    </div>

                    <!-- 3. RIGHT ZONE -->
                    <div class="kot-right-zone">
                        ${rightZoneHtml}
                    </div>
                </div>
            `;
        }

        // Advance Ticket Preparation Status
        async function progressTicket(ticketId, targetStatus) {
            try {
                const card = document.getElementById(`card-${ticketId}`);
                if (card) {
                    card.style.opacity = '0.5';
                    card.style.pointerEvents = 'none';
                }

                const res = await fetch(`/kds/tickets/${ticketId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ status: targetStatus })
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    alert(errData.message || 'Failed to update order status');
                    if (card) {
                        card.style.opacity = '1';
                        card.style.pointerEvents = 'auto';
                    }
                    return;
                }

                // Immediately pull updated feed
                pullFeed();
            } catch(e) {
                console.error(e);
                pullFeed();
            }
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

        // Kitchen 86 Drawer Toggle & Loading
        async function toggleDrawer86() {
            const body = document.getElementById('drawerBody86');
            const arrow = document.getElementById('drawerArrow');
            const hint = document.getElementById('drawerHint');
            const isExpanded = body.classList.toggle('expanded');
            arrow.className = isExpanded ? 'bi bi-chevron-up text-white-50 ms-1' : 'bi bi-chevron-down text-white-50 ms-1';
            hint.innerText = isExpanded ? 'Click to collapse' : 'Click to view / manage dishes';
            if (isExpanded) {
                loadDishes86();
            }
        }

        async function loadDishes86() {
            try {
                const url = activeKitchenId ? `/kds/${activeKitchenId}/dishes` : `/kds/dishes`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                dishesCache = data.dishes || [];
                renderDishes86(dishesCache);
            } catch(e) {}
        }

        function renderDishes86(dishes) {
            const el = document.getElementById('dishList86');
            const badge = document.getElementById('badgeSoldOut');
            const soldOutCount = dishes.filter(d => !d.is_available).length;
            badge.innerText = `${soldOutCount} Sold Out`;

            if (dishes.length === 0) {
                el.innerHTML = '<div class="text-secondary small py-2">No dishes mapped to this station.</div>';
                return;
            }
            el.innerHTML = dishes.map(d => `
                <div class="dish-chip-item ${d.is_available ? '' : 'dish-chip-soldout'} dish-row-86" data-name="${d.name.toLowerCase()}" onclick="changeAvailability(${d.id}, '${d.is_available ? 'sold_out' : 'available'}')">
                    <span>${d.name}</span>
                    <span class="badge ${d.is_available ? 'bg-success' : 'bg-danger'}">${d.is_available ? 'Available' : 'SOLD OUT'}</span>
                </div>
            `).join('');
        }

        function filterDishes86() {
            const q = document.getElementById('dishQuery').value.toLowerCase();
            document.querySelectorAll('.dish-row-86').forEach(item => {
                const name = item.getAttribute('data-name');
                item.style.display = name.includes(q) ? 'inline-flex' : 'none';
            });
        }

        async function changeAvailability(dishId, target) {
            try {
                const url = activeKitchenId 
                    ? `/kds/${activeKitchenId}/dishes/${dishId}/toggle-availability` 
                    : `/kds/dishes/${dishId}/toggle-availability`;
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ availability: target })
                });
                loadDishes86();
            } catch(e) {}
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
