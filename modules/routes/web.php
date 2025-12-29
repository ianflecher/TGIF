<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ====================
// PUBLIC ROUTES
// ====================

// Landing Page
Volt::route('/', 'landingpage')->name('landing');

// Authentication Routes
Volt::route('/login', 'auth.login')->name('login')->middleware('guest');
Volt::route('/register', 'auth.register')->name('register')->middleware('guest');
Volt::route('/forgot-password', 'auth.forgot-password')->name('password.request')->middleware('guest');
Volt::route('/reset-password/{token}', 'auth.reset-password')->name('password.reset')->middleware('guest');

// Public Product Pages
Volt::route('/products', 'products.index')->name('products.index');
Volt::route('/products/{product}', 'products.show')->name('products.show');

// ====================
// CUSTOMER ROUTES
// ====================

// Customer Dashboard
Volt::route('/dashboard', 'customer.dashboard')->name('customer.dashboard')->middleware('auth');

// Customer Profile
Volt::route('/profile', 'customer.profile')->name('customer.profile')->middleware('auth');
Volt::route('/profile/edit', 'customer.profile-edit')->name('customer.profile.edit')->middleware('auth');

// Customer Orders
Volt::route('/orders', 'customer.orders.index')->name('customer.orders.index')->middleware('auth');
Volt::route('/orders/{order}', 'customer.orders.show')->name('customer.orders.show')->middleware('auth');

// Customer Cart & Checkout
Volt::route('/cart', 'customer.cart.index')->name('customer.cart.index')->middleware('auth');
Volt::route('/checkout', 'customer.checkout.index')->name('customer.checkout.index')->middleware('auth');

// Customer Support
Volt::route('/support', 'customer.support.index')->name('customer.support.index');
Volt::route('/support/tickets', 'customer.support.tickets')->name('customer.support.tickets')->middleware('auth');
Volt::route('/support/tickets/create', 'customer.support.ticket-create')->name('customer.support.ticket-create')->middleware('auth');

// ====================
// ADMIN ROUTES
// ====================

// Admin Dashboard
Volt::route('/admin', 'admin.dashboard')->name('admin.dashboard')->middleware('auth');

// Admin Products
Volt::route('/admin/products', 'admin.products.index')->name('admin.products.index')->middleware('auth');
Volt::route('/admin/products/create', 'admin.products.create')->name('admin.products.create')->middleware('auth');
Volt::route('/admin/products/{product}/edit', 'admin.products.edit')->name('admin.products.edit')->middleware('auth');

// Admin Orders
Volt::route('/admin/orders', 'admin.orders.index')->name('admin.orders.index')->middleware('auth');
Volt::route('/admin/orders/{order}', 'admin.orders.show')->name('admin.orders.show')->middleware('auth');

// Admin Customers
Volt::route('/admin/customers', 'admin.customers.index')->name('admin.customers.index')->middleware('auth');
Volt::route('/admin/customers/{customer}', 'admin.customers.show')->name('admin.customers.show')->middleware('auth');

// Admin Inventory
Volt::route('/admin/inventory', 'admin.inventory.index')->name('admin.inventory.index')->middleware('auth');

// Admin Reports
Volt::route('/admin/reports', 'admin.reports.index')->name('admin.reports.index')->middleware('auth');

// Admin Settings
Volt::route('/admin/settings', 'admin.settings.index')->name('admin.settings.index')->middleware('auth');

// ====================
// FALLBACK ROUTE
// ====================

Route::fallback(function () {
    return redirect()->route('landing');
});