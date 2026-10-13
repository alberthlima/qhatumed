<script setup>
import navItems from '@/navigation/vertical'
import { useConfigStore } from '@core/stores/config'
import { themeConfig } from '@themeConfig'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'


// @layouts plugin
import { VerticalNavLayout } from '@layouts'

// SECTION: Loading Indicator
const isFallbackStateActive = ref(false)
const refLoadingIndicator = ref(null)

watch([
  isFallbackStateActive,
  refLoadingIndicator,
], () => {
  if (isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.fallbackHandle()
  if (!isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.resolveHandle()
}, { immediate: true })

// !SECTION
const configStore = useConfigStore()

// ℹ️ Provide animation name for vertical nav collapse icon.
const verticalNavHeaderActionAnimationName = ref(null)

watch([
  () => configStore.isVerticalNavCollapsed,
  () => configStore.isAppRTL,
], val => {
  if (configStore.isAppRTL)
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-back-180' : 'rotate-180'
  else
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-180' : 'rotate-back-180'
}, { immediate: true })

const NavItemsValidated = ref([]);

onMounted(() => {
  let user = localStorage.getItem('user') ? JSON.parse(localStorage.getItem('user')) : null;
  if(user){
    //traer la lista de permisos del usuario autenticado
    let permissions = user.permissions;
    navItems.forEach((nav) => {
      if(user.role.name == "Super-Admin"){
        NavItemsValidated.value.push(nav)
      }else{
        //los navs que tengan el permiso all puede ver por cualquier usuario
        if(nav.permission == "all"){
          NavItemsValidated.value.push(nav)
        }
        if(nav.heading){
          let headingFilter = nav.permissions.filter((permission) => {
            if(permissions.includes(permission)){
              return true;
            }
            return false;
          });
          if(headingFilter.length > 0){
            NavItemsValidated.value.push(nav);
          }
        }
        //los navs que tengan children se deben filtrar para ver a cual opcion se tiene permiso permiso
        if(nav.children){
          let navTemp = nav;
          let newChildren = nav.children.filter((subnav) => {
            if(permissions.includes(subnav.permission)){
              return true;
            }
            return false;
          })
          if(newChildren.length > 0){
            navTemp.children = newChildren;
            NavItemsValidated.value.push(navTemp);
          }
        }else{
          //verificar si los permisos del usuario autenti cado tiene el permiso del nav
          if(permissions.includes(nav.permission)){
            NavItemsValidated.value.push(nav);
          }
        }
      }
    })
  }
})

</script>

<template>
  <VerticalNavLayout :nav-items="NavItemsValidated">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n2 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon icon="ri-menu-line" />
        </IconBtn>

        <NavbarThemeSwitcher />

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <NavBarNotifications class="me-2" />
        <UserProfile />

      </div>
    </template>

    <AppLoadingIndicator ref="refLoadingIndicator" />

    <!-- 👉 Pages -->
    <RouterView v-slot="{ Component }">
      <Suspense
        :timeout="0"
        @fallback="isFallbackStateActive = true"
        @resolve="isFallbackStateActive = false"
      >
        <Component :is="Component" />
      </Suspense>
    </RouterView>

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
</template>

<style lang="scss">
@keyframes rotate-180 {
  from { transform: rotate(0deg); }
  to { transform: rotate(180deg); }
}

@keyframes rotate-back-180 {
  from { transform: rotate(180deg); }
  to { transform: rotate(0deg); }
}

.layout-vertical-nav {
  .nav-header {
    .header-action {
      animation-duration: 0.35s;
      animation-fill-mode: forwards;
      animation-name: v-bind(verticalNavHeaderActionAnimationName);
      transform: rotate(0deg);
    }
  }
}
</style>
