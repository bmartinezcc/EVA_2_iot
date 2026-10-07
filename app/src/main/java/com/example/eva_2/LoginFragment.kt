package com.example.eva_2

import android.os.Bundle
import android.util.Patterns
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.example.eva_2.databinding.FragmentLoginBinding
import kotlinx.coroutines.launch
import org.json.JSONObject

/** Pantalla simple de registro / inicio de sesión. */
class LoginFragment : Fragment() {

    private var _binding: FragmentLoginBinding? = null
    private val binding get() = _binding!!
    private lateinit var sesion: SessionManager
    private var modoRegistro = false

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?
    ): View {
        _binding = FragmentLoginBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        sesion = SessionManager(requireContext().applicationContext)

        // Si ya hay una sesión guardada, se pasa directo al panel
        if (sesion.estaLogueado) {
            irAlPanel()
            return
        }

        binding.tvCambiarModo.setOnClickListener { cambiarModo(!modoRegistro) }
        binding.btnAccion.setOnClickListener { enviar() }
    }

    private fun cambiarModo(registro: Boolean) {
        modoRegistro = registro
        binding.etNombre.visibility = if (registro) View.VISIBLE else View.GONE
        binding.btnAccion.setText(if (registro) R.string.btn_registrar else R.string.btn_iniciar)
        binding.tvCambiarModo.setText(if (registro) R.string.link_ir_login else R.string.link_ir_registro)
        binding.tvMensaje.text = ""
    }

    private fun enviar() {
        val nombre = binding.etNombre.text.toString().trim()
        val email = binding.etEmail.text.toString().trim()
        val password = binding.etPassword.text.toString()

        if (!Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            binding.tvMensaje.text = "Correo electrónico inválido"; return
        }
        if (password.length < 8) {
            binding.tvMensaje.text = "La contraseña debe tener al menos 8 caracteres"; return
        }
        if (modoRegistro && nombre.length < 2) {
            binding.tvMensaje.text = "Ingresa tu nombre"; return
        }

        val cuerpo = JSONObject().put("email", email).put("password", password)
        if (modoRegistro) cuerpo.put("nombre", nombre)

        binding.tvMensaje.text = ""
        binding.progreso.visibility = View.VISIBLE
        binding.btnAccion.isEnabled = false

        viewLifecycleOwner.lifecycleScope.launch {
            val r = ApiClient.llamar(if (modoRegistro) "registro.php" else "login.php", "POST", cuerpo)
            _binding ?: return@launch
            binding.progreso.visibility = View.GONE
            binding.btnAccion.isEnabled = true

            when {
                r.codigo == -1 -> binding.tvMensaje.setText(R.string.error_red)
                !r.exito -> binding.tvMensaje.text = r.mensaje.ifEmpty { "Error (${r.codigo})" }
                modoRegistro -> {
                    cambiarModo(false)
                    binding.etPassword.text?.clear()
                    binding.tvMensaje.text = r.mensaje
                }
                else -> {
                    val j = r.json!!
                    sesion.guardar(j.getString("token"), j.getString("nombre"), j.getString("rol"))
                    binding.etPassword.text?.clear()
                    irAlPanel()
                }
            }
        }
    }

    private fun irAlPanel() {
        findNavController().navigate(R.id.action_loginFragment_to_panelFragment)
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
