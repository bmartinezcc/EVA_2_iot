package com.example.eva_2

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.example.eva_2.databinding.FragmentPanelBinding
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import org.json.JSONObject

/**
 * Panel principal:
 *  - Monitoreo: estado del dispositivo IoT (Raspberry) cada 3 s.
 *  - USUARIO y ADMIN: ingresan una clave temporal.
 *  - Solo ADMIN: genera claves temporales.
 */
class PanelFragment : Fragment() {

    private var _binding: FragmentPanelBinding? = null
    private val binding get() = _binding!!
    private lateinit var sesion: SessionManager
    private var jobEstado: Job? = null

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?
    ): View {
        _binding = FragmentPanelBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        sesion = SessionManager(requireContext().applicationContext)

        binding.tvSaludo.text = "Hola, ${sesion.nombre} (${sesion.rol})"
        binding.layoutAdmin.visibility = if (sesion.esAdmin) View.VISIBLE else View.GONE

        binding.btnRefrescar.setOnClickListener {
            viewLifecycleOwner.lifecycleScope.launch { refrescarEstado() }
        }
        binding.btnValidar.setOnClickListener { validarClave() }
        binding.btnGenerar.setOnClickListener { generarClave() }
        binding.btnHistorial.setOnClickListener {
            findNavController().navigate(R.id.action_panelFragment_to_historialFragment)
        }
        binding.btnCerrarSesion.setOnClickListener { cerrarSesion() }
    }

    override fun onStart() {
        super.onStart()
        // Monitoreo periódico del dispositivo mientras la pantalla está visible
        jobEstado = viewLifecycleOwner.lifecycleScope.launch {
            while (isActive) {
                refrescarEstado()
                delay(3000)
            }
        }
    }

    override fun onStop() {
        jobEstado?.cancel()
        super.onStop()
    }

    private suspend fun refrescarEstado() {
        val r = ApiClient.llamar(
            "estado.php?dispositivo_id=${Config.DISPOSITIVO_ID}", "GET", null, sesion.token
        )
        val b = _binding ?: return
        when {
            r.codigo == -1 -> b.tvEstado.setText(R.string.error_red)
            r.codigo == 401 -> sesionExpirada()
            r.exito -> {
                val d = r.json!!.getJSONObject("dispositivo")
                b.tvEstado.text = "Dispositivo: ${d.getString("nombre")}\nEstado: ${d.getString("estado")}"
            }
            else -> b.tvEstado.text = r.mensaje
        }
    }

    private fun validarClave() {
        val clave = binding.etClave.text.toString().trim()
        if (clave.length != 6) {
            binding.tvMensaje.text = "La clave debe tener 6 dígitos"; return
        }
        val cuerpo = JSONObject().put("dispositivo_id", Config.DISPOSITIVO_ID).put("clave", clave)
        binding.btnValidar.isEnabled = false
        viewLifecycleOwner.lifecycleScope.launch {
            val r = ApiClient.llamar("validar_clave.php", "POST", cuerpo, sesion.token)
            val b = _binding ?: return@launch
            b.btnValidar.isEnabled = true
            when {
                r.codigo == -1 -> b.tvMensaje.setText(R.string.error_red)
                r.codigo == 401 -> sesionExpirada()
                else -> {
                    b.tvMensaje.text = r.mensaje.ifEmpty { "Error (${r.codigo})" }
                    if (r.exito) { b.etClave.text?.clear(); refrescarEstado() }
                }
            }
        }
    }

    private fun generarClave() {
        val minutos = binding.etMinutos.text.toString().toIntOrNull()
        if (minutos == null || minutos !in 1..60) {
            binding.tvMensaje.text = "La vigencia debe estar entre 1 y 60 minutos"; return
        }
        val cuerpo = JSONObject().put("dispositivo_id", Config.DISPOSITIVO_ID).put("minutos", minutos)
        binding.btnGenerar.isEnabled = false
        viewLifecycleOwner.lifecycleScope.launch {
            val r = ApiClient.llamar("generar_clave.php", "POST", cuerpo, sesion.token)
            val b = _binding ?: return@launch
            b.btnGenerar.isEnabled = true
            when {
                r.codigo == -1 -> b.tvMensaje.setText(R.string.error_red)
                r.codigo == 401 -> sesionExpirada()
                r.exito -> {
                    val j = r.json!!
                    b.tvClaveGenerada.text = j.getString("clave")
                    b.tvMensaje.text = "Clave válida por ${j.getInt("minutos")} min (hasta ${j.getString("expira_en")})"
                }
                else -> b.tvMensaje.text = r.mensaje.ifEmpty { "Error (${r.codigo})" }
            }
        }
    }

    private fun cerrarSesion() {
        val token = sesion.token
        // Se revoca la sesión en el servidor (mejor esfuerzo) y se borra la local
        viewLifecycleOwner.lifecycleScope.launch {
            ApiClient.llamar("logout.php", "POST", null, token)
        }
        sesion.cerrar()
        findNavController().navigate(R.id.action_global_loginFragment)
    }

    private fun sesionExpirada() {
        sesion.cerrar()
        findNavController().navigate(R.id.action_global_loginFragment)
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
