<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Dinas;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function approveUser($id)
{
    try {
        DB::transaction(function () use ($id) {
            $user = User::findOrFail($id);
            $dinas = $user->dinas()->lockForUpdate()->first();

            if ($dinas->status === 'terdaftar') {
                throw new \Exception('User tidak bisa diaktifkan, dinas sudah Terdaftar.');
            }

            $user->update(['is_active' => true]);
            $dinas->update(['status' => 'terdaftar']);
        });

        return response()->json(['message' => 'Berhasil Aktivasi User']);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Gagal aktivasi user',
            'error' => $e->getMessage(),
        ], 400);
    }
}

    public function rejectUser($id)
    {

        $user = User::findOrFail($id);
        if($user->is_active){
            return response()->json(['message' => 'User sudah diaktifkan, tidak bisa ditolak'], 400);
        }
        $user->delete();
        return response()->json(['message' => 'Pendaftaran user ditolak']);
    }
    public function deleteUser($id)
    {
        try{

            DB::transaction(function () use ($id) {
                
                $user = User::findOrFail($id);
                if ($user->dinas) {
                $user->dinas->update(['status' => 'belum_terdaftar']);
            }
                $user->delete();
            });
            return response()->json(['message' => 'User deleted successfully']);
        }catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'User not found'], 404);
        } catch (Throwable $e) {
        return response()->json(['message' => 'Failed to delete user', 'error' => $e->getMessage()], 500);
    }
    

}
    public function listUsers()
    {
        $users = User::with('dinas')->get();
        return response()->json($users);
    }
    public function createPusdatin(Request $request){
        try{

            $validated=$request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);
            
            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'pusdatin',
                'is_active' => true,
            ]);
            
            return response()->json([
                'message' => 'Akun Pusdatin berhasil dibuat',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat akun Pusdatin', 'error' => $e->getMessage()], 400);
        }
    }
    public function showUser(Request $request, $role, $status){
        $perPage = $request->input('per_page', 15);
        
        if($role !='pusdatin'){

            $data = User::with('dinas.region.parent')
            ->whereNotIn('role', ['admin', 'pusdatin'])
            ->where('role', $role == "kabupaten" ? "kabupaten/kota" : $role)  
            ->where('is_active', $status)
            ->when($request->search, function($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhereHas('dinas', function($dq) use ($search) {
                          $dq->where('nama_dinas', 'like', "%{$search}%")
                             ->orWhere('kode_dinas', 'like', "%{$search}%");
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
            // Transform data untuk menambahkan nama provinsi dan kabupaten
            $data->getCollection()->transform(function ($user) {
                if ($user->dinas && $user->dinas->region) {
                    $region = $user->dinas->region;
                    
                    // Jika region adalah provinsi (type = 'provinsi')
                    if ($region->type === 'provinsi') {
                        $user->province_name = $region->nama_wilayah ?? $region->nama_region;
                        $user->regency_name = null;
                    } 
                    // Jika region adalah kabupaten/kota
                    else {
                        $user->regency_name = $region->nama_wilayah ?? $region->nama_region;
                        // Ambil nama provinsi dari parent
                        $user->province_name = $region->parent ? ($region->parent->nama_wilayah ?? $region->parent->nama_region) : null;
                    }
                }
                
                return $user;
            });
            
            return response()->json($data);
        }elseif($role=='pusdatin'){
            // Convert status string ke boolean untuk is_active
            $isActive = $status === 'approved' || $status === true || $status === '1';
            
            $data = User::where('role', 'pusdatin')
            ->where('is_active', $isActive)
            ->when($request->search, function($query, $search) {
                return $query->where('email', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
            return response()->json($data);
        }


    }
 public function trackingHistoryPusdatin(Request $request, $year = null, $pusdatin_id = null)
{
    // Support both path params and query params
    $filterYear = $year ?? $request->query('year');
    $filterPusdatinId = $pusdatin_id ?? $request->query('pusdatin_id');
    
    $query = DB::table('pusdatin_logs')
        ->leftJoin('users', 'pusdatin_logs.actor_id', '=', 'users.id')
        ->select(
            'pusdatin_logs.id',
            'pusdatin_logs.year',
            'pusdatin_logs.submission_id',
            'pusdatin_logs.stage',
            'pusdatin_logs.activity_type',
            'pusdatin_logs.actor_id',
            'pusdatin_logs.document_type',
            'pusdatin_logs.status',
            'pusdatin_logs.catatan',
            'pusdatin_logs.created_at',
            'users.email as actor_email'
        )
        ->orderByDesc('pusdatin_logs.created_at');

    // Filter tahun kalau ada
    if ($filterYear) {
        $query->where('pusdatin_logs.year', $filterYear);
    }

    // Filter pusdatin_id kalau ada
    if ($filterPusdatinId) {
        $query->where('pusdatin_logs.actor_id', $filterPusdatinId);
    }

    $logs = $query->get();
    
    // Transform ke format yang diharapkan frontend
    $transformed = $logs->map(function ($log) {
        return [
            'id' => $log->id,
            'user' => $log->actor_email ?? 'Pusdatin #' . $log->actor_id,
            'role' => 'pusdatin',
            'action' => $this->formatAction($log->activity_type, $log->document_type, $log->stage),
            'target' => $log->document_type ?? $log->stage,
            'time' => $log->created_at,
            'status' => $log->status ?? 'info',
            'catatan' => $log->catatan,
            'submission_id' => $log->submission_id,
            'year' => $log->year,
        ];
    });

    return response()->json($transformed);
}

private function formatAction($activityType, $documentType, $stage)
{
    $actions = [
        'upload' => 'Upload',
        'finalize' => 'Finalisasi',
        'unfinalize' => 'Batal Finalisasi',
        'approve' => 'Menyetujui',
        'reject' => 'Menolak',
        'review' => 'Review',
    ];
    
    $action = $actions[$activityType] ?? ucfirst($activityType ?? 'Aksi');
    
    if ($documentType) {
        $docNames = [
            'iklh' => 'IKLH',
            'penilaian_slhd' => 'Penilaian SLHD',
            'penilaian_penghargaan' => 'Penilaian Penghargaan',
        ];
        $action .= ' ' . ($docNames[$documentType] ?? ucfirst($documentType));
    } elseif ($stage) {
        $action .= ' ' . ucfirst($stage);
    }
    
    return $action;
}
}