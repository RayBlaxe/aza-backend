<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Jika Anda menggunakan factory

class OrderItem extends Model
{
    use HasFactory; // Jika Anda menggunakan factory

    protected $fillable = [
        'order_id', // Pastikan ini juga ada jika disetel di controller
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'price',
        'total',
        // Tambahkan semua kolom lain yang Anda setel saat membuat OrderItem
    ];

    // ... relasi atau metode lain jika ada
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}