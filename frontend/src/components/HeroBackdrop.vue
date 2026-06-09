<script setup lang="ts">
// Décor de hero, purement esthétique (aria-hidden) — reprend la maquette :
// un astre qui se couche derrière une silhouette d'immeubles, plus quelques
// particules. Tout est déterministe (pas de Math.random) pour rester stable au
// rendu et aux tests. Les couleurs s'adaptent au thème via des variables CSS
// (halo violet « lune » en night, halo chaud « soleil » en sunset).

const GROUND = 220

interface Building {
  x: number
  w: number
  h: number
}

// Largeurs/hauteurs fixes -> skyline irrégulière mais reproductible.
const buildings: Building[] = (() => {
  const widths = [70, 54, 60, 48, 64, 58, 62, 54, 70, 56, 60, 66, 52, 64, 58, 70, 54, 62, 66, 56, 60, 64, 58, 70, 80]
  const heights = [96, 132, 70, 150, 104, 178, 92, 134, 64, 120, 190, 100, 146, 78, 128, 168, 96, 140, 72, 118, 182, 104, 150, 84, 130]
  const out: Building[] = []
  let x = 0
  for (let i = 0; i < widths.length; i++) {
    const w = widths[i] ?? 60
    // +6 de chevauchement pour éviter les liserés de fond entre immeubles.
    out.push({ x, w: w + 6, h: heights[i] ?? 110 })
    x += w
  }
  return out
})()

const VIEW_W = buildings.reduce((max, b) => Math.max(max, b.x + b.w), 0)

interface Window {
  x: number
  y: number
  cool: boolean
}

// Fenêtres allumées : grille parcimonieuse par immeuble, allumage pseudo-aléatoire
// mais déterministe (hash de la position).
const windows: Window[] = (() => {
  const out: Window[] = []
  buildings.forEach((b, bi) => {
    const top = GROUND - b.h
    for (let wy = top + 12; wy < GROUND - 12; wy += 18) {
      for (let wx = b.x + 9; wx < b.x + b.w - 9; wx += 15) {
        const seed = (bi * 13 + Math.round(wx) * 7 + Math.round(wy) * 3) % 17
        if (seed < 5) out.push({ x: wx, y: wy, cool: seed === 0 })
      }
    }
  })
  return out
})()

// Particules : position en %, classe de couleur (tokens d'ambiance), délai.
const particles = [
  { t: '14%', l: '10%', c: 'bg-pink-bright', s: 3, d: '0s' },
  { t: '24%', l: '86%', c: 'bg-cyan', s: 2, d: '1.1s' },
  { t: '34%', l: '22%', c: 'bg-violet-bright', s: 2, d: '2.3s' },
  { t: '18%', l: '64%', c: 'bg-gold', s: 3, d: '0.6s' },
  { t: '46%', l: '8%', c: 'bg-cyan', s: 2, d: '1.8s' },
  { t: '40%', l: '92%', c: 'bg-pink', s: 2, d: '3.1s' },
  { t: '28%', l: '48%', c: 'bg-violet-bright', s: 2, d: '2.7s' },
  { t: '52%', l: '74%', c: 'bg-gold', s: 2, d: '0.9s' },
  { t: '58%', l: '34%', c: 'bg-pink-bright', s: 2, d: '2.0s' },
  { t: '12%', l: '40%', c: 'bg-cyan', s: 2, d: '1.4s' },
] as const
</script>

