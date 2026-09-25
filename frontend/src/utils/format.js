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
  'hce.export.generated': 'Exportación de la HCE',
  'hce.encounter.created': 'Nota de atención registrada',
  'hce.template.applied': 'Plantilla aplicada',
  'hce.template.saved': 'Plantilla guardada',
  'hce.interaction.checked': 'Interacciones verificadas',
}

/** Tipos de atención que atiende el resolver de directores del backend. */
export const tiposAtencion = {
  emergency: 'Urgencias',
  outpatient_control: 'Control ambulatorio',
  teleconsultation: 'Teleconsulta',
}

/** Nombres de los dispositivos que atiende el resolver del backend. */
export const dispositivos = {
  glucometer: 'Glucómetro',
  sphygmomanometer: 'Tensiómetro',
  pulse_oximeter: 'Oxímetro de pulso',
}
