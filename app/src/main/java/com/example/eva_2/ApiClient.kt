package com.example.eva_2

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

/** Respuesta de la API. codigo = -1 significa que no hubo conexión. */
data class Respuesta(val codigo: Int, val json: JSONObject?) {
    val exito: Boolean get() = codigo in 200..299
    val mensaje: String get() = json?.optString("mensaje").orEmpty()
}

object ApiClient {

    suspend fun llamar(
        ruta: String,
        metodo: String = "POST",
        cuerpo: JSONObject? = null,
        token: String? = null
    ): Respuesta = withContext(Dispatchers.IO) {
        val con = URL("${Config.BASE_URL}/$ruta").openConnection() as HttpURLConnection
        try {
            con.requestMethod = metodo
            con.connectTimeout = 8000
            con.readTimeout = 8000
            con.setRequestProperty("Accept", "application/json")
            if (token != null) con.setRequestProperty("Authorization", "Bearer $token")
            if (cuerpo != null) {
                con.doOutput = true
                con.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                con.outputStream.use { it.write(cuerpo.toString().toByteArray(Charsets.UTF_8)) }
            }
            val codigo = con.responseCode
            val flujo = if (codigo in 200..299) con.inputStream else con.errorStream
            val texto = flujo?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
            val json = try { JSONObject(texto) } catch (e: Exception) { null }
            Respuesta(codigo, json)
        } catch (e: IOException) {
            Respuesta(-1, null)
        } finally {
            con.disconnect()
        }
    }
}