<template>
  <div class="noct-backdrop pointer-events-none overflow-hidden" aria-hidden="true">
    <!-- Astre (soleil/lune) qui se couche derrière les immeubles -->
    <div class="noct-orb" />
    <div class="noct-orb-soft" />

    <!-- Particules flottantes -->
    <span
      v-for="(p, i) in particles"
      :key="i"
      class="noct-particle absolute rounded-full"
      :class="p.c"
      :style="{ top: p.t, left: p.l, width: `${p.s}px`, height: `${p.s}px`, animationDelay: p.d }"
    />

    <!-- Silhouette de la ville -->
    <svg
      class="noct-skyline"
      :viewBox="`0 0 ${VIEW_W} ${GROUND}`"
      preserveAspectRatio="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <linearGradient id="noctBuilding" x1="0" y1="0" x2="0" y2="1">
          <stop class="noct-bld-top" offset="0" />
          <stop class="noct-bld-bot" offset="1" />
        </linearGradient>
      </defs>

      <rect
        v-for="(b, i) in buildings"
        :key="`b${i}`"
        :x="b.x"
        :y="GROUND - b.h"
        :width="b.w"
        :height="b.h"
        fill="url(#noctBuilding)"
      />

      <rect
        v-for="(w, i) in windows"
        :key="`w${i}`"
        class="noct-window"
        :class="{ 'noct-window--cool': w.cool }"
        :x="w.x"
        :y="w.y"
        width="3"
        height="4"
        rx="0.5"
      />

      <!-- Lampadaire -->
      <g class="noct-lamp">
        <rect class="noct-lamp-pole" :x="VIEW_W - 360" y="120" width="4" height="100" rx="2" />
        <circle class="noct-lamp-glow" :cx="VIEW_W - 358" cy="118" r="20" />
        <circle class="noct-lamp-bulb" :cx="VIEW_W - 358" cy="118" r="5" />
      </g>
    </svg>
  </div>
</template>

<style>
/* Couleurs par thème (night = halo violet « lune » ; sunset = halo chaud « soleil »). */
.noct-backdrop {
  --noct-orb: radial-gradient(closest-side, rgba(168, 85, 247, 0.5), rgba(124, 58, 237, 0.16) 55%, transparent 74%);
  --noct-orb-soft: radial-gradient(closest-side, rgba(255, 45, 146, 0.22), transparent 70%);
  --noct-bld-top: #08070f;
  --noct-bld-bot: #1b1533;
  --noct-window: #f5c56b;
  --noct-window-cool: #5eead4;
  --noct-lamp: #ffd98a;
}
html[data-theme='sunset'] .noct-backdrop {
  --noct-orb: radial-gradient(closest-side, rgba(255, 178, 92, 0.72), rgba(255, 116, 64, 0.3) 48%, transparent 72%);
  --noct-orb-soft: radial-gradient(closest-side, rgba(255, 70, 110, 0.24), transparent 70%);
  --noct-bld-top: #120a12;
  --noct-bld-bot: #3a1d2a;
  --noct-window: #ffd98a;
  --noct-window-cool: #ffb15c;
  --noct-lamp: #ffe6a8;
}

.noct-orb {
  position: absolute;
  left: 50%;
  bottom: -190px;
  width: 620px;
  height: 620px;
  max-width: 128vw;
  transform: translateX(-50%);
  border-radius: 9999px;
  background: var(--noct-orb);
  filter: blur(6px);
}
.noct-orb-soft {
  position: absolute;
  left: 50%;
  bottom: -40px;
  width: 840px;
  height: 360px;
  max-width: 150vw;
  transform: translateX(-50%);
  border-radius: 9999px;
  background: var(--noct-orb-soft);
  filter: blur(40px);
}

.noct-skyline {
  position: absolute;
  inset-inline: 0;
  bottom: 0;
  width: 100%;
  height: 190px;
}
@media (min-width: 640px) {
  .noct-skyline {
    height: 230px;
  }
}

.noct-bld-top {
  stop-color: var(--noct-bld-top);
}
.noct-bld-bot {
  stop-color: var(--noct-bld-bot);
}
.noct-window {
  fill: var(--noct-window);
  opacity: 0.85;
}
.noct-window--cool {
  fill: var(--noct-window-cool);
}
.noct-lamp-pole {
  fill: var(--noct-bld-bot);
}
.noct-lamp-bulb {
  fill: var(--noct-lamp);
}
.noct-lamp-glow {
  fill: var(--noct-lamp);
  opacity: 0.28;
  filter: blur(5px);
}

.noct-particle {
  box-shadow: 0 0 6px rgba(245, 241, 255, 0.55);
  opacity: 0.4;
  animation: noct-twinkle 6s ease-in-out infinite;
}
@keyframes noct-twinkle {
  0%,
  100% {
    opacity: 0.2;
    transform: translateY(0);
  }
  50% {
    opacity: 0.85;
    transform: translateY(-5px);
  }
}
@media (prefers-reduced-motion: reduce) {
  .noct-particle {
    animation: none;
  }
}
</style>
