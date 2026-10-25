<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Rules not listed here fall back to the English lines.
    |
    */

    'accepted' => ':attribute wajib dicentang.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal :date atau sesudahnya.',
    'array' => ':attribute harus berupa daftar.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal :date atau sebelumnya.',
    'between' => [
        'array' => ':attribute harus berisi :min sampai :max item.',
        'file' => ':attribute harus berukuran :min sampai :max kilobyte.',
        'numeric' => ':attribute harus di antara :min dan :max.',
        'string' => ':attribute harus :min sampai :max karakter.',
    ],
    'boolean' => ':attribute harus benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus :digits digit.',
    'digits_between' => ':attribute harus :min sampai :max digit.',
    'email' => ':attribute harus alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa file.',
    'filled' => ':attribute wajib diisi.',
    'gt' => [
        'numeric' => ':attribute harus lebih besar dari :value.',
    ],
    'gte' => [
        'numeric' => ':attribute harus lebih besar dari atau sama dengan :value.',
    ],
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus bilangan bulat.',
    'lowercase' => ':attribute harus huruf kecil.',
    'lt' => [
        'numeric' => ':attribute harus lebih kecil dari :value.',
    ],
    'lte' => [
        'numeric' => ':attribute harus lebih kecil dari atau sama dengan :value.',
    ],
    'max' => [
        'array' => ':attribute maksimal :max item.',
        'file' => ':attribute maksimal :max kilobyte.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],
    'mimes' => ':attribute harus berupa file: :values.',
    'mimetypes' => ':attribute harus berupa file: :values.',
    'min' => [
        'array' => ':attribute minimal :min item.',
        'file' => ':attribute minimal :min kilobyte.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus mengandung huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan kecil.',
        'numbers' => ':attribute harus mengandung angka.',
        'symbols' => ':attribute harus mengandung simbol.',
        'uncompromised' => ':attribute ini pernah bocor di internet. Pilih kata sandi lain.',
    ],
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi.',
    'required_with' => ':attribute wajib diisi.',
    'required_without' => ':attribute wajib diisi.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'string' => ':attribute harus :size karakter.',
    ],
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terdaftar.',
    'uploaded' => ':attribute gagal diunggah.',
    'url' => ':attribute harus URL yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'phone' => [
            'regex' => 'Nomor HP harus diawali 08 dan berisi 10–14 digit.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'nama',
        'email' => 'email',
        'phone' => 'nomor HP',
        'password' => 'kata sandi',
        'current_password' => 'kata sandi saat ini',
        'address' => 'alamat',
        'city' => 'kota',
        'business_name' => 'nama usaha',
        'service_id' => 'jenis layanan',
        'ac_capacity' => 'kapasitas AC',
        'ac_brand' => 'merek AC',
        'preferred_date' => 'tanggal',
        'time_window' => 'slot waktu',
        'rating' => 'rating',
        'comment' => 'cerita pengalaman',
    ],

];
