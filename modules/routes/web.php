<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ====================
// PUBLIC ROUTES
// ====================

// Landing Page
Volt::route('/', 'landingpage')->name('landing');

// Authentication Routes
Volt::route('/login', 'auth.login')->name('login');
Volt::route('/register', 'auth.register')->name('register');
Volt::route('/forgot-password', 'auth.forgot-password')->name('password.request');
Volt::route('/reset-password/{token}', 'auth.reset-password')->name('password.reset');

// Public Product Pages
Volt::route('/products', 'products.index')->name('products.index');
Volt::route('/products/{product}', 'products.show')->name('products.show');

// ====================
// CUSTOMER ROUTES
// ====================

// Customer Dashboard
Volt::route('/dashboard', 'customer.dashboard')->name('customer.dashboard');

// Customer Profile
Volt::route('/profile', 'customer.account.profile')->name('customer.account.profile');
Volt::route('/profile/edit', 'customer.profile-edit')->name('customer.profile.edit');

// Customer Orders
Volt::route('/orders', 'customer.orders.index')->name('customer.orders.index');
Volt::route('/orders/{order}', 'customer.orders.show')->name('customer.orders.show');

// Customer Cart & Checkout
Volt::route('/cart', 'customer.cart.index')->name('customer.cart.index');
Volt::route('/checkout', 'customer.checkout.index')->name('customer.checkout.index');

// Customer Support
Volt::route('/support', 'customer.support.index')->name('customer.support.index');
Volt::route('/support/tickets', 'customer.support.tickets')->name('customer.support.tickets');
Volt::route('/support/tickets/create', 'customer.support.ticket-create')->name('customer.support.ticket-create');

// Note: Removed the duplicate products route
Volt::route('customer/products', 'customer.products.index')->name('customer.products.index');

// ====================
// ADMIN ROUTES
// ====================

// Admin Dashboard
Volt::route('/admin', 'admin.dashboard')->name('admin.dashboard');

// Admin Products
Volt::route('/admin/products', 'admin.products.index')->name('admin.products.index');
Volt::route('/admin/products/create', 'admin.products.create')->name('admin.products.create');
Volt::route('/admin/products/{product}/edit', 'admin.products.edit')->name('admin.products.edit');

// Admin Orders
Volt::route('/admin/orders', 'admin.orders.index')->name('admin.orders.index');
Volt::route('/admin/orders/{order}', 'admin.orders.show')->name('admin.orders.show');

// Admin Customers
Volt::route('/admin/customers', 'admin.customers.index')->name('admin.customers.index');
Volt::route('/admin/customers/{customer}', 'admin.customers.show')->name('admin.customers.show');

// Admin Inventory
Volt::route('/admin/inventory', 'admin.inventory.index')->name('admin.inventory.index');

// Admin Reports
Volt::route('/admin/reports', 'admin.reports.index')->name('admin.reports.index');

// Admin Settings
Volt::route('/admin/settings', 'admin.settings.index')->name('admin.settings.index');

// ====================
// FALLBACK ROUTE
// ====================

Route::fallback(function () {
    return redirect()->route('landing');
});