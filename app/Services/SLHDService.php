<?php

namespace App\services;

class SLHDService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
     private array $babWeights = [
        'Bab_1' => 0.10,
        'Bab_2' => 0.50, // dibagi lagi ke matra
        'Bab_3' => 0.20,
        'Bab_4' => 0.15,
        'Bab_5' => 0.05,
    ];

    // Bobot matra pada BAB 2
    private array $bab2Weights = [
        'Keanekaragaman_Hayati'         => 0.10,
        'Kualitas_Air'                  => 0.10,
        'Laut_Pesisir_dan_Pantai'       => 0.10,
        'Kualitas_Udara'               => 0.10,
        'Lahan_dan_Hutan'              => 0.10,
        'Pengelolaan_Sampah_dan_Limbah'=> 0.25,
        'Perubahan_Iklim'              => 0.15,
        'Risiko_Bencana'               => 0.10,
    ];

    /**
     * Hitung skor total SLHD satu row
     */
    public function calculate(array $row): float
    {
        // Hitung BAB 2
        $bab2Score = 0;
        foreach ($this->bab2Weights as $col => $weight) {
            $value = $this->toFloat($row[$col] ?? null);
            $bab2Score += $value * $weight;  // Nilai * bobot matra
        }

        // Hitung skor total SLHD berdasarkan bobot BAB
        $total =
            $this->toFloat($row['Bab_1'] ?? null) * $this->babWeights['Bab_1'] +
            $bab2Score * $this->babWeights['Bab_2'] +
            $this->toFloat($row['Bab_3'] ?? null) * $this->babWeights['Bab_3'] +
            $this->toFloat($row['Bab_4'] ?? null) * $this->babWeights['Bab_4'] +
            $this->toFloat($row['Bab_5'] ?? null) * $this->babWeights['Bab_5'];

        return $total;
    }
    
    /**
     * Safely convert value to float, handling nulls, empty strings, and non-numeric values
     */
    private function toFloat($value): float
    {
        if ($value === null || $value === '' || $value === '-') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        // Untuk string non-numeric, return 0
        return 0.0;
    }

    /**
     * Apakah row ini lolos threshold?
     */
    public function passesSLHD(array $row): bool
    {
        return $row['Total_Skor'] >= 60;  // threshold sesuai kebutuhanmu
    }
    
}
