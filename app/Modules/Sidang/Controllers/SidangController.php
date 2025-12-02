<?php

// NAMESPACE BARU: Mengikuti struktur HMVC yang sudah disepakati (App\Modules\Sidang)
namespace App\Modules\Sidang\Controllers; 

use App\Controllers\BaseController;
use App\Modules\Sidang\Models\SidangModel;
use App\Modules\Sidang\Models\SidangAdminModel; 
use App\Libraries\Settings;
use Google\Client;
use Google\Service\Sheets;
use PhpOffice\PhpWord\TemplateProcessor;
use OTPHP\TOTP;
use App\Models\UserModel; 
use CodeIgniter\HTTP\ResponseInterface;

class SidangController extends BaseController
{
    protected $sidangModel;
    protected $sidangAdminModel;
    protected $setting;

    public function __construct()
    {
        $this->sidangModel = new SidangModel();
        $this->setting = new Settings(); 
        $this->sidangAdminModel = new SidangAdminModel(); 
    }

    /**
     * Helper untuk membersihkan nama terdakwa dari Als, Bin, Alias, Dkk, dsb.
     */
    private function cleanNamaTerdakwa(string $rawName): string
    {
        $keywords = [' als ', ' alias ', ' bin ', ' dkk '];
        $cleanName = $rawName;
        $lowerName = strtolower($rawName);
        $foundPos = false;

        foreach ($keywords as $keyword) {
            $pos = strpos($lowerName, $keyword); 
            if ($pos !== false) {
                 if ($foundPos === false || $pos < $foundPos) {
                     $foundPos = $pos;
                 }
            }
        }
        
        if ($foundPos !== false) {
            $cleanName = substr($rawName, 0, $foundPos);
        }
        
        return trim($cleanName);
    }
    
    /**
     * HELPER: KONEKSI KE GOOGLE SHEET
     * Kode ini berasal dari file upload Anda yang terpotong.
     */
    private function fetchSheet($range)
    {
        $client = new Client();
        $client->setAuthConfig(WRITEPATH . '/json-by2025.json');
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $service = new Sheets($client);
        
        // ⚠️ GANTI ID SHEET LO DISINI
        $spreadsheetId = '1Jlsbx5HxKzQDmfNFIBkw1TTmDBKXpIxKAtb_zhaLLfo'; 
        
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        return $response->getValues();
    }


    public function accessForm()
    {
        if (session()->get('isLoggedInSidang')) {
            return redirect()->to(site_url('sidang'));
        }

        $data = [
            'title' => 'Akses Menu Cetak Sidang',
            'nama_instansi' => $this->setting->info['nama_instansi'],
        ];
        return view('\App\Modules\Sidang\Views\access_form', $data); 
    }

    // app/Modules/Sidang/Controllers/SidangController.php

public function verifyOtp()
{
    $nip = $this->request->getPost('nip'); 
    $otp_code = $this->request->getPost('otp_code');
    
    // 1. Cek Input
    if (empty($nip) || empty($otp_code)) {
        // Ganti redirect->back() menjadi JSON error
        return $this->response->setJSON(['status' => false, 'message' => 'NIP dan Kode OTP wajib diisi.']);
    }

    $adminUser = $this->sidangAdminModel->findByNip($nip);

    // 2. Cek User
    if (!$adminUser || empty($adminUser['sidang_2fa_secret']) || $adminUser['is_active'] == 0) {
        // Ganti redirect->back() menjadi JSON error
        return $this->response->setJSON(['status' => false, 'message' => 'NIP tidak terdaftar untuk akses sidang atau belum dikonfigurasi.']);
    }

    $secret_key = $adminUser['sidang_2fa_secret'];

    try {
        $otp = TOTP::create($secret_key); 

        // 3. Verifikasi OTP
        if ($otp->verify($otp_code, null, 1)) {
        
            session()->set([
                'isLoggedInSidang' => true, 
                'sidang_nip' => $adminUser['nip'],
                'userId' => $adminUser['id'] 
            ]);
            // ✅ GANTI DENGAN JSON SUKSES (Mengandung URL Redirect)
            return $this->response->setJSON([
                'status' => true, 
                'redirect' => site_url('sidang'), 
                'message' => 'Akses berhasil.'
            ]); 

        } else {
            // Ganti redirect->back() menjadi JSON error
            return $this->response->setJSON(['status' => false, 'message' => 'Kode OTP tidak valid atau sudah kadaluarsa. Coba lagi.']);
        }
    } catch (\Throwable $e) {
         // Ganti redirect->back() menjadi JSON error
         return $this->response->setJSON(['status' => false, 'message' => 'Kesalahan Sistem Verifikasi.']);
    }
}


