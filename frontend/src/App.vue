<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppShell from '@/components/AppShell.vue'
import { useTheme } from '@/composables/useTheme'

useTheme()
</script>

<template>
  <AppShell>
    <RouterView />
  </AppShell>
</template>
