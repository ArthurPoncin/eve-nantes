<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

const isOpen = ref(false)
const root = ref<HTMLElement | null>(null)

function close(): void {
  isOpen.value = false
}

// Même mécanique de fermeture que UserMenu : clic en dehors et Échap.
function onDocumentClick(event: MouseEvent): void {
  if (root.value && !root.value.contains(event.target as Node)) {
    close()
  }
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onDocumentKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onDocumentKeydown)
})
</script>

<template>
  <div ref="root" class="relative sm:hidden">
    <button
      type="button"
      data-testid="mobile-nav-button"
      aria-label="Menu de navigation"
      aria-haspopup="menu"
      :aria-expanded="isOpen"
      class="flex h-9 w-9 flex-col items-center justify-center gap-[5px] rounded-full border border-hairline bg-glass transition hover:border-white/30"
      @click="isOpen = !isOpen"
    >
      <span
        class="h-px w-4 bg-text-2 transition-transform"
        :class="isOpen ? 'translate-y-[3px] rotate-45' : ''"
        aria-hidden="true"
      />
      <span
        class="h-px w-4 bg-text-2 transition-transform"
        :class="isOpen ? '-translate-y-[3px] -rotate-45' : ''"
        aria-hidden="true"
      />
    </button>

    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="-translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <nav
        v-if="isOpen"
        data-testid="mobile-nav"
        role="menu"
        aria-label="Navigation"
        class="glass-strong absolute right-0 top-full z-50 mt-3 flex w-48 flex-col rounded-2xl border border-hairline bg-glass-strong p-2 shadow-xl shadow-black/30"
      >
        <RouterLink
          to="/soiree"
          role="menuitem"
          data-testid="mobile-nav-soiree"
          class="rounded-xl px-3 py-2 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:bg-glass hover:text-text"
          @click="close"
        >
          Soirée
        </RouterLink>
        <RouterLink
          to="/explorer"
          role="menuitem"
          data-testid="mobile-nav-carte"
          class="rounded-xl px-3 py-2 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:bg-glass hover:text-text"
          @click="close"
        >
          Carte
        </RouterLink>
      </nav>
    </Transition>
  </div>
</template>
