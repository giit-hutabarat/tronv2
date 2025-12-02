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
    //$routes->add('/otp-sidang', 'Setting::otpSetup');    
});
// ====================================================================
// 🟢 KELOMPOK BARU: WEB ROUTE (HALAMAN ADMIN) - MODUL SIDANG OTP
// ====================================================================
// Gunakan group dengan prefix 'setting' tapi diarahkan ke Controller Sidang
$routes->group('setting/otp-sidang', ['filter' => 'auth_session'], function($routes) use ($adminSetupController){
    
    // 1. Rute Index URL: /setting/otp-sidang (URL UTAMA MENU)
    // Diarahkan ke AdminSetupController::setupIndex()
    $routes->get('/', "{$adminSetupController}::setupIndex"); 
    
    // 2. Rute GENERATE QR URL: /setting/otp-sidang/generate/(:num)
    $routes->get('generate/(:num)', "{$adminSetupController}::generateQr/$1");
    
});
// ====================================================================
// 2. KELOMPOK API ROUTE - MODUL SIDANG OTP
// ====================================================================
// Ini harus ada karena View 'setting_otp.php' memanggil API
$routes->group('api/sidang/admins', ['filter' => 'auth_session'], function($routes) use ($adminSetupController){
    
    // 1. Load Data (AJAX GET)
    // Diarahkan ke Controller yang menangani API (misal: ApiAdminSetup.php, jika ada)
    // KARENA KAMU TIDAK PUNYA API CONTROLLER, KITA ARAHKAN KE CONTROLLER UTAMA SEMENTARA
    $routes->get('/', "{$adminSetupController}::getAdminsApi"); 

    // 2. Save NIP (AJAX POST)
    $routes->post('save', "{$adminSetupController}::saveNip");

    // 3. Toggle Status (AJAX PUT)
    $routes->put('toggle/(:num)', "{$adminSetupController}::set2fa/$1"); 
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