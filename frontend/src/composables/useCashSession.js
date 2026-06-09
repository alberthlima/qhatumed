import { ref, onMounted } from 'vue'
import { $api } from '@/utils/api'

/**
 * useCashSession
 *
 * Composable reutilizable para verificar si el usuario tiene
 * una sesión de caja abierta. Retorna el estado de la sesión y
 * una función para verificar de forma manual / reactiva.
 *
 * Uso:
 *   const { session, isLoading, hasOpenSession, checkSession } = useCashSession()
 */
export function useCashSession() {
    const session = ref(null)
    const isLoading = ref(true)
    const error = ref(null)

    const hasOpenSession = computed(() => !!session.value && session.value.status === 'OPEN')

    const checkSession = async () => {
        isLoading.value = true
        error.value = null
        try {
            const res = await $api('/v1/cash-registers/current-session')
            session.value = res.session ?? null
        } catch (e) {
            session.value = null
            // 404 = sin sesión abierta — normal, no es un error real
            if (e?.status !== 404 && e?.response?.status !== 404) {
                error.value = 'No se pudo verificar el estado de la caja'
            }
        } finally {
            isLoading.value = false
        }
    }

    onMounted(checkSession)

    return { session, isLoading, hasOpenSession, checkSession, error }
}
