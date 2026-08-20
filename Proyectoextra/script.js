// ==================== Variables Globales ====================
let idUsuarioActual = null;
let tipoUsuarioActual = null;
let notificacionesGlobales = [];
let usuariosGlobales = [];
let intervaloNotificaciones = null;
let infoEnvio = null;

// ==================== Inicialización ====================

document.addEventListener('DOMContentLoaded', function() {
    inicializarApp();
    inicializarSidebar();
});

function inicializarSidebar() {
    const links = document.querySelectorAll('.sidebar-link[data-section]');

    // BUG FIX: mostrar solo la sección activa sin duplicar la lógica PHP
    // Ocultar todas las secciones excepto la primera
    document.querySelectorAll('.section-content').forEach((sec, idx) => {
        sec.style.display = idx === 0 ? '' : 'none';
    });

    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            links.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.section-content').forEach(sec => sec.style.display = 'none');
            const section = document.getElementById('section-' + this.dataset.section);
            if (section) section.style.display = '';
        });
    });

    // Marcar el primer link como activo
    const firstLink = document.querySelector('.sidebar-link[data-section]');
    if (firstLink) {
        links.forEach(l => l.classList.remove('active'));
        firstLink.classList.add('active');
    }
}

function inicializarApp() {
    const formularioRegistro = document.getElementById('formularioRegistro');
    if (formularioRegistro) {
        formularioRegistro.addEventListener('submit', registrarUsuario);
    }

    // Solo inicializar el dashboard si el usuario está autenticado (existen los elementos)
    if (!document.getElementById('contenedorNotificaciones')) return;

    obtenerSesion();

    const formularioNotificacion = document.getElementById('formularioNotificacion');
    if (formularioNotificacion) {
        formularioNotificacion.addEventListener('submit', enviarNotificacion);
    }
    const formularioCrearUsuario = document.getElementById('formularioCrearUsuario');
    if (formularioCrearUsuario) {
        formularioCrearUsuario.addEventListener('submit', crearUsuario);
    }
    const buscadorUsuarios = document.getElementById('buscadorUsuarios');
    if (buscadorUsuarios) {
        buscadorUsuarios.addEventListener('input', filtrarUsuarios);
    }
    document.querySelectorAll('.filtro-rol').forEach(boton => {
        boton.addEventListener('click', function() {
            this.classList.toggle('active');
            filtrarUsuarios();
        });
    });

    const btnRefrescar = document.getElementById('btnRefrescar');
    if (btnRefrescar) btnRefrescar.addEventListener('click', cargarNotificaciones);

    const btnBorrarLeidas = document.getElementById('btnBorrarLeidas');
    if (btnBorrarLeidas) btnBorrarLeidas.addEventListener('click', borrarNotificacionesLeidas);

    const formularioTurno = document.getElementById('formularioTurno');
    if (formularioTurno) formularioTurno.addEventListener('submit', guardarTurnoMaestro);

    const mostrarLeidas = document.getElementById('mostrarLeidas');
    if (mostrarLeidas) mostrarLeidas.addEventListener('change', filtrarNotificaciones);

    const titulo = document.getElementById('titulo');
    const mensaje = document.getElementById('mensaje');
    if (titulo) titulo.addEventListener('input', actualizarContador);
    if (mensaje) mensaje.addEventListener('input', actualizarContador);
}

// ==================== Fetch helper ====================

async function solicitarJson(url, options = {}) {
    const respuesta = await fetch(url, options);
    if (!respuesta.ok) {
        throw new Error(`Error HTTP ${respuesta.status}`);
    }
    return respuesta.json();
}

// ==================== Sesión ====================

function obtenerSesion() {
    solicitarJson('api.php?accion=obtenerSesion')
        .then(datos => {
            if (!datos.exito) {
                // No redirigir automáticamente — evita bucle de recargas
                console.warn('Sesión no válida en dashboard');
                return;
            }

            idUsuarioActual   = datos.usuario.id;
            tipoUsuarioActual = datos.usuario.tipo;
            // BUG FIX: usar parámetro correcto en lugar de arguments[0]
            establecerNombreUsuario(datos.usuario.nombre + ' ' + datos.usuario.apellido);
            cargarInfoEnvio();

            // Preseleccionar turno si es maestro
            if (datos.usuario.tipo === 'maestro' && datos.usuario.turno) {
                const sel = document.getElementById('selectTurnoMaestro');
                if (sel) sel.value = datos.usuario.turno;
            }

            cargarUsuariosDestino();
            cargarNotificaciones();

            if (datos.usuario.tipo === 'oficial_mayor') {
                cargarUsuarios();
            }

            clearInterval(intervaloNotificaciones);
            intervaloNotificaciones = setInterval(cargarNotificaciones, 30000);

            // Mostrar/ocultar campos de alumno en crear usuario según tipo seleccionado
            const nuevoTipoEl = document.getElementById('nuevoTipo');
            if (nuevoTipoEl) {
                nuevoTipoEl.addEventListener('change', function() {
                    const camposAlumno    = document.getElementById('camposAlumno');
                    const grupoTelefono   = document.getElementById('grupoNuevoTelefono');
                    const isAlumno        = this.value === 'alumno';
                    const isOficialMayor  = this.value === 'oficial_mayor';
                    if (camposAlumno)   camposAlumno.style.display  = isAlumno ? '' : 'none';
                    if (grupoTelefono)  grupoTelefono.style.display = isOficialMayor ? 'none' : '';
                });
                // Inicial: mostrar campos alumno porque es la opción por default
                const camposAlumnoInit = document.getElementById('camposAlumno');
                if (camposAlumnoInit) camposAlumnoInit.style.display = '';
            }

            // Cargar reportes cuando se abre la sección
            document.querySelectorAll('.sidebar-link[data-section]').forEach(link => {
                link.addEventListener('click', function() {
                    if (this.dataset.section === 'reportes') {
                        cargarReportesSalon();
                    }
                });
            });
        })
        .catch(err => {
            console.error('Error al obtener sesión:', err);
            mostrarError('Error de conexión al obtener sesión');
        });
}

