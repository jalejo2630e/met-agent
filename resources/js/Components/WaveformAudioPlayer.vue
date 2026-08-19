<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import WaveSurfer from 'wavesurfer.js';

const props = defineProps({
    // URL o data URL (data:audio/...;base64,....)
    src: { type: String, default: '' },
    height: { type: Number, default: 44 },
});

const waveEl = ref(null);
const ready = ref(false);
const playing = ref(false);
const current = ref(0);
const duration = ref(0);
const speed = ref(1);
const speeds = [1, 1.5, 2];

let ws = null;
let blobUrl = null;

/** Convierte un data URL a Blob URL (más eficiente que refetch de base64 gigante). */
function toPlayableUrl(src) {
    if (!src || ! src.startsWith('data:')) return src;
    try {
        const comma = src.indexOf(',');
        const meta = src.slice(0, comma);
        const b64 = src.slice(comma + 1);
        const mime = (meta.match(/data:(.*?);base64/) || [])[1] || 'audio/mpeg';
        const bin = atob(b64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        blobUrl = URL.createObjectURL(new Blob([bytes], { type: mime }));
        return blobUrl;
    } catch (e) {
        return src;
    }
}

function destroy() {
    if (ws) {
        try { ws.destroy(); } catch (e) { /* noop */ }
        ws = null;
    }
    if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
        blobUrl = null;
    }
    ready.value = false;
    playing.value = false;
    current.value = 0;
    duration.value = 0;
}

function setup() {
    destroy();
    if (!props.src || !waveEl.value) return;

    const url = toPlayableUrl(props.src);

    ws = WaveSurfer.create({
        container: waveEl.value,
        height: props.height,
        waveColor: '#cbd5e1',
        progressColor: '#6366f1',
        cursorColor: '#4338ca',
        cursorWidth: 2,
        barWidth: 2,
        barGap: 1,
        barRadius: 2,
        normalize: true,
    });

    ws.on('ready', () => {
        ready.value = true;
        duration.value = ws.getDuration();
        ws.setPlaybackRate(speed.value);
    });
    ws.on('timeupdate', (t) => { current.value = t; });
    ws.on('play', () => { playing.value = true; });
    ws.on('pause', () => { playing.value = false; });
    ws.on('finish', () => { playing.value = false; });

    ws.load(url);
}

function toggle() {
    if (ws && ready.value) ws.playPause();
}

function cycleSpeed() {
    const idx = speeds.indexOf(speed.value);
    speed.value = speeds[(idx + 1) % speeds.length];
    if (ws) ws.setPlaybackRate(speed.value);
}

function fmt(s) {
    if (!s && s !== 0) return '0:00';
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return `${m}:${String(sec).padStart(2, '0')}`;
}

onMounted(setup);
watch(() => props.src, setup);
onBeforeUnmount(destroy);
</script>

<template>
    <div class="flex w-full max-w-md items-center gap-3 rounded-lg border border-[#e3e8ee] bg-white px-3 py-2">
        <button
            type="button"
            :disabled="!ready"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#6366f1] text-white transition hover:bg-[#4f46e5] disabled:opacity-50"
            :title="playing ? 'Pausar' : 'Reproducir'"
            @click="toggle"
        >
            <svg v-if="!playing" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
            <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 5h4v14H6zm8 0h4v14h-4z" /></svg>
        </button>

        <div class="min-w-0 flex-1">
            <div ref="waveEl" class="w-full cursor-pointer" />
            <p v-if="!ready" class="mt-0.5 text-[11px] text-gray-400">Cargando onda…</p>
        </div>

        <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ fmt(current) }} / {{ fmt(duration) }}</span>

        <button
            type="button"
            :disabled="!ready"
            class="shrink-0 rounded border border-[#e3e8ee] px-1.5 py-0.5 text-[11px] font-medium text-gray-600 transition hover:bg-gray-50 disabled:opacity-50"
            title="Velocidad de reproducción"
            @click="cycleSpeed"
        >
            {{ speed }}x
        </button>
    </div>
</template>
