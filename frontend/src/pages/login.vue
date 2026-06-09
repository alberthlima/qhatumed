<script setup>
import { useGenerateImageVariant } from '@/@core/composable/useGenerateImageVariant'
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2LoginMaskDark from '@images/pages/auth-v2-login-mask-dark.png'
import authV2LoginMaskLight from '@images/pages/auth-v2-login-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

const form = ref({
  email: '',
  password: '',
  remember: false,
})

const route = useRoute()
const router = useRouter()

const login = async() => {
  if (!validateForm()) return
  
  try {
    const resp = await $api('auth/login', {
      method: 'POST',
      body: {
        email: form.value.email,
        password: form.value.password
      }
    });

    // Si llegamos aquí, el login fue exitoso
    localStorage.setItem("token", resp.access_token);
    localStorage.setItem("user", JSON.stringify(resp.user));
    
    // Mostrar toast de éxito
    toast.showSuccess(
      'Has iniciado sesión correctamente',
      '¡Bienvenido!'
    );

    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : '/')
    })
  } catch (error) {
    console.log('Error de login:', error);
    
    // Manejar diferentes tipos de errores
    if (error.response) {
      const status = error.response.status;
      const errorData = error.response.data;
      
      switch (status) {
        case 401:
          // Credenciales incorrectas
          toast.showError(
            'Las credenciales proporcionadas no son correctas. Por favor, verifica tu correo electrónico y contraseña.',
            'Error de Autenticación'
          );
          break;
        case 404:
          // Usuario no encontrado
          toast.showError(
            'No se encontró una cuenta asociada a este correo electrónico.',
            'Usuario No Encontrado'
          );
          break;
        case 422:
          // Errores de validación
          if (errorData.errors) {
            const firstError = Object.values(errorData.errors)[0][0];
            toast.showError(firstError, 'Error de Validación');
          } else {
            toast.showError(
              errorData.message || 'Los datos proporcionados no son válidos.',
              'Error de Validación'
            );
          }
          break;
        case 429:
          // Demasiados intentos
          toast.showError(
            'Has realizado demasiados intentos de inicio de sesión. Por favor, espera unos minutos antes de intentar nuevamente.',
            'Demasiados Intentos'
          );
          break;
        case 500:
          // Error del servidor
          toast.showError(
            'Ocurrió un error interno del servidor. Por favor, inténtalo más tarde.',
            'Error del Servidor'
          );
          break;
        default:
          // Error genérico
          toast.showError(
            errorData.message || 'Ocurrió un error inesperado. Por favor, inténtalo nuevamente.',
            'Error'
          );
      }
    } else if (error.request) {
      // Error de conexión
      toast.showError(
        'No se pudo conectar con el servidor. Por favor, verifica tu conexión a internet.',
        'Error de Conexión'
      );
    } else {
      // Error desconocido
      toast.showError(
        'Ocurrió un error inesperado. Por favor, inténtalo nuevamente.',
        'Error'
      );
    }
  }
}

const toast = useMaterializeToast()
// Validación básica del formulario
function validateForm() {
  // Validar que el email no esté vacío y tenga formato válido
  if (!form.value.email) {
    toast.showError('El correo electrónico es obligatorio', 'Error de validación');
    return false;
  } else if (!/\S+@\S+\.\S+/.test(form.value.email)) {
    toast.showError('El correo electrónico no tiene un formato válido', 'Error de validación');
    return false;
  }
  
  // Validar que la contraseña no esté vacía
  if (!form.value.password) {
    toast.showError('La contraseña es obligatoria', 'Error de validación');
    return false;
  }
  
  return true;
}

definePage({ meta: { layout: 'blank', unauthenticatedOnly: true } })

const isPasswordVisible = ref(false)
const authV2LoginMask = useGenerateImageVariant(authV2LoginMaskLight, authV2LoginMaskDark)
const authV2LoginIllustration = useGenerateImageVariant(authV2LoginIllustrationLight, authV2LoginIllustrationDark, authV2LoginIllustrationBorderedLight, authV2LoginIllustrationBorderedDark, true)
</script>

<template>
  <VRow
    no-gutters
    class="auth-wrapper"
  >
    <VCol
      md="8"
      class="d-none d-md-flex align-center justify-center position-relative"
    >
      <div class="d-flex align-center justify-center pa-10">
        <img
          :src="authV2LoginIllustration"
          class="auth-illustration w-100"
          alt="auth-illustration"
        >
      </div>
      <VImg
        :src="authV2LoginMask"
        class="d-none d-md-flex auth-footer-mask"
        alt="auth-mask"
      />
    </VCol>
    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
      style="background-color: rgb(var(--v-theme-surface));"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-5 pa-lg-7"
      >
        <VCardText class="d-flex align-center justify-center">
          <img src="@images/logo.png" alt="logo" width="200" />
        </VCardText>
        <VCardText>
          <h4 class="text-h4 mb-1">
            Bienvenido a <span class="text-capitalize">{{ themeConfig.app.title }}!</span>
          </h4>

          <p class="mb-0">
            Ingresa tus datos de acceso
          </p>
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="login">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <VTextField
                  v-model="form.email"
                  autofocus
                  label="Correo Electrónico"
                  type="email"
                  placeholder="tunombre@email.com"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  label="Contraseña"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <!-- login button -->
                <VBtn
                  class="my-2"
                  block
                  type="submit"
                >
                  Acceder
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";
</style>