// BUG FIX: usar parámetro con nombre normal en lugar de arguments[0]
function establecerNombreUsuario(nombre) {
    const texto = nombre || 'Cargando...';
    const el = document.getElementById('usuarioActual');
    if (el) el.textContent = 'Usuario: ' + texto;
}

function actualizarContador(event) {
    const elemento = event.target;
    const contador = elemento.id === 'titulo' ? 'contadorTitulo' : 'contadorMensaje';
    const max      = elemento.id === 'titulo' ? 150 : 1000;
    const el = document.getElementById(contador);
    if (el) el.textContent = `${elemento.value.length}/${max}`;
}

// ==================== Info de Envío / Horario ====================

function cargarInfoEnvio() {
    solicitarJson('api.php?accion=obtenerInfoEnvio')
        .then(datos => {
            if (!datos.exito) return;
            infoEnvio = datos;
            actualizarUIHorario();
        })
        .catch(() => {});
}

function actualizarUIHorario() {
    if (!infoEnvio) return;
    const boton   = document.querySelector('#formularioNotificacion button[type="submit"]');
    const aviso   = document.getElementById('avisoHorario');
    const restDiv = document.getElementById('avisoRestantes');

    if (!infoEnvio.puede_enviar) {
        if (boton) boton.disabled = true;
        if (aviso) {
            let msg = '⏰ Fuera del horario permitido (7:00–21:00).';
            if (tipoUsuarioActual === 'alumno' || tipoUsuarioActual === 'maestro') {
                const turno = infoEnvio.turno_usuario;
                if (turno === 'matutino')    msg = '⏰ Tu turno matutino es de 7:00 a 14:00. Actualmente fuera de horario.';
                if (turno === 'vespertino')  msg = '⏰ Tu turno vespertino es de 14:00 a 21:00. Actualmente fuera de horario.';
                if (!turno)                  msg = '⚠️ No tienes turno asignado. Ve a "Mi Turno" para configurarlo.';
            }
            aviso.textContent = msg;
            aviso.style.display = '';
        }
    } else {
        if (boton) boton.disabled = false;
        if (aviso) aviso.style.display = 'none';
    }

    if (restDiv && tipoUsuarioActual === 'alumno' && infoEnvio.restantes !== null) {
        restDiv.textContent = `Notificaciones restantes hoy: ${infoEnvio.restantes}/4`;
        restDiv.style.display = '';
        restDiv.className = 'aviso-restantes ' + (infoEnvio.restantes === 0 ? 'sin-restantes' : '');
        if (infoEnvio.restantes === 0 && boton) boton.disabled = true;
    } else if (restDiv) {
        restDiv.style.display = 'none';
    }
}

// ==================== Responder Notificación ====================

