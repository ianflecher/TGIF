<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Inventory Management') - TGIF Supplier Portal</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom green inventory theme -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        inventory: {
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
                            950: '#052e16',
                        },
                        forest: {
                            50: '#f7fee7',
                            100: '#ecfccb',
                            200: '#d9f99d',
                            300: '#bef264',
                            400: '#a3e635',
                            500: '#84cc16',
                            600: '#65a30d',
                            700: '#4d7c0f',
                            800: '#3f6212',
                            900: '#365314',
                        },
                        sage: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        },
                        accent: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            200: '#99f6e4',
                            300: '#5eead4',
                            400: '#2dd4bf',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --primary-green: #22c55e;
            --dark-green: #15803d;
            --deep-green: #14532d;
            --accent-teal: #14b8a6;
            --light-green: #dcfce7;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%);
            min-height: 100vh;
        }
        
        /* Header */
        .inventory-header {
            background: linear-gradient(135deg, var(--deep-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(20, 83, 45, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        
        .logo-container:hover {
            transform: translateY(-1px);
        }
        
        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .company-name {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .company-tagline {
            font-size: 0.85rem;
            opacity: 0.9;
            color: #bbf7d0;
        }
        
        /* Navigation */
        .inventory-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-teal) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 2px;
            background: white;
            border-radius: 2px;
        }
        
        /* User Actions */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1.25rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .user-badge.inventory {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.8) 0%, rgba(20, 184, 166, 0.8) 100%);
        }
        
        .user-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        /* Main Content */
        .inventory-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 4px solid var(--primary-green);
            color: #166534;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-left: 4px solid var(--accent-teal);
            color: #0d9488;
        }
        
        /* Page Cards */
        .page-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(34, 197, 94, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            border-top: 4px solid var(--primary-green);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .page-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(34, 197, 94, 0.12);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--deep-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(20, 83, 45, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .page-header p {
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 1;
            color: #bbf7d0;
        }
        
        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 14px;
            padding: 1.75rem;
            box-shadow: 0 6px 24px rgba(34, 197, 94, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-green) 0%, var(--accent-teal) 100%);
        }
        
        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(34, 197, 94, 0.15);
        }
        
        .dashboard-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dashboard-card p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .stat-number {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        /* Status Badges for Inventory */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid transparent;
        }
        
        .status-in-stock {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            border-color: #86efac;
        }
        
        .status-low-stock {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e;
            border-color: #fbbf24;
        }
        
        .status-out-of-stock {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-color: #f87171;
        }
        
        .status-on-order {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border-color: #93c5fd;
        }
        
        .status-discontinued {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #64748b;
            border-color: #cbd5e1;
        }
        
        .status-expired {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #475569;
            border: 1px dashed #94a3b8;
        }
        
        /* Tables for Inventory Data */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1.5rem 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
        }
        
        .data-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.25rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #334155;
            border-bottom: 2px solid #cbd5e1;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            background: white;
        }
        
        .data-table tr {
            transition: all 0.2s ease;
        }
        
        .data-table tr:hover {
            background: #f0fdf4;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-teal) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.4);
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1.75rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
            font-size: 0.95rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.85rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
        }
        
        /* Inventory-specific styles */
        .inventory-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .inventory-sidebar {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 6px 24px rgba(34, 197, 94, 0.08);
            height: fit-content;
            border: 1px solid #e2e8f0;
        }
        
        .inventory-sidebar h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .inventory-item-card {
            background: white;
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--primary-green);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .inventory-item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.15);
            border-left: 4px solid var(--accent-teal);
        }
        
        .inventory-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .inventory-item-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1.15rem;
        }
        
        .inventory-item-sku {
            color: #64748b;
            font-size: 0.85rem;
            font-family: 'Monaco', 'Consolas', monospace;
            background: #f1f5f9;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .inventory-item-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.35rem;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #334155;
            font-size: 1rem;
        }
        
        .stock-level {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .stock-bar {
            flex-grow: 1;
            height: 10px;
            background: #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }
        
        .stock-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stock-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .stock-fill.high {
            background: linear-gradient(90deg, var(--primary-green) 0%, #4ade80 100%);
        }
        
        .stock-fill.medium {
            background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
        }
        
        .stock-fill.low {
            background: linear-gradient(90deg, #ef4444 0%, #f87171 100%);
        }
        
        .stock-fill.critical {
            background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .mobile-nav {
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--deep-green);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-teal) 100%);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
            
            .inventory-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .inventory-header {
                padding: 0.75rem 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .inventory-nav {
                display: none;
            }
            
            .mobile-nav.show {
                display: flex;
            }
            
            .header-content {
                flex-wrap: wrap;
            }
            
            .logo-container {
                flex: 1;
            }
            
            .user-actions {
                width: 100%;
                justify-content: space-between;
                margin-top: 0.75rem;
                padding-top: 0.75rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .inventory-content {
                padding: 0 1rem;
            }
            
            .page-card {
                padding: 1.5rem;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
            
            .inventory-item-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .company-name {
                font-size: 1.2rem;
            }
            
            .company-tagline {
                font-size: 0.75rem;
            }
            
            .user-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .logout-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 0.6rem 1.25rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Main Header -->
<header class="inventory-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('inventory.home') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 48px; width: auto; filter: brightness(0) invert(1);">
                @else
                    <div style="background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-teal) 100%); color: white; font-weight: bold; padding: 10px 14px; border-radius: 10px; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">Green Inventory Management</div>
            </div>
        </a>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation -->
        <nav class="inventory-nav">
            <a href="{{ route('inventory.home') }}" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="{{ route('inventory.stock') }}" class="nav-link">
                <i class="fas fa-boxes"></i>Stock Management
            </a>
            <a href="{{ route('inventory.tracking') }}" class="nav-link">
                <i class="fas fa-tags"></i>Inventory Tracking
            </a>
        </nav>

        <!-- User Actions -->
        <div class="user-actions">
            @auth
                @if(Auth::user()->role === 'inventory_manager')
                    <div class="user-badge inventory">
                        <i class="fas fa-clipboard-list"></i>
                        <span>{{ Auth::user()->full_name ?? 'Inventory Manager' }}</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </button>
                    </form>
                @endif
            @else
                <a href="{{ route('inventory.login') }}" class="nav-link">
                    <i class="fas fa-sign-in-alt"></i>Login
                </a>
            @endauth
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav" id="mobileMenu">
        <a href="{{ route('inventory.home') }}" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
        <a href="{{ route('inventory.stock') }}" class="nav-link">
            <i class="fas fa-boxes"></i>Stock Management
        </a>
        <a href="{{ route('inventory.tracking') }}" class="nav-link">
            <i class="fas fa-tags"></i>Inventory Tracking
        </a>
    </nav>
</header>

<!-- Main Content Area -->
<main class="inventory-content">
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
        </div>
    @endif
    
    @if(session('warning'))
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            {{ session('warning') }}
        </div>
    @endif
    
    @if(session('info'))
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            {{ session('info') }}
        </div>
    @endif
    
    <!-- Page Content -->
    {{ $slot }}
</main>

<!-- JavaScript -->
<script>
    // Highlight active navigation link
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPath || (href !== '/' && currentPath.includes(href.replace(/\/$/, '')))) {
                link.classList.add('active');
            }
        });
        
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('show');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!mobileMenu.contains(event.target) && !mobileMenuBtn.contains(event.target) && mobileMenu.classList.contains('show')) {
                    mobileMenu.classList.remove('show');
                }
            });
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    });
    
    // Inventory-specific functions
    function updateStockLevel(itemId, newQuantity) {
        // This would typically make an API call to update stock
        console.log(`Updating item ${itemId} to ${newQuantity} units`);
        
        // Update UI immediately
        const stockElement = document.getElementById(`stock-${itemId}`);
        const stockBar = document.getElementById(`stock-bar-${itemId}`);
        
        if (stockElement && stockBar) {
            stockElement.textContent = newQuantity;
            
            // Animate stock change
            stockElement.style.transform = 'scale(1.2)';
            setTimeout(() => {
                stockElement.style.transform = 'scale(1)';
                stockElement.style.transition = 'transform 0.3s ease';
            }, 300);
            
            // Update stock bar with animation
            let width = '0%';
            let className = 'stock-fill critical';
            
            if (newQuantity >= 100) {
                width = '80%';
                className = 'stock-fill high';
            } else if (newQuantity >= 50) {
                width = '50%';
                className = 'stock-fill medium';
            } else if (newQuantity >= 10) {
                width = '20%';
                className = 'stock-fill low';
            } else {
                width = '5%';
                className = 'stock-fill critical';
            }
            
            stockBar.className = className;
            stockBar.style.width = width;
            
            // Show success message
            showAlert('Stock updated successfully', 'success');
        }
    }
    
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;
        
        const contentArea = document.querySelector('.inventory-content');
        if (contentArea) {
            contentArea.insertBefore(alertDiv, contentArea.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertDiv.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateY(-10px)';
                setTimeout(() => alertDiv.remove(), 500);
            }, 5000);
        }
    }
    
    // Smooth scroll for page cards
    function scrollToElement(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    // Add loading animation to buttons when clicked
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn') || e.target.closest('.btn')) {
            const btn = e.target.classList.contains('btn') ? e.target : e.target.closest('.btn');
            const originalContent = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            btn.style.pointerEvents = 'none';
            
            // Reset after 2 seconds (in real app, this would be after API call completes)
            setTimeout(() => {
                btn.innerHTML = originalContent;
                btn.style.pointerEvents = 'auto';
            }, 2000);
        }
    });
</script>

</body>
</html>