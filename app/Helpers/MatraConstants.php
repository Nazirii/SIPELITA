<?php

namespace App\Helpers;

class MatraConstants
{
    /**
     * Mapping kode tabel ke matra
     * 80 Tabel Utama SLHD
     * Format: 'Tabel X || Nama Tabel' => 'Kategori Matra'
     */
    const TABEL_TO_MATRA = [
        // Dokumen Non Matra (Tabel 1-3: Lab, D3TLH, KLHS)
        'Tabel 1 || Jumlah Pemanfaatan Pelayanan Laboratorium (Terakreditasi ISO/IEC 17025:2017)' => 'Dokumen Non Matra',
        'Tabel 2 || Ambang Batas D3TLH berdasar Sumber Daya Air, Lahan, dan Laut' => 'Dokumen Non Matra',
        'Tabel 3 || Jumlah Kajian Lingkungan Hidup Strategis (KLHS) yang Tervalidasi' => 'Dokumen Non Matra',

        // 2.1 Keanekaragaman Hayati (Tabel 4-11)
        'Tabel 4 || Jumlah produk ramah lingkungan yang teregister dan masuk dalam pengadaan barang dan jasa pemerintah' => 'Keanekaragaman Hayati',
        'Tabel 5 || Jumlah Produk Ekolabel Indonesia Tipe I yang Teregistrasi Berdasarkan Kategori Produk' => 'Keanekaragaman Hayati',
        'Tabel 6 || Jumlah Produk Ekolabel Indonesia Tipe II yang Teregistrasi Berdasarkan Kategori Produk' => 'Keanekaragaman Hayati',
        'Tabel 7 || Jumlah dokumen penerapan label ramah lingkungan untuk pengadaan barang dan jasa' => 'Keanekaragaman Hayati',
        'Tabel 8 || Data Taman Kehati' => 'Keanekaragaman Hayati',
        'Tabel 9 || Keadaan Flora dan Fauna' => 'Keanekaragaman Hayati',
        'Tabel 10 || Penangkaran Satwa dan Tumbuhan Liar' => 'Keanekaragaman Hayati',
        'Tabel 11 || Perdagangan Satwa dan Tumbuhan' => 'Keanekaragaman Hayati',

        // 2.2 Kualitas Air (Tabel 12-20)
        'Tabel 12 || Indeks Kualitas Air (IKA)' => 'Kualitas Air',
        'Tabel 13 || Kualitas Air Sumur' => 'Kualitas Air',
        'Tabel 14 || Curah Hujan Rata-rata Bulanan' => 'Kualitas Air',
        'Tabel 15 || Jumlah Rumah Tangga dan Sumber Daya Air Minum' => 'Kualitas Air',
        'Tabel 16 || Kualitas Air Hujan' => 'Kualitas Air',
        'Tabel 17 || Kondisi Sungai' => 'Kualitas Air',
        'Tabel 18 || Kondisi Danau/Waduk/Situ/Embung' => 'Kualitas Air',
        'Tabel 19 || Kualitas Air Sungai' => 'Kualitas Air',
        'Tabel 20 || Kualitas Air Danau/Waduk/Situ/Embung' => 'Kualitas Air',

        // 2.3 Laut, Pesisir, dan Pantai (Tabel 21-28)
        'Tabel 21 || Indeks Kualitas Ekosistem Gambut (IKEG)' => 'Laut, Pesisir, dan Pantai',
        'Tabel 22 || Luas dan Kerusakan Lahan Gambut' => 'Laut, Pesisir, dan Pantai',
        'Tabel 23 || Luas dan Kerapatan Tutupan Mangrove' => 'Laut, Pesisir, dan Pantai',
        'Tabel 24 || Luas dan Kerusakan Padang Lamun' => 'Laut, Pesisir, dan Pantai',
        'Tabel 25 || Luas Tutupan dan Kondisi Terumbu Karang' => 'Laut, Pesisir, dan Pantai',
        'Tabel 26 || Indeks Kualitas Air Laut (IKAL)' => 'Laut, Pesisir, dan Pantai',
        'Tabel 27 || Penanganan Sampah Laut (PSL): Kelimpahan Mikroplastik' => 'Laut, Pesisir, dan Pantai',
        'Tabel 28 || Penanganan Sampah Laut (PSL): Berat Sampah' => 'Laut, Pesisir, dan Pantai',

        // 2.4 Kualitas Udara (Tabel 29-34)
        'Tabel 29 || Indeks Kualitas Udara (IKU)' => 'Kualitas Udara',
        'Tabel 30 || Suhu Udara Rata-rata Bulanan' => 'Kualitas Udara',
        'Tabel 31 || Kualitas Udara Ambien' => 'Kualitas Udara',
        'Tabel 32 || Penggunaan Bahan Bakar Industri dan Rumah Tangga' => 'Kualitas Udara',
        'Tabel 33 || Jumlah Kendaraan Bermotor dan Jenis Bahan Bakar yang digunakan' => 'Kualitas Udara',
        'Tabel 34 || Perubahan Penambahan Ruas Jalan' => 'Kualitas Udara',

        // 2.5 Lahan dan Hutan (Tabel 35-48)
        'Tabel 35 || Tren IKTL (Indeks Kinerja Tutupan Lahan)' => 'Lahan dan Hutan',
        'Tabel 36 || Luas Kawasan Lindung Berdasarkan RTRW dan Tutupan Lahannya' => 'Lahan dan Hutan',
        'Tabel 37 || Luas Wilayah Menurut Penggunaan Lahan Utama' => 'Lahan dan Hutan',
        'Tabel 38 || Luas Hutan Berdasarkan Fungsi dan Status' => 'Lahan dan Hutan',
        'Tabel 39 || Luas Lahan Kritis di Dalam dan Luar Kawasan Hutan' => 'Lahan dan Hutan',
        'Tabel 40 || Evaluasi Kerusakan Tanah di lahan Kering Akibat Erosi Air' => 'Lahan dan Hutan',
        'Tabel 41 || Evaluasi Kerusakan Tanah di lahan Kering' => 'Lahan dan Hutan',
        'Tabel 42 || Evaluasi Kerusakan Tanah di lahan Basah' => 'Lahan dan Hutan',
        'Tabel 43 || Luas Perubahan Penggunaan Lahan Pertanian' => 'Lahan dan Hutan',
        'Tabel 44 || Jenis Pemanfaatan Lahan' => 'Lahan dan Hutan',
        'Tabel 45 || Luas Areal dan Produksi Pertambangan Menurut Jenis Bahan Galian' => 'Lahan dan Hutan',
        'Tabel 46 || Realisasi Kegiatan Penghijauan dan Reboisasi' => 'Lahan dan Hutan',
        'Tabel 47 || Jumlah dan Produksi Pemanfaatan Hasil Hutan Kayu' => 'Lahan dan Hutan',
        'Tabel 48 || Tren IKL (Indeks Kualitas Lahan)' => 'Lahan dan Hutan',

        // 2.6 Pengelolaan Sampah dan Limbah (Tabel 49-53)
        'Tabel 49 || Indeks Kinerja Pengelolaan Sampah (IKPS)' => 'Pengelolaan Sampah dan Limbah',
        'Tabel 50 || Jumlah Limbah Padat dan Cair berdasarkan Sumber Pencemaran' => 'Pengelolaan Sampah dan Limbah',
        'Tabel 51 || Jumlah Rumah Tangga dan Fasilitas Tempat Buang Air Besar' => 'Pengelolaan Sampah dan Limbah',
        'Tabel 52 || Perusahaan yang Mendapat Izin Mengelola B3' => 'Pengelolaan Sampah dan Limbah',
        'Tabel 53 || Data Capaian Pengurangan dan Daur Ulang Sampah' => 'Pengelolaan Sampah dan Limbah',

        // 2.7 Perubahan Iklim (Tabel 54-57)
        'Tabel 54 || Jumlah Desa Berdasarkan Kerentanan Perubahan Iklim' => 'Perubahan Iklim',
        'Tabel 55 || Tingkat emisi masing-masing sektor' => 'Perubahan Iklim',
        'Tabel 56 || Pengurangan emisi aksi mitigasi perubahan iklim' => 'Perubahan Iklim',
        'Tabel 57 || Data aksi adaptasi perubahan iklim' => 'Perubahan Iklim',

        // 2.8 Risiko Bencana (Tabel 58-62)
        'Tabel 58 || Indeks Risiko Bencana (IRB)' => 'Risiko Bencana',
        'Tabel 59 || Jenis Penyakit Utama yang Diderita Penduduk' => 'Risiko Bencana',
        'Tabel 60 || Jumlah Rumah Tangga Miskin' => 'Risiko Bencana',
        'Tabel 61 || Kebencanaan' => 'Risiko Bencana',
        'Tabel 62 || Luas Wilayah, Jumlah Penduduk, Pertumbuhan Penduduk dan Kepadatan Penduduk' => 'Risiko Bencana',

        // Bab III - Dokumen Non Matra: Penetapan Isu Prioritas (Tabel 63-80)
        'Tabel 63 || Peraturan Daerah Rencana Perlindungan dan Pengelolaan Lingkungan Hidup (RPPLH)' => 'Dokumen Non Matra',
        'Tabel 64 || Jumlah Ijin Usaha Pemanfaatan Jasa Lingkungan dan Wisata Alam' => 'Dokumen Non Matra',
        'Tabel 65 || Dokumen Izin Lingkungan' => 'Dokumen Non Matra',
        'Tabel 66 || Pengawasan Izin Lingkungan (AMDAL, UKL/UPL, Surat Pernyataan)' => 'Dokumen Non Matra',
        'Tabel 67 || Kegiatan Fisik Lainnya oleh Instansi' => 'Dokumen Non Matra',
        'Tabel 68 || Jumlah Lembaga Swadaya Masyarakat (LSM) Lingkungan Hidup' => 'Dokumen Non Matra',
        'Tabel 69 || Status Pengaduan Masyarakat Bidang Lingkungan' => 'Dokumen Non Matra',
        'Tabel 70 || Jumlah Personil Lembaga Pengelola Lingkungan Hidup menurut Tingkat Pendidikan' => 'Dokumen Non Matra',
        'Tabel 71 || Jumlah Staf Fungsional Bidang Lingkungan dan Staf yang telah mengikuti Diklat' => 'Dokumen Non Matra',
        'Tabel 72 || Penerima Penghargaan Lingkungan Hidup' => 'Dokumen Non Matra',
        'Tabel 73 || Kegiatan/Program yang Diinisiasi Masyarakat' => 'Dokumen Non Matra',
        'Tabel 74 || Produk Domestik Bruto atas Dasar Harga Berlaku' => 'Dokumen Non Matra',
        'Tabel 75 || Produk Domestik Bruto atas Dasar Harga Konstan' => 'Dokumen Non Matra',
        'Tabel 76 || Produk Hukum Bidang Pengelolaan Lingkungan Hidup dan Kehutanan' => 'Dokumen Non Matra',
        'Tabel 77 || Anggaran Pengelolaan Lingkungan Hidup di Daerah' => 'Dokumen Non Matra',
        'Tabel 78 || Pendapatan Asli Daerah' => 'Dokumen Non Matra',
        'Tabel 79 || Inovasi Pengelolaan Lingkungan Hidup Daerah' => 'Dokumen Non Matra',
        'Tabel 80 || Jumlah Penduduk Laki-laki dan Perempuan Menurut Tingkatan Pendidikan' => 'Dokumen Non Matra',
    ];

