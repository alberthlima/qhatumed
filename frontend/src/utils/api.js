import { ofetch } from 'ofetch'

function parseJwt(token) {
  try {
    // Aquí separamos la segunda parte (el payload) que contiene los datos como la fecha de expiración
    const base64Url = token.split('.')[1]; // Obtenemos el payload que está en formato base64Url

    // El formato base64Url usa '-' y '_' en lugar de '+' y '/' respectivamente
    // Necesitamos reemplazarlos para que sea decodificable en base64
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');

    // Decodificamos la cadena base64
    // atob() convierte la cadena base64 a texto legible
    // Luego usamos decodeURIComponent para manejar correctamente los caracteres especiales
    const jsonPayload = decodeURIComponent(atob(base64).split('').map(function (c) {
      return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2); // Convertimos a formato URI
    }).join(''));

    // Finalmente convertimos el payload decodificado a un objeto JSON y lo retornamos
    return JSON.parse(jsonPayload);
  } catch (e) {
    return null;
  }
}

function isTokenExpired(token) {
  const decodedToken = parseJwt(token);
  if (!decodedToken || !decodedToken.exp) {
    return true; // Token inválido o sin fecha de expiración
  }
  const currentTime = Math.floor(Date.now() / 1000); // Tiempo actual en segundos
  return decodedToken.exp < currentTime; // Retorna true si el token ha expirado
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    'Accept': 'application/json',
  },
  async onRequest(response) {
    const accessToken = localStorage.getItem("token"); //useCookie('accessToken').value
    if (isTokenExpired(accessToken) && response.request != 'auth/login') {
      localStorage.removeItem("token");
      localStorage.removeItem("user");

      // Se puede importar o usar directamente si hay auto-import, 
      // pero fuera de componentes es mejor llamar al hook o usar la interfaz directamente.
      // En este contexto, si useMaterializeToast está auto-importado:
      const toast = useMaterializeToast()

      toast.showError(
        'El TOKEN ha expirado.',
        'Ingrese de nuevo al sistema'
      );

      setTimeout(() => {
        window.location.reload();
      }, 1000); // 1 segundo para que el usuario vea el mensaje
    }

    let options = response.options;
    if (accessToken) {
      options.headers = {
        ...options.headers,
        Authorization: `Bearer ${accessToken}`,
      }
    }
  },
})
