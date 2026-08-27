const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

// El endpoint de la cookie CSRF vive en la raíz del backend, fuera de /api.
const rootURL = baseURL.replace(/\/api\/?$/, '')

/**
 * Error de API con el estado HTTP y los errores de validación de Laravel,
 * para que la interfaz pueda distinguir un 401 de un 422 o un 429.
 */
export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

function readCookie(name) {
  const match = document.cookie.match(new RegExp(`(^|; )${name}=([^;]*)`))
  return match ? decodeURIComponent(match[2]) : null
}

/**
 * Pide la cookie XSRF-TOKEN si aún no la tenemos. Sanctum la envía junto con la
 * cookie de sesión, que es HttpOnly y por tanto invisible para JavaScript.
 */
async function ensureCsrfCookie() {
  if (readCookie('XSRF-TOKEN')) return

  await fetch(`${rootURL}/sanctum/csrf-cookie`, { credentials: 'include' })
}

export async function api(path, options = {}) {
  const method = options.method ?? 'GET'
  const mutating = !['GET', 'HEAD', 'OPTIONS'].includes(method)

  if (mutating) await ensureCsrfCookie()

  const xsrfToken = readCookie('XSRF-TOKEN')

  let response
  try {
    response = await fetch(`${baseURL}${path}`, {
      ...options,
      // Sin esto el navegador no manda ni recibe la cookie de sesión.
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        ...options.headers,
      },
      body: options.body ? JSON.stringify(options.body) : undefined,
    })
  } catch {
    // fetch sólo rechaza por fallo de red o bloqueo de CORS, nunca por 4xx/5xx.
    throw new ApiError('No se pudo conectar con el servidor.', 0)
  }

  if (response.status === 204) return null

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new ApiError(
      payload.message ?? `Error ${response.status}`,
      response.status,
      payload.errors ?? {},
    )
  }

  return payload
}
