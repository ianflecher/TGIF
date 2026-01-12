<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Helpdesk System') - TGIF Support Portal</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom green helpdesk theme -->
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
        .helpdesk-header {
            background: linear-gradient(135deg, var(--deep-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(20, 83, 45, 0.2);
            position: sticky;
            top: 0;
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
        .helpdesk-nav {
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
        
        .user-badge.support {
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
        .helpdesk-content {
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
        
        /* Status Badges for Tickets */
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
        
        .status-open {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            border-color: #86efac;
        }
        
        .status-in-progress {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e;
            border-color: #fbbf24;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-color: #f87171;
        }
        
        .status-resolved {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border-color: #93c5fd;
        }
        
        .status-closed {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #64748b;
            border-color: #cbd5e1;
        }
        
        .status-escalated {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #475569;
            border: 1px dashed #94a3b8;
        }
        
        /* Priority Badges */
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid transparent;
        }
        
        .priority-critical {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border-color: #f87171;
            animation: pulse 2s infinite;
        }
        
        .priority-high {
            background: linear-gradient(135deg, #fef2f2 0%, #fed7d7 100%);
            color: #ef4444;
            border-color: #fca5a5;
        }
        
        .priority-medium {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #d97706;
            border-color: #fbbf24;
        }
        
        .priority-low {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #16a34a;
            border-color: #86efac;
        }
        
        /* Tables for Ticket Data */
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
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
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
        
        .form-textarea {
            width: 100%;
            padding: 0.85rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            min-height: 150px;
            resize: vertical;
            font-family: 'Inter', sans-serif;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 0.85rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.5em 1.5em;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
        }
        
        /* Helpdesk-specific styles */
        .helpdesk-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }
        
        .helpdesk-sidebar {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 6px 24px rgba(34, 197, 94, 0.08);
            height: fit-content;
            border: 1px solid #e2e8f0;
        }
        
        .helpdesk-sidebar h3 {
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
        
        .ticket-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--primary-green);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .ticket-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.15);
            border-left: 4px solid var(--accent-teal);
        }
        
        .ticket-card.critical {
            border-left: 4px solid #ef4444;
            animation: pulse-card 2s infinite;
        }
        
        @keyframes pulse-card {
            0%, 100% { box-shadow: 0 4px 16px rgba(239, 68, 68, 0.1); }
            50% { box-shadow: 0 4px 24px rgba(239, 68, 68, 0.2); }
        }
        
        .ticket-card.high {
            border-left: 4px solid #f59e0b;
        }
        
        .ticket-card.medium {
            border-left: 4px solid #3b82f6;
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .ticket-title {
            font-weight: 600;
            color: #1e293b;
            font-size: 1.15rem;
            margin-bottom: 0.5rem;
        }
        
        .ticket-id {
            color: #64748b;
            font-size: 0.85rem;
            font-family: 'Monaco', 'Consolas', monospace;
            background: #f1f5f9;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .ticket-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .ticket-description {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }
        
        .ticket-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .message-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border: 1px solid #e2e8f0;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .message-author {
            font-weight: 600;
            color: #1e293b;
        }
        
        .message-time {
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .message-content {
            color: #475569;
            line-height: 1.6;
        }
        
        .message-attachments {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .attachment-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #f1f5f9;
            border-radius: 6px;
            color: #475569;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }
        
        .attachment-item:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.15);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
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
            
            .helpdesk-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .helpdesk-header {
                padding: 0.75rem 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .helpdesk-nav {
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
            
            .helpdesk-content {
                padding: 0 1rem;
            }
            
            .page-card {
                padding: 1.5rem;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
            
            .ticket-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .ticket-actions {
                flex-wrap: wrap;
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
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Main Header -->
<header class="helpdesk-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('sales.home') }}" class="logo-container">
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
                <div class="company-tagline">Support Helpdesk System</div>
            </div>
        </a>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation -->
        <nav class="helpdesk-nav">
            <a href="{{ route('sales.home') }}" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="{{ route('sales.ticket') }}" class="nav-link">
                <i class="fas fa-ticket-alt"></i>Tickets
            </a>
        </nav>

        <!-- User Actions -->
        <div class="user-actions">
                <div class="user-badge support">
                    <i class="fas fa-headset"></i>
                    <span>{{ Auth::user()->full_name ?? 'Support Agent' }}</span>
                </div>
                <!-- LOGOUT FORM -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </button>
                </form>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav" id="mobileMenu">
        <a href="{{ route('sales.home') }}" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
        <a href="{{ route('sales.ticket') }}" class="nav-link">
            <i class="fas fa-ticket-alt"></i>Tickets
        </a>
        <!-- LOGOUT FOR MOBILE -->
        @auth
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
            <div style="color: #bbf7d0; font-size: 0.85rem; margin-bottom: 0.5rem;">Logged in as:</div>
            <div style="color: white; font-weight: 600; margin-bottom: 1rem;">{{ Auth::user()->full_name }}</div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </button>
                </form>
        </div>
        @endauth
    </nav>
</header>

<!-- Main Content Area -->
<main class="helpdesk-content">
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
        
        // Add loading animation to logout button
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('logout-btn') || e.target.closest('.logout-btn')) {
                const btn = e.target.classList.contains('logout-btn') ? e.target : e.target.closest('.logout-btn');
                const originalContent = btn.innerHTML;
                
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.8';
            }
        });
        
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
    
    // Helpdesk-specific functions
    function updateTicketStatus(ticketId, newStatus) {
        console.log(`Updating ticket ${ticketId} to ${newStatus}`);
        
        const statusElement = document.getElementById(`status-${ticketId}`);
        if (statusElement) {
            const oldStatus = statusElement.textContent;
            statusElement.textContent = newStatus;
            
            // Update status badge class
            statusElement.className = `status-badge status-${newStatus.toLowerCase().replace(' ', '-')}`;
            
            // Animate the change
            statusElement.style.transform = 'scale(1.1)';
            setTimeout(() => {
                statusElement.style.transform = 'scale(1)';
                statusElement.style.transition = 'transform 0.3s ease';
            }, 300);
            
            showAlert(`Ticket status updated from ${oldStatus} to ${newStatus}`, 'success');
        }
    }
    
    function assignToSelf(ticketId) {
        console.log(`Assigning ticket ${ticketId} to current user`);
        
        const assigneeElement = document.getElementById(`assignee-${ticketId}`);
        if (assigneeElement) {
            assigneeElement.textContent = 'You';
            assigneeElement.style.fontWeight = '600';
            assigneeElement.style.color = 'var(--primary-green)';
            
            showAlert('Ticket assigned to you', 'success');
        }
    }
    
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;
        
        const contentArea = document.querySelector('.helpdesk-content');
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
    
    // Ticket filtering
    function filterTickets(status) {
        const tickets = document.querySelectorAll('.ticket-card');
        tickets.forEach(ticket => {
            if (status === 'all') {
                ticket.style.display = 'block';
            } else {
                const ticketStatus = ticket.querySelector('.status-badge').textContent.toLowerCase();
                if (ticketStatus === status.toLowerCase().replace(' ', '-')) {
                    ticket.style.display = 'block';
                } else {
                    ticket.style.display = 'none';
                }
            }
        });
        
        // Update active filter button
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(btn => {
            if (btn.dataset.filter === status) {
                btn.classList.add('active');
                btn.style.background = 'linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%)';
                btn.style.color = 'white';
            } else {
                btn.classList.remove('active');
                btn.style.background = '';
                btn.style.color = '';
            }
        });
        
        showAlert(`Showing ${status === 'all' ? 'all' : status} tickets`, 'info');
    }
    
    // Search tickets
    function searchTickets(searchTerm) {
        const tickets = document.querySelectorAll('.ticket-card');
        let foundCount = 0;
        
        tickets.forEach(ticket => {
            const ticketContent = ticket.textContent.toLowerCase();
            if (ticketContent.includes(searchTerm.toLowerCase())) {
                ticket.style.display = 'block';
                foundCount++;
                
                // Highlight search term (simplified)
                ticket.style.border = '2px solid var(--accent-teal)';
            } else {
                ticket.style.display = 'none';
                ticket.style.border = '';
            }
        });
        
        showAlert(`Found ${foundCount} tickets matching "${searchTerm}"`, 'info');
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
    
    // Add message to ticket
    function addMessage(ticketId) {
        const messageInput = document.getElementById(`message-${ticketId}`);
        const messageContent = messageInput?.value.trim();
        
        if (!messageContent) {
            showAlert('Please enter a message', 'error');
            return;
        }
        
        const messagesContainer = document.getElementById(`messages-${ticketId}`);
        if (messagesContainer) {
            const newMessage = document.createElement('div');
            newMessage.className = 'message-container';
            newMessage.innerHTML = `
                <div class="message-header">
                    <div class="message-author">You</div>
                    <div class="message-time">Just now</div>
                </div>
                <div class="message-content">${messageContent}</div>
            `;
            
            messagesContainer.appendChild(newMessage);
            messageInput.value = '';
            
            // Scroll to new message
            newMessage.scrollIntoView({ behavior: 'smooth' });
            
            showAlert('Message added successfully', 'success');
        }
    }
</script>

</body>
</html>