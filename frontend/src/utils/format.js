export function fecha(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-CO', {
    dateStyle: 'short',
    timeStyle: 'medium',
  })
}

export function navegador(userAgent) {
  if (!userAgent) return 'Desconocido'
  const match = userAgent.match(/(Firefox|Edg|Chrome|Safari)\/[\d.]+/)
  return match ? match[0].replace('Edg', 'Edge') : userAgent.slice(0, 40)
}

/** Etiquetas legibles de las acciones que registra el AuditLogger. */
export const acciones = {
  'auth.login.succeeded': 'Inicio de sesión',
  'auth.login.failed': 'Intento fallido',
  'auth.login.throttled': 'Bloqueado por intentos',
  'auth.logout': 'Cierre de sesión',
  'auth.session.read': 'Lectura de sesión',
  'iot.reading.ingested': 'Lectura IoT recibida',
}

/** Nombres de los dispositivos que atiende el resolver del backend. */
export const dispositivos = {
  glucometer: 'Glucómetro',
  sphygmomanometer: 'Tensiómetro',
  pulse_oximeter: 'Oxímetro de pulso',
}
