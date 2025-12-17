<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Dinas;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;



class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'id_dinas' => 'required|exists:dinas,id',
                'kode_dinas' => 'required|string|exists:dinas,kode_dinas',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ], [
                'id_dinas.required' => 'Pilih dinas terlebih dahulu.',
                'id_dinas.exists' => 'Dinas tidak ditemukan.',
                'kode_dinas.required' => 'Kode dinas wajib diisi.',
                'kode_dinas.exists' => 'Kode dinas tidak cocok atau tidak terdaftar.',
                'email.required' => 'Email wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'email.unique' => 'Email sudah terdaftar.',
                'password.required' => 'Password wajib diisi.',
                'password.min' => 'Password minimal 8 karakter.',
            ]);
            
            $user = DB::transaction(function () use ($validated) {
                $dinas = Dinas::with('region')->lockForUpdate()->findOrFail($validated['id_dinas']);

                if ($dinas->kode_dinas !== $validated['kode_dinas']) {
                    throw new \Exception('Kode dinas tidak valid untuk dinas yang dipilih.');
                }

                if ($dinas->status !== 'belum_terdaftar') {
                    throw new \Exception('Dinas ini sudah terdaftar.');
                }

                // Tentukan role berdasarkan tipe region
                $role = match ($dinas->region->type) {
                    'provinsi' => 'provinsi',
                    'kabupaten/kota' => 'kabupaten/kota',
                    default => 'unknown',
                };

                // Buat user
                $user = User::create([
                    'email' => $validated['email'],
                    'dinas_id' => $dinas->id,
                    'password' => Hash::make($validated['password']),
                    'role' => $role,
                ]);

                return $user;
            });

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil! Mohon tunggu aktivasi dari admin.',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'dinas_id' => $user->dinas_id,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Ambil pesan error pertama saja agar konsisten
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            
            return response()->json([
                'success' => false,
                'message' => $firstError,
                'errors' => $errors, // tetap sertakan detail untuk debugging
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    public function login(Request $request)
    {
        
        // Validasi input
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);
        // return response()->json(['test' => 'clean response']);
        $user=User::where('email', $request->email)->first();
         if (!$user ){
            return response()->json(['message' => 'email tidak ditemukan'], 404);
         }
            elseif( !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'password salah'], 401);
        }
        if (!$user->is_active) {
            return response()->json(['message' => 'Akun Tidak aktif. Silakan hubungi administrator.'], 403);
        }
        $user->tokens()->delete();
        $token=$user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'message' => 'Login berhasil',
            'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'role' => [
                'name' => $user->role, // Wrap dalam object agar match dengan FE
            ],
            'dinas_id' => $user->dinas_id,
            'token' => $token,
    ],
        ]);


        
}
    
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }
}

