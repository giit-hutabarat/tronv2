<?php

namespace App\Modules\User\Controllers\Api;

use App\Controllers\BaseControllerApi;
use App\Modules\User\Models\UserModel;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use ReflectionException;

class User extends BaseControllerApi
{
    protected $format       = 'json';
    protected $modelName    = UserModel::class;

    public function __construct()
    {
        // 🔒 Otorisasi Level Admin (Hanya Super Admin yang bisa manipulasi user)
        if (session()->get('user_type') != 1) {
            $response = service('response');
            $response->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
                     ->setJSON(['status' => false, 'message' => 'Akses Ditolak. Anda bukan Super Admin.']);
            $response->send();
            exit(); 
        }
    }

    public function index()
    {
        return $this->respond(["status" => true, "message" => lang('App.getSuccess'), "data" => $this->model->findAll()], 200);
    }

    public function create()
    {
        $rules = [
            'email' => [ 'rules'  => 'required', 'errors' => [] ],
            'fullname' => [ 'rules'  => 'required', 'errors' => [] ],
            'username' => [ 'rules'  => 'required', 'errors' => [] ],
            'password' => [ 'rules'  => 'required', 'errors' => [] ],
        ];

        $input = $this->getRequestInput();

        if (!$this->validate($rules)) {
            $response = [
                'status' => false,
                'message' => lang('App.isRequired'),
                'data' => $this->validator->getErrors(),
            ];
            return $this->respond($response, 200);
        } else {
            $data = [
                'email' => $input['email'],
                'fullname' => $input['fullname'],
                'username' => $input['username'],
                'password' => $input['password'],
                'user_type' => 2, // Default user baru adalah Tipe 2
                'is_active' => 1
            ];

            $simpan = $this->model->save($data);
            if ($simpan) {
                $response = [
                    'status' => true,
                    'message' => lang('App.productSuccess'),
                    'data' => [],
                ];
                return $this->respond($response, 200);
            }
        }
    }
    
    public function update($id = NULL)
    {
        $rules = [
            'email' => [ 'rules'  => 'required', 'errors' => [] ],
            'fullname' => [ 'rules'  => 'required', 'errors' => [] ],
        ];

        $input = $this->getRequestInput();

        if (!$this->validate($rules)) {
            $response = [
                'status' => false,
                'message' => lang('App.updFailed'),
                'data' => $this->validator->getErrors(),
            ];
            return $this->respond($response, 200);
        } else {
            $data = [
                'email' => $input['email'],
                'fullname' => $input['fullname']
            ];

            $simpan = $this->model->update($id, $data);
            if ($simpan) {
                $response = [
                    'status' => true,
                    'message' => lang('App.updSuccess'),
                    'data' => [],
                ];
                return $this->respond($response, 200);
            }
        }
    }

    public function delete($id = null)
    {
        $hapus = $this->model->find($id);
        if ($hapus) {
            $this->model->delete($id);
            $response = [
                'status' => true,
                'message' => lang('App.delSuccess'),
                'data' => [],
            ];
            return $this->respond($response, 200);
        } else {
            $response = [
                'status' => false,
                'message' => lang('App.delFailed'),
                'data' => [],
            ];
            return $this->respond($response, 200);
        }
    }

    public function setActive($id = NULL)
    {
        $input = $this->getRequestInput();
        $data = [ 'is_active' => $input['is_active'] ];

        if ($this->model->update($id, $data)) {
            $response = [
                'status' => true,
                'message' => lang('App.updSuccess'),
                'data' => []
            ];
            return $this->respond($response, 200);
        } else {
            $response = [
                'status' => false,
                'message' => lang('App.delFailed'),
                'data' => []
            ];
            return $this->respond($response, 200);
        }
    }

    public function setRole($id = NULL)
    {
        $input = $this->getRequestInput();
        $data = [ 'user_type' => $input['user_type'] ];

        if ($this->model->update($id, $data)) {
            $response = [
                'status' => true,
                'message' => lang('App.updSuccess'),
                'data' => []
            ];
            return $this->respond($response, 200);
        } else {
            $response = [
                'status' => false,
                'message' => lang('App.delFailed'),
                'data' => []
            ];
            return $this->respond($response, 200);
        }
    }

    public function changePassword()
    {
        $rules = [
            // 💥 KOREKSI: Tambahkan ID pengguna di rules jika lo membutuhkannya untuk validasi di sini
            'id' => 'required', 
            'password' => 'required|min_length[8]|max_length[255]',
            'verify' => 'required|matches[password]'
        ];

        $input = $this->getRequestInput();

        if (!$this->validate($rules)) {
            return $this->getResponse(
                [
                    'status' => false,
                    'message' => 'Validasi Gagal', // Ubah pesan agar lebih jelas
                    'data' => $this->validator->getErrors()
                ],
                ResponseInterface::HTTP_OK
            );
        }
        
        // 💥 KOREKSI KRITIS 1: Cari pengguna berdasarkan ID yang dikirim dari Frontend
        $user = $this->model->find($input['id']);
        
        if (!$user) {
             return $this->getResponse(
                [
                    'status' => false,
                    'message' => 'Pengguna tidak ditemukan berdasarkan ID.',
                    'data' => []
                ], ResponseInterface::HTTP_OK
            );
        }

        // 💥 KOREKSI KRITIS 2: Gunakan Primary Key 'id' untuk update
        $user_id = $input['id']; 
		$user_data = [
			'password' => $input['password'],
		];
        
        try {
            if ($this->model->update($user_id, $user_data)) {
                return $this->getResponse(
                    [
                        'status' => true,
                        'message' => lang('App.passChanged'),
                        'data' => []
                    ], ResponseInterface::HTTP_OK
                );
            } else {
                // Ini menangani kasus update gagal tanpa error fatal (misalnya data sama)
                return $this->getResponse(
                    [
                        'status' => false,
                        'message' => 'Update gagal, pastikan password baru.',
                        'data' => []
                    ], ResponseInterface::HTTP_OK
                );
            }
        } catch (Exception $e) {
             // 💥 Tambahkan logging untuk menangkap error DB
             log_message('error', 'Change password failed: ' . $e->getMessage());
             return $this->getResponse(
                [
                    'status' => false,
                    'message' => 'Gagal mengubah password karena error sistem. Cek log.',
                    'data' => []
                ], ResponseInterface::HTTP_OK
            );
        }
    }
}