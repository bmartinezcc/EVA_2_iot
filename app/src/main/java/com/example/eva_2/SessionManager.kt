package com.example.eva_2

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Guarda la sesión CIFRADA (AES-256) con Android Keystore.
 * Nunca se guarda la contraseña: solo el token de sesión que entrega el servidor.
 */
class SessionManager(context: Context) {

    private val prefs = EncryptedSharedPreferences.create(
        context,
        "sesion_segura",
        MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )

    val token: String? get() = prefs.getString("token", null)
    val nombre: String get() = prefs.getString("nombre", "").orEmpty()
    val rol: String get() = prefs.getString("rol", "USUARIO").orEmpty()
    val estaLogueado: Boolean get() = !token.isNullOrEmpty()
    val esAdmin: Boolean get() = rol == "ADMIN"

    fun guardar(token: String, nombre: String, rol: String) {
        prefs.edit().putString("token", token).putString("nombre", nombre).putString("rol", rol).apply()
    }

    fun cerrar() {
        prefs.edit().clear().apply()
    }
}