    /**
     * HALAMAN UTAMA (INDEX) - MEMERLUKAN FILTER 'sidang_auth'
     */
    public function index()
    {
        // Cek redundan login
        if (!session()->get('isLoggedInSidang')) {
             return redirect()->to(site_url('sidang/access'));
        }
        
        // 1. Ambil Semua Tanggal Unik yang Ada (untuk Dropdown)
        $queryTanggal = $this->sidangModel->select('tanggal_sidang')->distinct()->orderBy('tanggal_sidang', 'DESC')->findAll();
        
        $listTanggal = [];
        $latestDate = null; 

        foreach ($queryTanggal as $row) {
            $rawDate = $row['tanggal_sidang']; 

            if (!empty($rawDate) && $rawDate !== '0000-00-00') {
                try {
                    $formattedDate = date('d-m-Y', strtotime($rawDate)); 
                    if ($latestDate === null) {
                        $latestDate = $rawDate; 
                    }
                    $listTanggal[] = $formattedDate; 
                } catch (\Exception $e) {
                    continue; 
                }
            }
        }
        
        // 2. Data dikirim KOSONG SECARA DEFAULT
        $cleanedData = []; 

        // 3. Kirim Data ke View 
        $data = [
            'title'         => 'Cetak Sidang - ' . $this->setting->info['nama_aplikasi'],
            'nama_instansi' => $this->setting->info['nama_instansi'],
            'alamat'        => $this->setting->info['alamat'],
            'opt_tanggal'   => $listTanggal, 
            // Kirim data kosong
            'all_data'      => $cleanedData, 
            'nip_user'      => session()->get('sidang_nip'),
            // 🛑 KOREKSI: Set ke null agar View JS bisa menampilkan placeholder
            'selected_date' => null 
        ];

        return view('\App\Modules\Sidang\Views\sidang_view', $data);
    }
    
    /**
     * API: Mengambil data sidang full.
     * Dapat difilter berdasarkan tanggal_sidang (DD-MM-YYYY)
     */
    public function apiData(): ResponseInterface
    {
        // Tanggal diterima dari JS dalam format DD-MM-YYYY
        $tanggalFilter = $this->request->getGet('tanggal');
        
        $query = $this->sidangModel->orderBy('tanggal_sidang', 'ASC');

        // Jika tidak ada filter tanggal, return data kosong (seperti yang diminta JS)
        if (empty($tanggalFilter)) {
            return $this->response->setJSON(['status' => 200, 'total' => 0, 'data' => []]);
        }

// 🛑 KOREKSI: Konversi tanggal dari DD-MM-YYYY ke Database YYYY-MM-DD
        try {
            // 1. Simpan hasil parsing ke variabel $dateObj
            $dateObj = \DateTime::createFromFormat('d-m-Y', $tanggalFilter);
            
            // 2. Cek apakah parsing berhasil DAN input string-nya sama dengan output (validasi strict)
            if ($dateObj !== false && $dateObj->format('d-m-Y') === $tanggalFilter) { 
                // Jika valid, ubah format ke YYYY-MM-DD
                $dbFormatDate = $dateObj->format('Y-m-d'); 
            } else {
                // Jika parsing gagal atau format tidak sesuai (misalnya 32-12-2025)
                return $this->response->setJSON(['status' => 400, 'message' => 'Format tanggal filter tidak valid.']);
            }
            
            // 3. Lanjutkan query dengan tanggal yang sudah diformat
            $query->where('tanggal_sidang', $dbFormatDate);
            
        } catch (\Exception $e) {
            // Jika terjadi error sistem lainnya (misal: memori habis, dll.)
            return $this->response->setJSON(['status' => 400, 'message' => 'Kesalahan Sistem dalam konversi tanggal.']);
        }


        // Ambil semua data sesuai filter
        $allData = $query->findAll();

        $cleanedData = [];

        foreach ($allData as $row) {
            $row['nama_bersih'] = $this->cleanNamaTerdakwa($row['nama_terdakwa']);
            $row['data_full'] = json_decode($row['data_full'], true);
            
            // Tambahkan tanggal_sidang_format untuk memudahkan debug di frontend
            $row['tanggal_sidang_format'] = $tanggalFilter; 

            $cleanedData[] = $row;
        }

        // Kembalikan respons dalam format JSON
        return $this->response->setJSON(['status' => 200, 'total' => count($cleanedData), 'data' => $cleanedData]);
    }

