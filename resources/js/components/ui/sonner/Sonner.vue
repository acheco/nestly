<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon, XIcon } from "@lucide/vue"
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

import 'vue-sonner/style.css';

const props = defineProps<ToasterProps>()
</script>

<template>
  <!--
    Spec 001, R4/R6 (D-103): toast colors by type through vue-sonner's rich colors, which
    read --{type}-bg, --{type}-border and --{type}-text on [data-type] (vue-sonner
    lib/index.css). Soft but opaque: the token mixed into --popover, never transparency.
    The text keeps the normal color; the icon takes the token. info keeps the normal look.
    The contrast guard (tests/js/contrast.test.ts) reads these color-mix expressions.
    rich-colors goes after v-bind so the props' default false does not turn it off.
  -->
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
      '--success-bg': 'color-mix(in oklab, var(--color-success) 12%, var(--popover))',
      '--success-border': 'color-mix(in oklab, var(--color-success) 35%, var(--popover))',
      '--success-text': 'var(--popover-foreground)',
      '--error-bg': 'color-mix(in oklab, var(--destructive) 12%, var(--popover))',
      '--error-border': 'color-mix(in oklab, var(--destructive) 35%, var(--popover))',
      '--error-text': 'var(--popover-foreground)',
      '--warning-bg': 'color-mix(in oklab, var(--color-warning) 12%, var(--popover))',
      '--warning-border': 'color-mix(in oklab, var(--color-warning) 35%, var(--popover))',
      '--warning-text': 'var(--popover-foreground)',
      '--info-bg': 'var(--popover)',
      '--info-border': 'var(--border)',
      '--info-text': 'var(--popover-foreground)',
    }"
    v-bind="props"
    rich-colors
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4 text-success" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4 text-warning" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4 text-destructive" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>