function mostrarModalRespuesta(idNotificacion, tituloOriginal, nombreEmisor) {
    // Eliminar modal previo si existe
    const prev = document.getElementById('modalRespuesta');
    if (prev) prev.remove();

    const modal = document.createElement('div');
    modal.id        = 'modalRespuesta';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-card">
            <div class="modal-header">
                <h3>💬 Responder a ${escapeHtml(nombreEmisor)}</h3>
                <button class="modal-cerrar" id="cerrarModal">✕</button>
            </div>
            <p class="modal-contexto">Respondiendo a: <em>${escapeHtml(tituloOriginal)}</em></p>
            <div class="formulario-grupo">
                <label for="respuestaMensaje">Tu respuesta</label>
                <textarea id="respuestaMensaje" placeholder="Escribe tu respuesta..." rows="4" maxlength="1000"></textarea>
                <small id="contRespuesta">0/1000</small>
            </div>
            <div class="formulario-grupo">
                <label for="respuestaImportancia">Importancia</label>
                <select id="respuestaImportancia">
                    <option value="baja">Baja</option>
                    <option value="media" selected>Media</option>
                    <option value="alta">Alta</option>
                </select>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem">
                <button class="boton boton-secundario" id="cancelarRespuesta">Cancelar</button>
                <button class="boton boton-primario" id="enviarRespuesta" style="width:auto">Enviar respuesta →</button>
            </div>
            <div id="mensajeRespuesta" class="mensaje-estado"></div>
        </div>
    `;
    document.body.appendChild(modal);

    const textarea = document.getElementById('respuestaMensaje');
    textarea.addEventListener('input', () => {
        document.getElementById('contRespuesta').textContent = `${textarea.value.length}/1000`;
    });

    document.getElementById('cerrarModal').addEventListener('click',    () => modal.remove());
    document.getElementById('cancelarRespuesta').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });

    document.getElementById('enviarRespuesta').addEventListener('click', () => {
        const mensaje     = textarea.value.trim();
        const importancia = document.getElementById('respuestaImportancia').value;
        const divMsg      = document.getElementById('mensajeRespuesta');
        const btnEnviar   = document.getElementById('enviarRespuesta');

        if (!mensaje) {
            divMsg.textContent = '✕ Escribe un mensaje antes de enviar';
            divMsg.className   = 'mensaje-estado error';
            return;
        }

        btnEnviar.disabled    = true;
        btnEnviar.textContent = 'Enviando...';

        solicitarJson('api.php?accion=responderNotificacion', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id_notificacion: idNotificacion, mensaje, importancia })
        })
        .then(res => {
            if (res.exito) {
                modal.remove();
                cargarNotificaciones();
                cargarInfoEnvio();
            } else {
                divMsg.textContent = '✕ ' + res.mensaje;
                divMsg.className   = 'mensaje-estado error';
                btnEnviar.disabled    = false;
                btnEnviar.textContent = 'Enviar respuesta →';
            }
        })
        .catch(() => {
            divMsg.textContent = '✕ Error de conexión';
            divMsg.className   = 'mensaje-estado error';
            btnEnviar.disabled    = false;
            btnEnviar.textContent = 'Enviar respuesta →';
        });
    });
}

// ==================== Carga de datos ====================

function cargarUsuariosDestino() {
    const select = document.getElementById('usuarioDestino');
    if (!select) return;

    const grupoRoles    = document.getElementById('grupoRoles');
    const grupoUsuarios = document.getElementById('grupoUsuarios');
    if (grupoRoles)    grupoRoles.innerHTML    = '';
    if (grupoUsuarios) grupoUsuarios.innerHTML = '';

    const ETIQUETAS_ROL = {
        oficial_mayor: '📋 Todos los oficiales mayores',
        intendente:    '🏫 Todos los intendentes',
        maestro:       '📚 Todos los maestros',
        alumno:        '🎒 Todos los alumnos'
    };

    // Cargar roles permitidos y usuarios en paralelo
    Promise.all([
        solicitarJson('api.php?accion=obtenerRolesPermitidos'),
        solicitarJson('api.php?accion=obtenerUsuarios')
    ])
    .then(([datosRoles, datosUsuarios]) => {
        // Rellenar optgroup de roles
        if (datosRoles.exito && grupoRoles) {
            datosRoles.roles.forEach(rol => {
                const option = document.createElement('option');
                option.value       = 'rol:' + rol;
                option.textContent = ETIQUETAS_ROL[rol] || ('Todos los ' + rol + 's');
                grupoRoles.appendChild(option);
            });
        }

        // Rellenar optgroup de usuarios individuales
        if (datosUsuarios.exito && grupoUsuarios) {
            datosUsuarios.usuarios.forEach(usuario => {
                const option = document.createElement('option');
                option.value       = usuario.id;
                option.textContent = `${usuario.nombre} ${usuario.apellido} (${usuario.tipo})`;
                grupoUsuarios.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error al cargar destinatarios:', error);
        mostrarError('Error de conexión al cargar destinatarios');
    });
}

function cargarNotificaciones() {
    if (!idUsuarioActual) return;
    const url = `api.php?accion=obtenerNotificaciones&id_usuario=${idUsuarioActual}`;
    solicitarJson(url)
        .then(datos => {
            if (datos.exito) {
                notificacionesGlobales = datos.notificaciones;
                filtrarNotificaciones();
                actualizarContadorNotificaciones();
            } else {
                mostrarError('Error al cargar notificaciones');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarError('Error de conexión');
        });
}

function actualizarContadorNotificaciones() {
    const noLeidas = notificacionesGlobales.filter(n => !n.leida).length;
    const leidas   = notificacionesGlobales.filter(n =>  n.leida).length;

    const elNoLeidas = document.getElementById('contadorNoLeidas');
    const elLeidas   = document.getElementById('contadorLeidas');
    if (elNoLeidas) elNoLeidas.textContent = `No leídas: ${noLeidas}`;
    if (elLeidas)   elLeidas.textContent   = `Leídas: ${leidas}`;

    const elTotal = document.getElementById('contadorNotificaciones');
    if (elTotal) {
        const total = notificacionesGlobales.length;
        elTotal.textContent = total === 1 ? '1 notificación' : `${total} notificaciones`;
    }
}

// ==================== Filtrado de Notificaciones ====================

function filtrarNotificaciones() {
    // BUG FIX: verificar que el elemento existe antes de acceder a .checked
    const checkboxLeidas = document.getElementById('mostrarLeidas');
    const mostrarLeidas  = checkboxLeidas ? checkboxLeidas.checked : true;
    const contenedor     = document.getElementById('contenedorNotificaciones');
    if (!contenedor) return;

    if (notificacionesGlobales.length === 0) {
        contenedor.innerHTML = '<p class="mensaje-vacio">No hay notificaciones</p>';
        return;
    }

    const prioridad = { alta: 3, media: 2, baja: 1 };
    const ordenar   = arr => arr.sort((a, b) => {
        const va = prioridad[a.importancia] || 2;
        const vb = prioridad[b.importancia] || 2;
        return vb !== va ? vb - va : new Date(b.fecha_envio) - new Date(a.fecha_envio);
    });

    const filtradas  = notificacionesGlobales.filter(n => mostrarLeidas || !n.leida);
    const noLeidas   = ordenar(filtradas.filter(n => !n.leida));
    const leidas     = ordenar(filtradas.filter(n =>  n.leida));

    if (noLeidas.length === 0 && leidas.length === 0) {
        contenedor.innerHTML = '<p class="mensaje-vacio">No hay notificaciones que mostrar</p>';
        return;
    }

    contenedor.innerHTML = `
        <div class="notificaciones-columns">
            <div class="col no-leidas">
                <h3 class="col-titulo">No leídas (${noLeidas.length})</h3>
                <div class="lista-notif lista-no-leidas">
                    ${noLeidas.length ? noLeidas.map(n => crearElementoNotificacion(n)).join('') : '<p class="mensaje-vacio">Sin notificaciones no leídas</p>'}
                </div>
            </div>
            <div class="col leidas">
                <h3 class="col-titulo">Leídas (${leidas.length})</h3>
                <div class="lista-notif lista-leidas">
                    ${mostrarLeidas
                        ? (leidas.length ? leidas.map(n => crearElementoNotificacion(n)).join('') : '<p class="mensaje-vacio">Sin notificaciones leídas</p>')
                        : '<p class="mensaje-vacio">Notificaciones leídas ocultas</p>'}
                </div>
            </div>
        </div>
    `;

    document.querySelectorAll('.notificacion').forEach(elem => {
        elem.addEventListener('click', function(e) {
            // No marcar como leída si se hizo click en el botón responder
            if (e.target.closest('.boton-responder')) return;
            const id           = this.dataset.id;
            const notificacion = notificacionesGlobales.find(n => n.id == id);
            if (notificacion && !notificacion.leida) marcarComoLeida(id);
        });
    });

    document.querySelectorAll('.boton-responder').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id     = this.dataset.id;
            const titulo = this.dataset.titulo;
            const emisor = this.dataset.emisor;
            mostrarModalRespuesta(parseInt(id), titulo, emisor);
        });
    });
}

function crearElementoNotificacion(notificacion) {
    const claseLeida          = notificacion.leida ? 'leida' : '';
    const badgeLeida          = notificacion.leida
        ? '<span class="badge" style="background:rgba(52,211,153,.1);color:#6ee7b7;border:1px solid rgba(52,211,153,.2);">Leída ✓</span>'
        : '<span class="badge" style="background:rgba(248,113,113,.1);color:#fca5a5;border:1px solid rgba(248,113,113,.2);">Sin leer</span>';
    const importancia         = notificacion.importancia || 'media';
    const etiquetaImportancia = `<span class="badge badge-importancia badge-${importancia}">${importancia.charAt(0).toUpperCase() + importancia.slice(1)}</span>`;
    const fecha               = new Date(notificacion.fecha_envio);
    const fechaLectura        = notificacion.fecha_lectura
        ? `<small>Leída: ${formatearFecha(new Date(notificacion.fecha_lectura))}</small>`
        : '';

    const nombreEmisor = escapeHtml(notificacion.nombre_emisor + ' ' + notificacion.apellido_emisor);

    // Bloque de contexto si es respuesta a otra notificación
    let bloqueRespuesta = '';
    if (notificacion.id_respuesta_a && notificacion.titulo_original) {
        const nombreOriginal = notificacion.nombre_emisor_original
            ? escapeHtml(notificacion.nombre_emisor_original + ' ' + (notificacion.apellido_emisor_original || ''))
            : 'alguien';
        bloqueRespuesta = `
            <div class="notif-respuesta-contexto">
                <span class="respuesta-etiqueta">↩ respondió a ${nombreOriginal}</span>
                <p class="respuesta-mensaje-orig">${escapeHtml((notificacion.mensaje_original || '').substring(0, 120))}${(notificacion.mensaje_original || '').length > 120 ? '…' : ''}</p>
            </div>`;
    }

    // Botón de responder (visible si hay permisos — el backend lo validará de todas formas)
    const btnResponder = `<button class="boton boton-responder" data-id="${notificacion.id}" data-titulo="${escapeHtml(notificacion.titulo)}" data-emisor="${nombreEmisor}">↩ Responder</button>`;

    return `
        <div class="notificacion ${claseLeida}" data-id="${notificacion.id}">
            <div class="notificacion-header">
                <h3 class="notificacion-titulo">${escapeHtml(notificacion.titulo)}</h3>
            </div>
            <div class="notif-emisor">
                <span class="emisor-chip">✉ De: <strong>${nombreEmisor}</strong></span>
            </div>
            ${bloqueRespuesta}
            <div class="notificacion-meta">${etiquetaImportancia}</div>
            <p class="notificacion-mensaje">${escapeHtml(notificacion.mensaje)}</p>
            <div class="notificacion-footer">
                <div class="notificacion-fecha">
                    <small>📅 ${formatearFecha(fecha)}</small>
                    ${fechaLectura}
                </div>
                <div class="notificacion-acciones">
                    ${badgeLeida}
                    ${btnResponder}
                </div>
            </div>
        </div>
    `;
}

// ==================== Envío de Notificaciones ====================

function enviarNotificacion(event) {
    event.preventDefault();
    const boton      = document.querySelector('#formularioNotificacion button[type="submit"]');
    const idUsuario  = document.getElementById('usuarioDestino').value;
    const titulo     = document.getElementById('titulo').value.trim();
    const mensaje    = document.getElementById('mensaje').value.trim();
    const importancia = document.getElementById('importancia').value;

    if (!idUsuario) { mostrarError('Por favor selecciona un destinatario'); return; }
    if (!titulo || !mensaje) { mostrarError('Todos los campos son requeridos'); return; }

    boton.disabled    = true;
    boton.textContent = 'Enviando...';

    const datos = {
        id_usuario: idUsuario === 'todos' ? 'todos'
                  : idUsuario.startsWith('rol:') ? idUsuario
                  : parseInt(idUsuario),
        titulo,
        mensaje,
        importancia
    };

    solicitarJson('api.php?accion=enviarNotificacion', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(datos)
    })
    .then(resultado => {
        if (resultado.exito) {
            mostrarExito('¡Notificación enviada correctamente!');
            document.getElementById('formularioNotificacion').reset();
            document.getElementById('importancia').value           = 'media';
            document.getElementById('contadorTitulo').textContent  = '0/150';
            document.getElementById('contadorMensaje').textContent = '0/1000';
            cargarNotificaciones();
            cargarInfoEnvio();
        } else {
            mostrarError('Error: ' + resultado.mensaje);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarError('Error de conexión al enviar notificación');
    })
    .finally(() => {
        boton.disabled    = false;
        boton.textContent = 'Enviar Notificación →';
    });
}

// ==================== Marcar como Leída ====================

function marcarComoLeida(idNotificacion) {
    solicitarJson('api.php?accion=marcarComoLeida', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id_notificacion: parseInt(idNotificacion) })
    })
    .then(resultado => {
        if (resultado.exito) {
            const notificacion = notificacionesGlobales.find(n => n.id == idNotificacion);
            if (notificacion) {
                notificacion.leida         = true;
                notificacion.fecha_lectura = new Date().toISOString();
            }
            filtrarNotificaciones();
            actualizarContadorNotificaciones();
        }
    })
    .catch(error => console.error('Error al marcar como leída:', error));
}

// ==================== Gestión de Usuarios ====================

function cargarUsuarios() {
    const contenedor = document.getElementById('contenedorUsuarios');
    if (!contenedor) return;

    solicitarJson('api.php?accion=obtenerTodosLosUsuarios')
        .then(datos => {
            if (datos.exito) {
                usuariosGlobales = datos.usuarios || [];
                if (usuariosGlobales.length === 0) {
                    contenedor.innerHTML = '<p class="mensaje-vacio">No hay usuarios registrados</p>';
                    return;
                }
                filtrarUsuarios();
            } else {
                contenedor.innerHTML = '<p class="mensaje-vacio">Error al cargar usuarios: ' + datos.mensaje + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contenedor.innerHTML = '<p class="mensaje-vacio">Error de conexión</p>';
        });
}

function crearUsuario(event) {
    event.preventDefault();

    const nombre     = document.getElementById('nuevoNombre').value.trim();
    const apellidoEl = document.getElementById('nuevoApellido');
    const apellido   = apellidoEl ? apellidoEl.value.trim() : '';
    const email      = document.getElementById('nuevoEmail').value.trim();
    const password   = document.getElementById('nuevoPassword').value;
    const tipo       = document.getElementById('nuevoTipo').value;
    const turnoEl    = document.getElementById('nuevoTurno');
    const turno      = turnoEl ? (turnoEl.value || null) : null;
    const boton      = document.querySelector('#formularioCrearUsuario button[type="submit"]');
    const divMensaje = document.getElementById('mensajeCrearUsuario');

    // Campos nuevos
    const telefonoEl = document.getElementById('nuevoTelefono');
    const telefono   = telefonoEl ? (telefonoEl.value.trim() || null) : null;

    let extras = { telefono };

    if (tipo === 'alumno') {
        const carreraEl  = document.getElementById('nuevoCarrera');
        const semestreEl = document.getElementById('nuevoSemestre');
        const salonEl    = document.getElementById('nuevoSalon');
        const gradoEl    = document.getElementById('nuevoGrado');
        const grupoEl    = document.getElementById('nuevoGrupo');
        extras.carrera  = carreraEl  ? (carreraEl.value  || null) : null;
        extras.semestre = semestreEl ? (parseInt(semestreEl.value) || null) : null;
        extras.salon    = salonEl    ? (salonEl.value    || null) : null;
        extras.grado    = gradoEl    ? (parseInt(gradoEl.value) || null) : null;
        extras.grupo    = grupoEl    ? (grupoEl.value    || null) : null;
    }

    if (!nombre || !apellido || !email || !password) {
        mostrarErrorUsuario('Todos los campos son requeridos', divMensaje);
        return;
    }
    if (password.length < 6) {
        mostrarErrorUsuario('La contraseña debe tener al menos 6 caracteres', divMensaje);
        return;
    }

    boton.disabled    = true;
    boton.textContent = 'Creando...';

    solicitarJson('api.php?accion=crearUsuario', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ nombre, apellido, email, password, tipo, turno, ...extras })
    })
    .then(resultado => {
        if (resultado.exito) {
            mostrarExitoUsuario('¡Usuario creado correctamente!', divMensaje);
            document.getElementById('formularioCrearUsuario').reset();
            document.getElementById('nuevoTipo').value = 'alumno';
            const camposAlumno = document.getElementById('camposAlumno');
            if (camposAlumno) camposAlumno.style.display = '';
            cargarUsuarios();
            cargarUsuariosDestino();
        } else {
            mostrarErrorUsuario('Error: ' + resultado.mensaje, divMensaje);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarErrorUsuario('Error de conexión al crear el usuario', divMensaje);
    })
    .finally(() => {
        boton.disabled    = false;
        boton.textContent = 'Crear Usuario';
    });
}

function registrarUsuario(event) {
    event.preventDefault();
    const nombre     = document.getElementById('registroNombre').value.trim();
    const apellido   = document.getElementById('registroApellido').value.trim();
    const email      = document.getElementById('registroEmail').value.trim();
    const password   = document.getElementById('registroPassword').value;
    const turno      = document.getElementById('registroTurno')    ? document.getElementById('registroTurno').value    : null;
    const carrera    = document.getElementById('registroCarrera')  ? document.getElementById('registroCarrera').value  : null;
    const semestre   = document.getElementById('registroSemestre') ? parseInt(document.getElementById('registroSemestre').value) || null : null;
    const salon      = document.getElementById('registroSalon')    ? document.getElementById('registroSalon').value    : null;
    const grado      = document.getElementById('registroGrado')    ? parseInt(document.getElementById('registroGrado').value) || null : null;
    const grupo      = document.getElementById('registroGrupo')    ? document.getElementById('registroGrupo').value    : null;
    const telefono   = document.getElementById('registroTelefono') ? document.getElementById('registroTelefono').value.trim() : null;
    const divMensaje = document.getElementById('mensajeRegistro');
    const boton      = document.querySelector('#formularioRegistro button[type="submit"]');

    if (!nombre || !apellido || !email || !password) {
        mostrarErrorUsuario('Todos los campos son requeridos', divMensaje);
        return;
    }
    if (password.length < 6) {
        mostrarErrorUsuario('La contraseña debe tener al menos 6 caracteres', divMensaje);
        return;
    }

    boton.disabled    = true;
    boton.textContent = 'Registrando...';

    solicitarJson('api.php?accion=registrar', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ nombre, apellido, email, password, turno, carrera, semestre, salon, grado, grupo, telefono })
    })
    .then(resultado => {
        if (resultado.exito) {
            const nombre = resultado.usuario ? resultado.usuario.nombre : '';
            divMensaje.textContent = '✓ Registro exitoso' + (nombre ? '. Bienvenido ' + nombre + '!' : '.');
            divMensaje.className   = 'mensaje-estado exito';
            document.getElementById('formularioRegistro').reset();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            mostrarErrorUsuario('Error: ' + resultado.mensaje, divMensaje);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarErrorUsuario('Error de conexión al registrar', divMensaje);
    })
    .finally(() => {
        boton.disabled    = false;
        boton.textContent = 'Registrarme';
    });
}

function filtrarUsuarios() {
    const buscadorEl   = document.getElementById('buscadorUsuarios');
    const texto        = buscadorEl ? buscadorEl.value.trim().toLowerCase() : '';
    const rolesActivos = Array.from(document.querySelectorAll('.filtro-rol.active')).map(el => el.dataset.rol);
    const contenedor   = document.getElementById('contenedorUsuarios');
    if (!contenedor) return;

    const filtrados = usuariosGlobales.filter(u => {
        const nombre = (u.nombre + ' ' + u.apellido).toLowerCase();
        return (texto === '' || nombre.includes(texto)) && (rolesActivos.length === 0 || rolesActivos.includes(u.tipo));
    });

    if (filtrados.length === 0) {
        contenedor.innerHTML = '<p class="mensaje-vacio">No se encontraron usuarios que coincidan</p>';
        return;
    }

    let html = '<table class="tabla-usuarios"><thead><tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>Email</th><th>Rol</th><th>Turno</th><th>Acciones</th></tr></thead><tbody>';
    filtrados.forEach(usuario => {
        const turnoSelect = ['alumno','maestro','intendente','oficial_mayor'].includes(usuario.tipo)
            ? `<select class="select-turno" data-id="${usuario.id}">
                <option value="">-- Sin turno --</option>
                <option value="matutino"   ${usuario.turno === 'matutino'   ? 'selected' : ''}>Matutino (7-14h)</option>
                <option value="vespertino" ${usuario.turno === 'vespertino' ? 'selected' : ''}>Vespertino (14-21h)</option>
               </select>`
            : '<span style="color:var(--color-texto-muted);font-size:.8rem">N/A</span>';
        html += `
            <tr>
                <td style="color:var(--color-texto-secundario);font-family:var(--font-mono)">#${usuario.id}</td>
                <td><input type="text" class="input-nombre"   data-id="${usuario.id}" value="${escapeHtml(usuario.nombre)}"></td>
                <td><input type="text" class="input-apellido" data-id="${usuario.id}" value="${escapeHtml(usuario.apellido)}"></td>
                <td style="font-family:var(--font-mono);font-size:.82rem">${escapeHtml(usuario.email)}</td>
                <td>
                    <select class="select-rol" data-id="${usuario.id}">
                        <option value="oficial_mayor" ${usuario.tipo === 'oficial_mayor' ? 'selected' : ''}>Oficial Mayor</option>
                        <option value="intendente"    ${usuario.tipo === 'intendente'    ? 'selected' : ''}>Intendente</option>
                        <option value="maestro"       ${usuario.tipo === 'maestro'       ? 'selected' : ''}>Maestro</option>
                        <option value="alumno"        ${usuario.tipo === 'alumno'        ? 'selected' : ''}>Alumno</option>
                    </select>
                </td>
                <td>${turnoSelect}</td>
                <td><button class="boton boton-secundario boton-guardar" data-id="${usuario.id}" style="padding:.4rem .8rem;font-size:.82rem">Guardar</button></td>
            </tr>
        `;
    });
    html += '</tbody></table>';
    contenedor.innerHTML = html;

    contenedor.querySelectorAll('.boton-guardar').forEach(boton => {
        boton.addEventListener('click', function() {
            actualizarUsuarioFormulario(parseInt(this.dataset.id, 10));
        });
    });

    // Cambio de rol: mostrar/ocultar select de turno dinámicamente
    contenedor.querySelectorAll('.select-rol').forEach(sel => {
        sel.addEventListener('change', function() {
            const id   = this.dataset.id;
            const cell = this.closest('tr').querySelector('.select-turno');
            const naEl = this.closest('tr').querySelector('td:nth-child(6)');
            const necesitaTurno = ['alumno','maestro','intendente','oficial_mayor'].includes(this.value);
            if (necesitaTurno) {
                if (!cell) {
                    naEl.innerHTML = `<select class="select-turno" data-id="${id}">
                        <option value="">-- Sin turno --</option>
                        <option value="matutino">Matutino (7-14h)</option>
                        <option value="vespertino">Vespertino (14-21h)</option>
                    </select>`;
                }
            } else {
                if (cell) cell.parentElement.innerHTML = '<span style="color:var(--color-texto-muted);font-size:.8rem">N/A</span>';
            }
        });
    });
}

function actualizarUsuarioFormulario(idUsuario) {
    const nombreInput   = document.querySelector(`.input-nombre[data-id="${idUsuario}"]`);
    const apellidoInput = document.querySelector(`.input-apellido[data-id="${idUsuario}"]`);
    const rolSelect     = document.querySelector(`.select-rol[data-id="${idUsuario}"]`);
    const divMensaje    = document.getElementById('mensajeCrearUsuario') || document.getElementById('mensajeEstado');

    if (!nombreInput || !apellidoInput || !rolSelect) return;

    const nombre      = nombreInput.value.trim();
    const apellido    = apellidoInput.value.trim();
    const tipo        = rolSelect.value;
    const turnoSelect = document.querySelector(`.select-turno[data-id="${idUsuario}"]`);
    const turno       = turnoSelect ? turnoSelect.value || null : null;

    if (!nombre || !apellido) {
        if (divMensaje) mostrarErrorUsuario('Nombre y apellido requeridos', divMensaje);
        return;
    }

    solicitarJson('api.php?accion=actualizarUsuario', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id_usuario: idUsuario, nombre, apellido, tipo, turno })
    })
    .then(resultado => {
        if (resultado.exito) {
            if (divMensaje) mostrarExitoUsuario('Usuario actualizado correctamente', divMensaje);
            cargarUsuarios();
        } else if (divMensaje) {
            mostrarErrorUsuario('Error: ' + resultado.mensaje, divMensaje);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (divMensaje) mostrarErrorUsuario('Error de conexión al actualizar usuario', divMensaje);
    });
}

// ==================== Funciones Auxiliares ====================

function formatearFecha(fecha) {
    const ahora     = new Date();
    const diferencia = ahora - fecha;
    const minutos   = Math.floor(diferencia / 60000);
    const horas     = Math.floor(diferencia / 3600000);
    const dias      = Math.floor(diferencia / 86400000);

    if (minutos < 1)  return 'Hace unos segundos';
    if (minutos < 60) return `Hace ${minutos} ${minutos === 1 ? 'minuto' : 'minutos'}`;
    if (horas < 24)   return `Hace ${horas} ${horas === 1 ? 'hora' : 'horas'}`;
    if (dias < 7)     return `Hace ${dias} ${dias === 1 ? 'día' : 'días'}`;
    return fecha.toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

function mostrarExito(mensaje) {
    const div = document.getElementById('mensajeEstado');
    if (!div) return;
    div.textContent = '✓ ' + mensaje;
    div.className   = 'mensaje-estado exito';
    setTimeout(() => { div.textContent = ''; div.className = 'mensaje-estado'; }, 4000);
}

function mostrarError(mensaje) {
    const div = document.getElementById('mensajeEstado');
    if (!div) return;
    div.textContent = '✕ ' + mensaje;
    div.className   = 'mensaje-estado error';
    setTimeout(() => { div.textContent = ''; div.className = 'mensaje-estado'; }, 4000);
}

function mostrarExitoUsuario(mensaje, elemento) {
    if (!elemento) return;
    elemento.textContent = '✓ ' + mensaje;
    elemento.className   = 'mensaje-estado exito';
    setTimeout(() => { elemento.textContent = ''; elemento.className = 'mensaje-estado'; }, 4000);
}

function mostrarErrorUsuario(mensaje, elemento) {
    if (!elemento) return;
    elemento.textContent = '✕ ' + mensaje;
    elemento.className   = 'mensaje-estado error';
    setTimeout(() => { elemento.textContent = ''; elemento.className = 'mensaje-estado'; }, 4000);
}

// ==================== Borrar notificaciones leídas ====================

function borrarNotificacionesLeidas() {
    const leidas = notificacionesGlobales.filter(n => n.leida);
    if (leidas.length === 0) {
        mostrarEstado('No tienes mensajes leídos para borrar.', false);
        return;
    }
    if (!confirm(`¿Borrar ${leidas.length} mensaje(s) leído(s)? Esta acción no se puede deshacer.`)) return;

    solicitarJson('api.php?accion=borrarLeidas', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(resultado => {
        if (resultado.exito) {
            mostrarEstado(`✓ ${resultado.borrados} mensaje(s) leído(s) eliminado(s)`, true);
            cargarNotificaciones();
        } else {
            mostrarEstado('Error: ' + resultado.mensaje, false);
        }
    })
    .catch(err => console.error('Error al borrar leídas:', err));
}

function mostrarEstado(msg, exito) {
    const el = document.getElementById('mensajeEstado');
    if (!el) return;
    el.textContent = msg;
    el.className   = 'mensaje-estado ' + (exito ? 'exito' : 'error');
    setTimeout(() => { el.textContent = ''; el.className = 'mensaje-estado'; }, 4000);
}

// ==================== Cambiar turno del maestro ====================

function guardarTurnoMaestro(e) {
    e.preventDefault();
    const select = document.getElementById('selectTurnoMaestro');
    const msgEl  = document.getElementById('mensajeTurno');
    if (!select || !select.value) return;

    solicitarJson('api.php?accion=actualizarTurnoMaestro', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ turno: select.value })
    })
    .then(resultado => {
        if (msgEl) {
            msgEl.textContent = resultado.exito ? '✓ ' + resultado.mensaje : '✕ ' + resultado.mensaje;
            msgEl.className   = 'mensaje-estado ' + (resultado.exito ? 'exito' : 'error');
            setTimeout(() => { msgEl.textContent = ''; msgEl.className = 'mensaje-estado'; }, 4000);
        }
        if (resultado.exito) {
            // Actualizar turno en la sesión local para el aviso de horario
            if (infoEnvio) infoEnvio.turno_usuario = select.value;
            cargarInfoEnvio(); // re-evaluar horario
        }
    })
    .catch(err => console.error('Error al guardar turno:', err));
}

// ==================== Reportes por Salón ====================

let graficaInstancia = null;

function cargarReportesSalon() {
    const contenedor = document.getElementById('contenedorGraficaSalon');
    const canvas     = document.getElementById('graficaBarrasSalon');
    if (!contenedor) return;

    contenedor.innerHTML = '<p class="mensaje-vacio">Cargando datos...</p>';
    if (canvas) canvas.style.display = 'none';

    solicitarJson('api.php?accion=obtenerReportesSalon')
        .then(datos => {
            if (!datos.exito) {
                contenedor.innerHTML = '<p class="mensaje-vacio">Error al cargar reportes.</p>';
                return;
            }

            const reportes = datos.reportes || [];
            const turno    = datos.turno    || '';

            if (reportes.length === 0) {
                contenedor.innerHTML = '<p class="mensaje-vacio">No hay reportes de alumnos registrados en tu turno todavía.</p>';
                return;
            }

            // Calcular total para porcentajes
            const totalReportes = reportes.reduce((sum, r) => sum + parseInt(r.total_reportes), 0);

            const labels      = reportes.map(r => 'Salón ' + r.salon);
            const absolutos   = reportes.map(r => parseInt(r.total_reportes));
            const porcentajes = reportes.map(r => totalReportes > 0 ? ((parseInt(r.total_reportes) / totalReportes) * 100).toFixed(1) : 0);

            contenedor.innerHTML = `
                <p style="color:var(--color-texto-suave);margin-bottom:.8rem;">
                    Turno: <strong>${turno || 'Todos'}</strong> — Total de reportes: <strong>${totalReportes}</strong>
                </p>
                <table style="width:100%;border-collapse:collapse;margin-bottom:1.5rem;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--color-borde);">
                            <th style="text-align:left;padding:.5rem .8rem;">Salón</th>
                            <th style="text-align:right;padding:.5rem .8rem;">Reportes</th>
                            <th style="text-align:right;padding:.5rem .8rem;">%</th>
                            <th style="text-align:right;padding:.5rem .8rem;">Alumnos</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${reportes.map((r, i) => `
                        <tr style="border-bottom:1px solid var(--color-borde);">
                            <td style="padding:.5rem .8rem;font-weight:600;">Salón ${r.salon}</td>
                            <td style="text-align:right;padding:.5rem .8rem;">${r.total_reportes}</td>
                            <td style="text-align:right;padding:.5rem .8rem;color:var(--color-acento);">${porcentajes[i]}%</td>
                            <td style="text-align:right;padding:.5rem .8rem;">${r.total_alumnos}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            `;

            // Destruir gráfica anterior si existe
            if (graficaInstancia) {
                graficaInstancia.destroy();
                graficaInstancia = null;
            }

            if (canvas && typeof Chart !== 'undefined') {
                canvas.style.display = '';
                const colores = [
                    '#3b82f6','#10b981','#f59e0b','#ef4444',
                    '#8b5cf6','#ec4899','#06b6d4','#84cc16',
                    '#f97316','#6366f1','#14b8a6','#e11d48'
                ];
                graficaInstancia = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '% de reportes',
                            data: porcentajes,
                            backgroundColor: colores.slice(0, labels.length),
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => `${ctx.parsed.y}% (${absolutos[ctx.dataIndex]} reportes)`
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: { callback: v => v + '%' },
                                grid: { color: 'rgba(255,255,255,0.07)' }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        })
        .catch(err => {
            console.error('Error al cargar reportes:', err);
            contenedor.innerHTML = '<p class="mensaje-vacio">Error de conexión al cargar reportes.</p>';
        });
}
