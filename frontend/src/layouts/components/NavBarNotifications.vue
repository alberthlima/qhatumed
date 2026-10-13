<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import Notifications from '@core/components/Notifications.vue'


const router = useRouter()
const notifications = ref([])
const unreadCount = ref(0)
const summary = ref({
  total_alerts: 0,
  expired_batches: 0,
  expiring_batches: 0,
  low_stock: 0,
  out_of_stock: 0,
})
const isLoading = ref(false)
let refreshInterval = null

const fetchAlerts = async () => {
  try {
    isLoading.value = true
    const res = await $api('/v1/notifications/stock-alerts', { method: 'GET' })
    if (res && res.success) {
      notifications.value = res.data || []
      unreadCount.value = res.unread_count || 0
      summary.value = res.summary || {}
    }
  } catch (error) {
    // Si el usuario no está logueado o hay fallo de red temporal, no romper la UI
    console.warn('[NavBarNotifications] No se pudieron sincronizar las alertas:', error)
  } finally {
    isLoading.value = false
  }
}

const removeNotification = async notificationId => {
  const index = notifications.value.findIndex(item => item.id === notificationId)
  if (index !== -1) {
    notifications.value.splice(index, 1)
  }
  try {
    await $api('/v1/notifications/mark-read', {
      method: 'POST',
      body: { ids: [notificationId] },
    })
  } catch (e) {
    console.error('Error al descartar notificación:', e)
  }
}

const markRead = async notificationIds => {
  const idsArray = Array.isArray(notificationIds) ? notificationIds : [notificationIds]
  notifications.value.forEach(item => {
    if (idsArray.includes(item.id)) {
      item.isSeen = true
    }
  })
  try {
    await $api('/v1/notifications/mark-read', {
      method: 'POST',
      body: { ids: idsArray },
    })
  } catch (e) {
    console.error('Error marcando como leídas:', e)
  }
}

const markUnRead = async notificationIds => {
  const idsArray = Array.isArray(notificationIds) ? notificationIds : [notificationIds]
  notifications.value.forEach(item => {
    if (idsArray.includes(item.id)) {
      item.isSeen = false
    }
  })
  try {
    await $api('/v1/notifications/mark-unread', {
      method: 'POST',
      body: { ids: idsArray },
    })
  } catch (e) {
    console.error('Error marcando como no leídas:', e)
  }
}

const handleNotificationClick = notification => {
  if (!notification.isSeen) {
    markRead([notification.id])
  }
  if (notification.link) {
    router.push(notification.link)
  }
}

const handleViewAll = () => {
  router.push('/inventario/lotes')
}

onMounted(() => {
  fetchAlerts()
  // Actualizar automáticamente cada 2 minutos
  refreshInterval = setInterval(fetchAlerts, 120000)
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>

<template>
  <Notifications
    :notifications="notifications"
    title="Alertas de Inventario"
    empty-text="¡Sin alertas de stock pendientes!"
    footer-text="Ver Control de Lotes y Vencimientos"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
    @click:view-all="handleViewAll"
  />
</template>
