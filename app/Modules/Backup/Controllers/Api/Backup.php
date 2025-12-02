<?php

namespace App\Modules\Backup\Controllers\Api;
/*
IT Shop Purwokerto (Tokopedia, Shopee & Bukalapak)
Dibuat oleh: Hari Wicaksono, S.Kom
11-2021
*/

use App\Controllers\BaseControllerApi;
use App\Modules\Backup\Models\BackupModel; // Pastikan ini di-use
use CodeIgniter\HTTP\ResponseInterface;
use Y0lk\SQLDumper\SQLDumper;
use Config\Database;

class Backup extends BaseControllerApi
{
    protected $format       = 'json';
    protected $modelName    = BackupModel::class; // FIX: Menggunakan BackupModel yang benar

    public function __construct()
    {
        // 🔒 Otorisasi Level Super Admin
        if (session()->get('user_type') != 1) {
            $response = service('response');
            $response->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
                     ->setJSON(['status' => false, 'message' => 'Akses Ditolak. Hanya Super Admin yang dapat mengelola pencadangan database.']);
            $response->send();
            exit(); 
        }
    }

    public function index()
    {
        // FIX: Sekarang akan memanggil BackupModel->findAll()
        return $this->respond(['status' => true, 'message' => lang('App.getSuccess'), 'data' => $this->model->findAll()], 200);
    }

    public function create()
    {
        // 1. Dapatkan objek konfigurasi mentah (Config\Database)
        $dbConfigObj = config('Database'); 
        $db = $dbConfigObj->default;
        
        // 2. Kredensial dipaksa menggunakan IP untuk menghindari koneksi socket (localhost)
        $db_config = [
            'hostname' => '127.0.0.1', // 💥 FIX HOST FINAL: Dipaksa menggunakan IP
            'username' => 'root',      // 💥 FIX USER
            'password' => '',          // 💥 FIX PASS: String Kosong
            'database' => $db['database'],
            'port'     => $db['port'] ?? 3306,
        ];
        
        // 💥 DEBUGGING: Log kredensial final
        log_message('critical', 'FINAL DB CONFIG USED: Host: ' . $db_config['hostname'] . ' | User: ' . $db_config['username'] . ' | DB: ' . $db_config['database']);


        $tanggal = date('Ymd-His');
        
        // Gunakan WRITEPATH untuk directory yang aman
        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR; 
        
        // PERBAIKAN FILE PERMISSION CHECK
        if (! is_dir($backupDir) && !mkdir($backupDir, 0777, true)) {
            log_message('critical', 'Gagal membuat direktori backup: ' . $backupDir);
            return $this->respond(['status' => false, 'message' => 'Gagal membuat folder backup. Periksa izin folder writable!'], 500);
        }
        if (! is_writable($backupDir)) {
             log_message('critical', 'Izin Tulis Ditolak di: ' . $backupDir);
             return $this->respond(['status' => false, 'message' => 'Gagal membuat backup. Folder writable/backups tidak memiliki izin tulis (777)!'], 500);
        }

        $namaFile = 'backup-' . $tanggal . '.sql';
        $pathFile = 'backups/';

        try {
            // 3. Inisialisasi SQL Dumper
            $dumper = new SQLDumper(
                $db_config['hostname'],
                $db_config['username'],
                $db_config['password'],
                $db_config['database'],
                $db_config['port']
            );

            $dumper->allTables()->withData(true)->withDrop(true);
            $dumper->save($backupDir . $namaFile); 

            // 4. Log ke Tabel Database
            $data = [
                'file_name' => $namaFile,
                'file_path' => $pathFile . $namaFile,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->model->save($data);

            return $this->respond(['status' => true, 'message' => lang('App.saveSuccess'), 'data' => []], 200);

        } catch (\Exception $e) {
            log_message('critical', 'Gagal membuat backup. SQL Dumper Error: ' . $e->getMessage());
            
            // Mengembalikan pesan Error MySQL yang terekspos
            return $this->respond([
                'status' => false,
                'message' => 'Gagal membuat backup. Error: ' . $e->getMessage(),
                'data' => []
            ], 500); 
        }
    }
    public function delete($id = null)
    {
        $hapus = $this->model->find($id);
        if ($hapus) {
            $filepath = WRITEPATH . $hapus['file_path'];
            
			unlink($filepath);
            $this->model->delete($id);
            return $this->respond(['status' => true, 'message' => lang('App.delSuccess'), 'data' => []], 200);
        } else {
            return $this->respond(['status' => false, 'message' => lang('App.delFailed'), 'data' => []], 200);
        }
    }

    public function download()
    {
        $input = $this->getRequestInput();
        $id = $input['id'] ?? $this->request->getPost('id');

        $backup = $this->model->find($id);
        
        if (!$backup) {
            return $this->respond(['status' => false, 'message' => 'File backup tidak ditemukan.'], 404);
        }

        $name = $backup['file_name'];
        $path = $backup['file_path'];
        $filePath = base_url($path); 

        return $this->respond(['status' => true, 'message' => lang('App.getSuccess'), 'data' => ['filename' => $name, 'url' => $filePath]], 200);
    }
}