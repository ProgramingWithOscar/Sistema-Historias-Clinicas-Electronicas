const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

export async function api(path, options = {}) {
  const response = await fetch(`${baseURL}${path}`, {
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    ...options,
  })

  if (!response.ok) {
    throw new Error(`API ${response.status}: ${response.statusText}`)
  }

  return response.json()
}
