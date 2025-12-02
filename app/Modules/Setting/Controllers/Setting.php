<?php

namespace  App\Modules\Setting\Controllers;
/*
IT Shop Purwokerto (Tokopedia, Shopee & Bukalapak)
Dibuat oleh: Hari Wicaksono, S.Kom
11-2021
*/

use App\Controllers\BaseController;
use App\Libraries\Settings;

class Setting extends BaseController
{
	protected $setting;

	public function __construct()
	{
		//memanggil Model
		$this->setting = new Settings();
	}

	public function general()
	{
		return view('App\Modules\Setting\Views/setting_general', [
			'title' => 'Pengaturan Umum'
		]);
	}

	public function app()
	{
		return view('App\Modules\Setting\Views/setting_app', [
			'title' => 'Pengaturan Aplikasi'
		]);
	}

	public function otpSetup()
    {
        // Panggil Model untuk mendapatkan daftar NIP yang sudah di-setup
        // Anda perlu membuat Model ini jika belum ada.
        $sidangAdminModel = new \App\Modules\Sidang\Models\SidangAdminModel(); 
        
        $data = [
            'title' => 'Setup NIP Pegawai (Akses Sidang)',
            'list_admins' => $sidangAdminModel->findAll(), // Asumsi mengambil semua NIP
        ];
        
        // Render view yang sudah Anda pindahkan
        return view('\App\Modules\Setting\Views\setting_otp', $data); 
    }

}



