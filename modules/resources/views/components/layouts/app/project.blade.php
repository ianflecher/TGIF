<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Add custom green theme colors -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    
    <title>TGIF Project Management Module</title>

    <style>
        :root {
            --primary-green: #22c55e;
            --dark-green: #15803d;
            --light-green: #dcfce7;
            --forest-green: #14532d;
            --mint-green: #bbf7d0;
        }
        
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: 'Poppins','Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%);
            color: #1f2b16;
        }
        
        /* Header */
        .main-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--forest-green) 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            box-shadow: 0 2px 12px rgba(34, 197, 94, 0.2);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
            display: flex;
            align-items: center;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .logo-container:hover {
            opacity: 0.9;
        }
        
        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .company-name {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: white;
        }
        
        .company-tagline {
            font-size: 0.75rem;
            opacity: 0.9;
            color: var(--mint-green);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 5px 12px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, var(--forest-green) 0%, #0f4223 100%);
            color: white;
            width: 65px;
            min-height: calc(100vh - 60px);
            position: fixed;
            left: 0;
            top: 60px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 15px 0;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
        }
        
        .sidebar:hover {
            width: 200px;
        }
        
        .sidebar h3 {
            padding: 0 15px;
            margin-bottom: 15px;
            color: var(--mint-green);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            opacity: 0;
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }
        
        .sidebar:hover h3 {
            opacity: 1;
        }
        
        .sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar nav ul li {
            margin-bottom: 2px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #e2e8f0;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 0 5px;
            font-weight: 500;
            white-space: nowrap;
            position: relative;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-item.active {
            background: var(--primary-green);
            color: white;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .main-content {
            margin-left: 65px;
            margin-top: 60px;
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%);
            transition: margin-left 0.3s ease;
        }
        
        .sidebar:hover + .main-content,
        .main-content:hover {
            margin-left: 200px;
        }
        
        .module-icon {
            min-width: 24px;
            text-align: center;
            font-size: 1.2rem;
            margin-right: 0;
            transition: margin-right 0.3s ease;
        }
        
        .sidebar:hover .module-icon {
            margin-right: 12px;
        }
        
        .nav-text {
            opacity: 0;
            transition: opacity 0.3s ease;
            font-size: 0.9rem;
        }
        
        .sidebar:hover .nav-text {
            opacity: 1;
        }
        
        .status-badge {
            background: var(--primary-green);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar:hover .status-badge {
            opacity: 1;
        }
        
        /* Tooltip for compact mode */
        .nav-tooltip {
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--forest-green);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            margin-left: 10px;
        }
        
        .nav-tooltip::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            border-width: 6px;
            border-style: solid;
            border-color: transparent var(--forest-green) transparent transparent;
        }
        
        .nav-item:hover .nav-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .sidebar:hover .nav-tooltip {
            display: none;
        }
        
        /* Form styling fixes */
        input, textarea, select {
            background-color: white !important;
            color: black !important;
        }

        input::placeholder, textarea::placeholder {
            color: gray !important;
        }

        input[type="date"] {
            color-scheme: light;
            background-color: white !important;
            color: black !important;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0);
        }

        input[type="number"] {
            color-scheme: light;
            background-color: white !important;
            color: black !important;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            filter: invert(0);
        }
        
        /* Green scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-green);
            border-radius: 2px;
        }
        
        /* Animation for active state */
        @keyframes gentle-pulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3); }
            50% { box-shadow: 0 2px 12px rgba(34, 197, 94, 0.5); }
        }
        
        .nav-item.active {
            animation: gentle-pulse 3s infinite;
        }
        
        /* Mobile menu toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            margin-right: 10px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .sidebar {
                width: 55px;
            }
            
            .sidebar:hover {
                width: 180px;
            }
            
            .main-content {
                margin-left: 55px;
                padding: 1rem;
            }
            
            .sidebar:hover + .main-content {
                margin-left: 180px;
            }
            
            .company-name {
                font-size: 1rem;
            }
            
            .company-tagline {
                display: none;
            }
            
            .user-badge span:last-child {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
                width: 200px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        /* Keep all your existing component styles below */
        /* Cards */
        .dashboard-cards {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
            gap:1rem;
        }
        .card {
            background:#fff;
            padding:1rem;
            border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,0.08);
        }
        .card-title { font-size:1rem; margin-bottom:0.5rem; color:#1b5e20; }
        .card-value { font-size:1.6rem; font-weight:600; color:#2e7d32; }

        /* Table */
        .data-table { margin-top:1.5rem; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);}
        .table-header { background:#2e7d32; color:#fff; padding:1rem; display:flex; justify-content:space-between; align-items:center; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:0.75rem 1rem; border-bottom:1px solid #eee; text-align:left; }
        th { background:#f8f9fa; color:#2c5530; }

        /* Buttons */
        .btn { padding:0.5rem 1rem; border:none; border-radius:6px; cursor:pointer; }
        .btn-primary { background:#2e7d32; color:#fff; }
        .btn-warning { background:#ffb300; color:#1f2b16; }
        .btn-success { background:#43a047; color:#fff; }
        .btn-danger { background:#e53935; color:#fff; }

        /* Forms */
        .form-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap:0.75rem;
            margin:1rem 0;
        }
        .form-grid input, .form-grid select { padding:0.6rem 0.8rem; border:1px solid #d6e3d3; border-radius:8px; }

        /* Modal */
        .modal { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; padding:1rem; z-index:1000; }
        .modal[aria-hidden="false"] { display:flex; }
        .modal-dialog { background:#fff; border-radius:10px; width:100%; max-width:520px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; padding:1rem; background:#2e7d32; color:#fff; }
        .modal-body { padding:1rem; display:grid; gap:0.5rem; }
        .modal-footer { padding:0.75rem 1rem; background:#f7f7f7; display:flex; justify-content:flex-end; gap:0.5rem; }

        /* Keep all your other existing styles below */
        #gantt {
            width: 100%;
            min-height: 400px;
        }

        .gantt-tooltip {
            background: #fff;
            border: 1px solid #ccc;
            padding: 6px;
            font-size: 13px;
            border-radius: 4px;
        }

        /* ===== Friesday Modal Wrapper ===== */
        .friesday-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1000;
        }

        /* ===== Modal Box ===== */
        .friesday-modal-box {
            width: 100%;
            max-width: 90rem;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Add all your other component styles here... */
        /* (They will all work with the new layout) */

        .back-link {
            display: inline-block;
            padding: 0.5rem 1.2rem;
            border-radius: 9999px;
            background: transparent;
            color: #15803d;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease, transform 0.1s ease;
        }

        .back-link:hover {
            background-color: #16a34a;
            color: #fff;
            transform: scale(1.05);
        }

        /* --- Container --- */
.phase-container {
    max-width: 1200px;
    margin: 2rem auto;
    background: #ffffff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    font-family: "Segoe UI", sans-serif;
}

/* --- Header --- */
.phase-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #22c55e; /* green main */
    padding-bottom: 0.75rem;
    margin-bottom: 1.5rem;
}

.phase-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #166534; /* dark green */
}

.phase-back-btn {
    text-decoration: none;
    background: #166534;
    color: #fff;
    border-radius: 6px;
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
    transition: background 0.2s ease;
}

.phase-back-btn:hover {
    background: #22c55e;
}

/* --- Table --- */
.phase-table-container {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #e5e7eb; /* gray border */
    background: #f9fafb; /* light gray bg */
    
     width: 100%;
}

.phase-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.phase-table thead {
    background: #dcfce7; /* light green */
    color: #166534;
}

.phase-table th, 
.phase-table td {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.phase-table th {
    font-weight: 600;
}

.phase-table tr:hover {
    background: #f0fdf4; /* soft hover green */
}

/* --- Buttons --- */
.phase-btn {
    border: none;
    border-radius: 6px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    cursor: pointer;
    color: #fff;
    transition: background 0.2s ease, transform 0.1s ease;
}

.phase-btn:hover {
    transform: translateY(-1px);
}

/* Button colors */
.phase-btn-green {
    background: #22c55e;
}
.phase-btn-green:hover {
    background: #166534;
}

.phase-btn-yellow {
    background: #facc15;
    color: #1f2937;
}
.phase-btn-yellow:hover {
    background: #eab308;
    color: #fff;
}

.phase-btn-red {
    background: #ef4444;
}
.phase-btn-red:hover {
    background: #b91c1c;
}

/* --- No Data --- */
.phase-no-data {
    text-align: center;
    color: #6b7280;
    padding: 2rem 0;
    font-style: italic;
}

.resources-container {
    max-width: 1100px;
    margin: 2rem auto;
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    font-family: "Segoe UI", sans-serif;
}
.resources-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #22c55e;
    padding-bottom: 0.75rem;
    margin-bottom: 1.5rem;
}
.resources-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #166534;
}

/* === Table === */
.resources-table-wrapper {
    overflow-x: auto;
}
.resources-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}
.resources-table th, .resources-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.resources-table thead {
    background: #dcfce7;
    color: #166534;
    font-weight: 600;
}
.resources-table tr:hover {
    background: #f0fdf4;
}
.resources-status {
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}
.resources-status.active { background: #bbf7d0; color: #14532d; }
.resources-status.unavailable { background: #fee2e2; color: #991b1b; }

/* === Buttons === */
.resources-btn {
    border: none;
    border-radius: 6px;
    padding: 0.45rem 0.9rem;
    font-size: 0.85rem;
    cursor: pointer;
    color: #fff;
    transition: all 0.2s ease;
}
.resources-btn:hover { transform: translateY(-1px); }
.resources-btn-green { background: #22c55e; }
.resources-btn-green:hover { background: #15803d; }
.resources-btn-yellow { background: #facc15; color: #1f2937; }
.resources-btn-yellow:hover { background: #eab308; color: #fff; }
.resources-btn-red { background: #ef4444; }
.resources-btn-red:hover { background: #b91c1c; }
.resources-btn-gray { background: #6b7280; }

/* === Modal === */
.resources-modal {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.45);
    display: flex;
    justify-content: center;
    align-items: flex-start; /* move modal to top */
    padding-top: 100px; 
    animation: fadeIn 0.2s ease-in-out;
}
.resources-modal-box {
    background: #fff;
    border-radius: 14px;
    padding: 1.8rem;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    animation: slideUp 0.25s ease-in-out;
}
.resources-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.resources-modal-header h3 {
    color: #166534;
    font-weight: 700;
}
.resources-close-btn {
    background: none;
    border: none;
    font-size: 1.3rem;
    cursor: pointer;
    color: #475569;
}
.resources-close-btn:hover { color: #14532d; }

/* === Form === */
.resources-form-grid {
    display: grid;
    gap: 0.8rem;
}
.resources-form-grid label {
    display: flex;
    flex-direction: column;
    font-weight: 500;
    color: #374151;
}
.resources-form-grid input {
    padding: 0.45rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    transition: border-color 0.2s ease;
}
.resources-form-grid input:focus {
    border-color: #22c55e;
    outline: none;
}
.resources-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
}

/* === Empty State === */
.resources-empty {
    text-align: center;
    color: #6b7280;
    padding: 2rem 0;
    font-style: italic;
}

/* Container */
.task-costs-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: Arial, sans-serif;
}

/* Task title */
.task-title {
    font-size: 28px;
    font-weight: bold;
    color: #15803d; /* green */
    margin-bottom: 30px;
}

/* Cost Card */
.cost-card {
    background-color: #ffffff;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}
.cost-card:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

/* Cost type header */
.cost-type {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 10px;
}

/* Cost list */
.cost-list {
    list-style: disc inside;
    margin-left: 0;
    padding-left: 0;
    margin-bottom: 10px;
}
.cost-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    color: #374151;
}

/* Cost value */
.cost-value {
    font-weight: 600;
    color: #15803d;
}

/* Total per type */
.cost-total {
    margin-top: 10px;
    font-weight: 600;
    border-top: 1px solid #e5e7eb;
    padding-top: 8px;
    color: #111827;
}

/* Empty message */
.cost-empty {
    color: #9ca3af;
    font-style: italic;
}

/* Total task cost */
.total-task-cost {
    background-color: #f0fdf4;
    border: 1px solid #dcfce7;
    padding: 15px 20px;
    border-radius: 15px;
    font-weight: 600;
    font-size: 18px;
    display: flex;
    justify-content: space-between;
    margin-top: 25px;
    color: #111827;
}
.total-task-cost .total-value {
    color: #15803d;
    font-size: 20px;
    font-weight: bold;
}

/* === Animations === */
@keyframes fadeIn {
    from { opacity: 0; } to { opacity: 1; }
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.resources-form-grid select {
    padding: 0.45rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background-color: #fff;
    color: #111827;
    transition: border-color 0.2s ease;
}
.resources-form-grid select:focus {
    border-color: #22c55e;
    outline: none;
}

.resources-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.resources-btn-gray {
    background-color: #6c757d;
    color: white;
    border: none;
    border-radius: 5px;
    padding: 8px 14px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.resources-btn-gray:hover {
    background-color: #5a6268;
}
        
    </style>
</head>
<body>

<!-- Main Header -->
<header class="main-header">
    <div class="header-content">
        <button class="menu-toggle" onclick="toggleMobileMenu()">☰</button>
        <!-- Make logo container clickable -->
        <a href="{{ route('projects.home') ?: route('projects.projects') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 40px; width: auto;">
                @else
                    <div style="background: white; color: var(--dark-green); font-weight: bold; padding: 4px 8px; border-radius: 6px;">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">TGIF Project Management</div>
                <div class="company-tagline">Admin Portal</div>
            </div>
        </a>
        <div class="user-info">
            <div class="user-badge">
                <span style="color: var(--mint-green);">👤</span>
                <span>{{ Auth::user()->name ?? 'User' }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn">
                    Logout
                </button>
            </form>
        </div>
    </div>
</header>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <h3>Navigation</h3>
    <nav>
        <ul>
            <!-- Make icon clickable -->
            <li>
                <a href="{{ route('projects.projects') }}" class="nav-item {{ request()->routeIs('projects.projects') ? 'active' : '' }}">
                    <span class="module-icon">📋</span>
                    <span class="nav-text">Project Planning</span>
                    <span class="nav-tooltip">Project Planning & Scheduling</span>
                </a>
            </li>
            <!-- Add more navigation items as needed -->
            <li>
                <a href="{{ route('projects.budget') }}" class="nav-item {{ request()->routeIs('projects.viewresources') ? 'active' : '' }}">
                    <span class="module-icon">📦</span>
                    <span class="nav-text">Budget</span>
                    <span class="nav-tooltip">Budget Management</span>
                </a>
            </li>

        </ul>
    </nav>
</aside>

<!-- Main Content -->
<main class="main-content" id="mainContent">
    {{ $slot }}
</main>

<!-- JavaScript for Interactions -->
<script>
    // Toggle mobile menu
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (window.innerWidth <= 480 && 
            !sidebar.contains(event.target) && 
            !menuToggle.contains(event.target) && 
            sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });
    
    // Handle sidebar hover behavior
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    sidebar.addEventListener('mouseenter', function() {
        if (window.innerWidth > 768) {
            this.style.width = '200px';
            mainContent.style.marginLeft = '200px';
        }
    });
    
    sidebar.addEventListener('mouseleave', function() {
        if (window.innerWidth > 768) {
            this.style.width = '65px';
            mainContent.style.marginLeft = '65px';
        }
    });
    
    // Update active state
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && currentPath.includes(href.replace(/\/$/, '')) && href !== '/') {
                item.classList.add('active');
            }
        });
        
        // Auto-close mobile menu on item click
        if (window.innerWidth <= 480) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', () => {
                    sidebar.classList.remove('mobile-open');
                });
            });
        }
        
        // Make sure the logo container works properly
        const logoContainer = document.querySelector('.logo-container');
        if (logoContainer) {
            logoContainer.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                if (href) {
                    window.location.href = href;
                }
            });
        }
    });
    
    // Handle logo click with animation
    document.querySelectorAll('.logo-container').forEach(logo => {
        logo.addEventListener('click', function() {
            // Add a subtle click effect
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
</script>

</body>
</html>