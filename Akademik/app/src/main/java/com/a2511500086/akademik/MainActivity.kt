package com.a2511500086.akademik

import android.graphics.Color
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.a2511500086.akademik.databinding.ActivityMainBinding
import com.a2511500086.akademik.api.ApiConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import retrofit2.HttpException
import java.io.IOException
import java.net.SocketTimeoutException

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Cek koneksi jika btnTest diklik
        binding.btnTestConnection.setOnClickListener {
            cekKoneksiApi()
        }
    }

    private fun cekKoneksiApi() {
        // 1. Tampilkan indikator loading & status awal
        binding.progressBar.visibility = View.VISIBLE
        binding.btnTestConnection.isEnabled = false
        updateStatus("Status: Menghubungkan ke server...", Color.DKGRAY)

        // 2. Jalankan request jaringan secara asynchronous menggunakan
        // Coroutine
        lifecycleScope.launch {
            try {
                // Eksekusi panggilan Retrofit di thread IO
                val response = withContext(Dispatchers.IO) {
                    ApiConfig.getApiService().getDaftarMahasiswa()
                }

                // 3. Penanganan HTTP Response
                if (response.isSuccessful) {
                    val body = response.body()
                    val jumlahData = body?.data?.size ?: 0
                    updateStatus(
                        "Status: Terhubung (HTTP ${response.code()})\n" +
                                "Pesan: ${body?.message ?: "OK"} ($jumlahData data ditemukan)",
                        Color.parseColor("#2E7D32") // Hijau
                    )
                } else {
                    // Server merespons, namun mengembalikan kode error (404,
                    // 500, dll.)
                    updateStatus(
                        "Status: Gagal merespons (HTTP ${response.code()} - ${response.message()})",
                        Color.parseColor("#C62828") // Merah
                    )
                }

            } catch (e: SocketTimeoutException) {
                // 4. Penanganan Exception: Waktu koneksi habis
                updateStatus(
                    "Status: Timeout - Server terlalu lama merespons. Periksa IP & port server.",
                    Color.parseColor("#C62828")
                )
            } catch (e: IOException) {
                // Penanganan Exception: Tidak ada internet / server mati /
                // salah IP
                updateStatus(
                    "Status: Gagal terhubung ke jaringan (${e.localizedMessage})",
                    Color.parseColor("#C62828")
                )
            } catch (e: HttpException) {
                // Penanganan Exception: Kesalahan protokol HTTP
                updateStatus(
                    "Status: Kesalahan HTTP (${e.code()})",
                    Color.parseColor("#C62828")
                )
            } catch (e: Exception) {
                // Penanganan Exception umum lainnya
                updateStatus(
                    "Status: Terjadi kesalahan (${e.localizedMessage})",
                    Color.parseColor("#C62828")
                )
            } finally {
                // Sembunyikan loading dan aktifkan kembali tombol
                binding.progressBar.visibility = View.GONE
                binding.btnTestConnection.isEnabled = true
            }
        }
    }

    // 5. Fungsi pembaruan status koneksi pada UI
    private fun updateStatus(pesan: String, warna: Int) {
        binding.tvConnectionStatus.text = pesan
        binding.tvConnectionStatus.setTextColor(warna)
    }
}