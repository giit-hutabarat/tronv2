<?php

if(!isset($routes))
{ 
    $routes = \Config\Services::routes(true);
}

// ✅ KOREKSI UTAMA: DEFINISI VARIABEL WAJIB
// Variabel ini harus didefinisikan agar tidak Undefined saat baris 23 dieksekusi.
// Rute ini diarahkan ke Controller Setup yang ada di Modul Sidang.
$adminSetupController = '\\App\\Modules\\Sidang\\Controllers\\AdminSetupController';


// ====================================================================
// KELOMPOK 1: WEB ROUTE (HALAMAN ADMIN)
// ====================================================================
// Gunakan use ($adminSetupController) agar variabel dapat diakses di dalam closure.
$routes->group('setting', ['filter' => 'auth_session', 'namespace' => 'App\\Modules\\Setting\\Controllers'], function($routes) use ($adminSetupController){ 
	$routes->add('general', 'Setting::general');
	$routes->add('app', 'Setting::app');
	
    // RUTE MENU OTP: Ini adalah rute yang dipanggil dari Dashboard
    // URL: /setting/otp-sidang
    $routes->add('otp-sidang', 'Setting::otpSetup');    
});

// ====================================================================
// KELOMPOK 2: ADMIN API ROUTE (CRUD & Update Config)
// ... (API routes lainnya dari Modul Setting) ...
$routes->group('api', ['filter' => 'auth_session', 'namespace' => 'App\\Modules\\Setting\\Controllers\\Api'], function($routes){
    $routes->get('setting/general', 'ApiSetting::general');
	$routes->get('setting/app', 'ApiSetting::app');
	
    // Update dan Upload
    $routes->put('setting/update/(:segment)', 'ApiSetting::update/$1');
	$routes->post('setting/upload', 'ApiSetting::upload');

	$routes->put('setting/change/(:segment)', 'ApiSetting::setChange/$1');

    // Data Helper
	$routes->get('setting/kota', 'ApiSetting::kota');
	$routes->get('setting/layout', 'ApiSetting::layout');
});