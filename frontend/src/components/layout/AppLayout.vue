<script setup>
import { ref } from 'vue'
import AppNavbar from './AppNavbar.vue'
import AppSidebar from './AppSidebar.vue'

const isSidebarOpen = ref(false)

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const closeSidebar = () => {
  isSidebarOpen.value = false
}
</script>

<template>
  <div class="app-layout">
    <AppSidebar
      :is-open="isSidebarOpen"
      @close="closeSidebar"
    />

    <div class="app-main-wrapper">
      <AppNavbar @toggle-sidebar="toggleSidebar" />

      <main class="app-content-area">
        <router-view />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-layout {
  min-height: 100vh;
  display: flex;
  background-color: var(--color-bg-app);
}

.app-main-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  margin-left: 260px;
  transition: margin-left 0.25s ease;
}

.app-content-area {
  flex: 1;
  padding: 32px;
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .app-main-wrapper {
    margin-left: 0;
  }

  .app-content-area {
    padding: 20px 16px;
  }
}
</style>