    /**
     * FITUR SINKRONISASI (SYNC) - MEMERLUKAN FILTER 'sidang_auth'
     */
    // app/Modules/Sidang\Controllers\SidangController.php (Blok fungsi sync)
    // app/Modules/Sidang\Controllers\SidangController.php (Blok fungsi sync)

public function sync()
{
    // 1. Ambil Data Mentah dari Google Sheet (fetchSheet tetap sama)
    try {
        $sidangSheet = $this->fetchSheet('SIDANG HARI INI!A1:G100');
        $masterSheet = $this->fetchSheet('DATA_MASTER!A1:P100'); // Diperluas hingga Kolom P (Index 15)
    } catch (\Throwable $e) {
        return redirect()->to('sidang')->with('error', "Gagal koneksi ke Google Sheet. Cek kredensial dan ID Spreadsheet. Pesan: " . $e->getMessage());
    }

    // 2. Buat Kamus Data Master (Kunci: Nomor Perkara / Index 0)
    $masterMap = [];
    if (!empty($masterSheet)) {
        foreach ($masterSheet as $mRow) {
            $key = isset($mRow[0]) ? trim(preg_replace('/\s+/', ' ', $mRow[0])) : '';
            if ($key) $masterMap[$key] = $mRow;
        }
    }

    $countInsert = 0;
    $countUpdate = 0;

    // 3. Proses Simpan ke Database
    if (!empty($sidangSheet)) {
        foreach ($sidangSheet as $rowS) {
            // Bersihkan data
            $noPerkara = isset($rowS[0]) ? trim(preg_replace('/\s+/', ' ', $rowS[0])) : '';
            $nama      = isset($rowS[1]) ? trim(preg_replace('/\s+/', ' ', $rowS[1])) : '';
            
            // Filter Ghost Rows
            if ($noPerkara == '' || strlen($noPerkara) < 5 || stripos($noPerkara, 'Nomor') !== false) continue;

            // Cari Biodata di Master
            $rowM = $masterMap[$noPerkara] ?? [];

            // --- LOGIKA PARSING TEMPAT & TANGGAL LAHIR (Menggunakan $rowM[2]) ---
            $tempatLahir = '-';
            $tglLahir = '-';

            if (isset($rowM[2])) {
                $tglLahirRaw = $rowM[2];
                $tempArray = explode(',', $tglLahirRaw, 2); 
                $tempatLahir = trim($tempArray[0]);
                $tglLahir = trim($tempArray[1] ?? '-');
                
                // Jika tidak ada koma (misal hanya "Jakarta"), maka Tgl Lahir = '-'
                if (count($tempArray) === 1 && $tglLahirRaw !== '-') {
                    $tempatLahir = $tglLahirRaw;
                    $tglLahir = '-';
                }
            }
            // -------------------------------------------------------------------


            // 4. Gabungkan Data (SIDANG HARI INI + DATA MASTER) ke dalam JSON data_full
            $dataFull = [
                // --- BIODATA DARI DATA MASTER ($rowM) ---
                'tempat_lahir'    => $tempatLahir,               // Hasil parsing
                'tgl_lahir'       => $tglLahir,                  // Hasil parsing
                'umur'            => $rowM[3] ?? '-',            // Index 3
                'jenis_kelamin'   => $rowM[4] ?? '-',            // Index 4
                'kewarganegaraan' => $rowM[5] ?? 'Indonesia',    // Index 5
                'alamat'          => $rowM[6] ?? '-',            // Index 6
                'agama'           => $rowM[7] ?? '-',            // Index 7
                'pendidikan'      => $rowM[8] ?? '-',            // Index 8
                'pekerjaan'       => $rowM[9] ?? '-',            // Index 9
                'nama_ortu'       => $rowM[10] ?? '-',           // Index 10
                
                // --- DATA SIDANG DARI SHEET SIDANG HARI INI ($rowS) ---
                'agenda_raw'      => $rowS[3] ?? '-', // Agenda Sidang (Kolom D)
                'hari_raw'        => $rowS[6] ?? '-', // Hari Sidang (Kolom G)
            ];

            // Tanggal Sidang (Kolom G)
            $tglSidang = $rowS[6] ?? date('Y-m-d'); 

            // Cek apakah data sudah ada di DB?
            $existing = $this->sidangModel->where('nomor_perkara', $noPerkara)->first();

            // 5. Data untuk Kolom Utama Database ($saveData)
            $saveData = [
                'tanggal_sidang' => $tglSidang,               // Hari Sidang (Kolom G Sheet Sidang)
                'nomor_perkara'  => $noPerkara,               // Kolom A Sheet Sidang
                'nama_terdakwa'  => $nama,                    // Kolom B Sheet Sidang
                'jpu'            => $rowS[4] ?? '-',          // Kolom E Sheet Sidang
                
                // --- Field Tambahan Sesuai Permintaan (Diambil dari SHEET SIDANG HARI INI) ---
                'jenis_perkara'  => $rowS[2] ?? '-',          // Kolom C Sheet Sidang
                'agenda_sidang'  => $rowS[3] ?? '-',          // Kolom D Sheet Sidang
                'status_sidang'  => $rowS[5] ?? '-',          // Kolom F Sheet Sidang
                // -----------------------------------------------------------------------------

                'data_full'      => json_encode($dataFull), // Simpan biodata sbg JSON
            ];

            if ($existing) {
                $this->sidangModel->update($existing['id'], $saveData);
                $countUpdate++;
            } else {
                $this->sidangModel->insert($saveData);
                $countInsert++;
            }
        }
    }
/** */
    return redirect()->to('sidang')->with('success', "Sinkronisasi Selesai! Data Baru: $countInsert, Update: $countUpdate");
}
    /**
     * FITUR PROSES CETAK - MEMERLUKAN FILTER 'sidang_auth'
     */
    public function proses()
    {
        // 1. Ambil Input User
        $selectedNoPerkara = $this->request->getPost('pilih_data');
        $docTypes = $this->request->getPost('jenis_dokumen');
        $mode = $this->request->getPost('mode_cetak');
        $tanggalTerpilih = $this->request->getPost('tanggal_terpilih');

        // 2. Setup Folder Backup
        $hariIni = date('Y-m-d');
        $pathArsip = WRITEPATH . 'arsip_sidang';
        $folderBackup = $pathArsip . DIRECTORY_SEPARATOR . $hariIni;

        if (!is_dir($pathArsip)) mkdir($pathArsip, 0777, true);
        if (!is_dir($folderBackup)) mkdir($folderBackup, 0777, true);

        $generatedFiles = [];
        $targetData = [];

        // 3. Ambil Data Target dari Database
        if ($mode == 'full_p38') {
            // Mode Tombol Hijau: Ambil semua data tanggal tsb
            $dbFormatDate = \DateTime::createFromFormat('d-m-Y', $tanggalTerpilih)->format('Y-m-d');
            $targetData = $this->sidangModel->where('tanggal_sidang', $dbFormatDate)->findAll();
            $docTypes = ['p38']; 
        } else {
            // Mode Tombol Kuning: Ambil sesuai checklist
            if(empty($selectedNoPerkara)) return redirect()->back()->with('error', 'Pilih data dulu!');
            if(empty($docTypes)) return redirect()->back()->with('error', 'Pilih jenis dokumen!');
            
            $targetData = $this->sidangModel->whereIn('nomor_perkara', $selectedNoPerkara)->findAll();
        }

        if (empty($targetData)) {
            return redirect()->back()->with('error', 'Data tidak ditemukan di database. Coba Sync dulu!');
        }

        // 4. Generate Word
        foreach ($targetData as $row) {
            // Decode JSON biodata
            $details = json_decode($row['data_full'], true);

            $dataRow = [
                'nomor_perkara'   => $row['nomor_perkara'],
                'nama_terdakwa'   => $this->cleanNamaTerdakwa($row['nama_terdakwa']), 
                'jpu'             => $row['jpu'],
                'hari_sidang'     => $row['tanggal_sidang'], 
                'agenda'          => $details['agenda_raw'] ?? '-',
                'tanggal_surat'   => date('d F Y'), 

                // Data Biodata
                'tempat_lahir'    => $details['tempat_lahir'] ?? '-',
                'tgl_lahir'       => $details['tgl_lahir'] ?? '-',
                'umur'            => $details['umur'] ?? '-',
                'jenis_kelamin'   => $details['jenis_kelamin'] ?? '-',
                'kewarganegaraan' => $details['kewarganegaraan'] ?? '-',
                'alamat'          => $details['alamat'] ?? '-',
                'agama'           => $details['agama'] ?? '-',
                'pekerjaan'       => $details['pekerjaan'] ?? '-',
                'pendidikan'      => $details['pendidikan'] ?? '-',
                'nama_ortu'       => $details['nama_ortu'] ?? '-',
                'nip_jpu'         => session()->get('sidang_nip') ?? '...................',
            ];

            // Bersihkan nama file
            $cleanName = preg_replace('/[^A-Za-z0-9 \-]/', '', $row['nama_terdakwa']);
            $cleanName = substr($cleanName, 0, 50);

            // Generate P-37
            if (in_array('p37', $docTypes)) {
                $this->generateDoc('template_p37.docx', $dataRow, "P37_{$cleanName}.docx", $folderBackup, $generatedFiles);
            }
            // Generate P-38
            if (in_array('p38', $docTypes)) {
                $this->generateDoc('template_p38.docx', $dataRow, "P38_{$cleanName}.docx", $folderBackup, $generatedFiles);
            }
        }

        // 5. Packing & Download
        $totalFiles = count($generatedFiles);
        if ($totalFiles === 0) return redirect()->back()->with('error', 'Gagal generate file.');

        if ($totalFiles === 1) {
            $singleFile = reset($generatedFiles);
            return $this->response->download($singleFile, null)->setFileName(basename($singleFile));
        } 
        else {
            $zip = new \ZipArchive();
            $zipName = $folderBackup . DIRECTORY_SEPARATOR . 'Berkas_Sidang_' . time() . '.zip';

            if ($zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                foreach ($generatedFiles as $fname => $fpath) {
                    if (file_exists($fpath)) $zip->addFile($fpath, $fname);
                }
                $zip->close();
            }
            
            if (file_exists($zipName)) {
                return $this->response->download($zipName, null);
            } else {
                return redirect()->back()->with('error', 'Gagal membuat file ZIP.');
            }
        }
    }

    // Helper Generate Doc
    private function generateDoc($tpl, $data, $outName, $path, &$filesArr) {
        $tplPath = WRITEPATH . 'templates/' . $tpl;
        if (file_exists($tplPath)) {
            $proc = new TemplateProcessor($tplPath);
            $proc->setValues($data);
            
            // Auto numbering nama file biar gak bentrok
            $finalName = $outName;
            $c = 1;
            while(file_exists($path . DIRECTORY_SEPARATOR . $finalName)) {
                $finalName = pathinfo($outName, PATHINFO_FILENAME) . "_($c)." . pathinfo($outName, PATHINFO_EXTENSION);
                $c++;
            }
            
            $saveP = $path . DIRECTORY_SEPARATOR . $finalName;
            $proc->saveAs($saveP);
            $filesArr[$finalName] = $saveP;
        }
    }
}