    /**
     * Kategori Matra
     */
    const MATRA_LIST = [
        'Keanekaragaman Hayati',
        'Kualitas Air',
        'Laut, Pesisir, dan Pantai',
        'Kualitas Udara',
        'Lahan dan Hutan',
        'Pengelolaan Sampah dan Limbah',
        'Perubahan Iklim',
        'Risiko Bencana',
        'Dokumen Non Matra',
    ];

    /**
     * Get matra by kode tabel (full format: "Tabel X || Nama")
     */
    public static function getMatraByKode(string $kodeTabel): ?string
    {
        return self::TABEL_TO_MATRA[$kodeTabel] ?? null;
    }

    /**
     * Get matra by nomor tabel saja (e.g. "Tabel 1", "Tabel 2", etc)
     */
    public static function getMatraByNomor(int $nomor): ?string
    {
        $prefix = "Tabel {$nomor} ||";
        foreach (self::TABEL_TO_MATRA as $kode => $matra) {
            if (str_starts_with($kode, $prefix)) {
                return $matra;
            }
        }
        return null;
    }

    /**
     * Get kode tabel by nomor
     */
    public static function getKodeByNomor(int $nomor): ?string
    {
        $prefix = "Tabel {$nomor} ||";
        foreach (array_keys(self::TABEL_TO_MATRA) as $kode) {
            if (str_starts_with($kode, $prefix)) {
                return $kode;
            }
        }
        return null;
    }

    /**
     * Check if kode tabel valid
     */
    public static function isValidKode(string $kodeTabel): bool
    {
        return isset(self::TABEL_TO_MATRA[$kodeTabel]);
    }

    /**
     * Get all valid kode tabel
     */
    public static function getAllKodeTabel(): array
    {
        return array_keys(self::TABEL_TO_MATRA);
    }

    /**
     * Extract nomor tabel from kode (e.g. "Tabel 1 || Nama" => 1)
     */
    public static function extractNomorTabel(string $kodeTabel): ?int
    {
        if (preg_match('/^Tabel (\d+)/', $kodeTabel, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}
