package com.example.eva_2

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ArrayAdapter
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.example.eva_2.databinding.FragmentHistorialBinding
import kotlinx.coroutines.launch

/** Consulta de datos guardados en AWS RDS (bitácora). ADMIN ve todo, USUARIO solo lo suyo. */
class HistorialFragment : Fragment() {

    private var _binding: FragmentHistorialBinding? = null
    private val binding get() = _binding!!

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?
    ): View {
        _binding = FragmentHistorialBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val sesion = SessionManager(requireContext().applicationContext)

        viewLifecycleOwner.lifecycleScope.launch {
            val r = ApiClient.llamar("historial.php", "GET", null, sesion.token)
            val b = _binding ?: return@launch
            b.progreso.visibility = View.GONE
            when {
                r.codigo == -1 -> { b.tvVacio.setText(R.string.error_red); b.tvVacio.visibility = View.VISIBLE }
                r.codigo == 401 -> {
                    sesion.cerrar()
                    findNavController().navigate(R.id.action_global_loginFragment)
                }
                r.exito -> {
                    val eventos = r.json!!.getJSONArray("eventos")
                    val lineas = (0 until eventos.length()).map { i ->
                        val e = eventos.getJSONObject(i)
                        val ip = e.optString("ip").takeIf { it.isNotEmpty() && it != "null" }
                        buildString {
                            append(e.optString("creado_en")).append("\n")
                            append(e.optString("usuario").ifEmpty { "-" }).append(" · ")
                            append(e.optString("evento"))
                            val det = e.optString("detalle")
                            if (det.isNotEmpty() && det != "null") append(" · ").append(det)
                            if (ip != null) append(" · ").append(ip)
                        }
                    }
                    if (lineas.isEmpty()) b.tvVacio.visibility = View.VISIBLE
                    b.listaEventos.adapter =
                        ArrayAdapter(requireContext(), android.R.layout.simple_list_item_1, lineas)
                }
                else -> { b.tvVacio.text = r.mensaje; b.tvVacio.visibility = View.VISIBLE }
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
