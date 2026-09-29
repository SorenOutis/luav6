@php
    $record = $getRecord();
    $currentAudioUrl = $record?->audio_path ? \App\Support\PublicFileUrl::resolve($record->audio_path) : null;
@endphp

<div
    x-data="audioTrimmer({
        currentAudioUrl: @js($currentAudioUrl),
        currentDuration: @js($record?->duration_seconds ?? null),
    })"
    class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900"
>
    <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
        <div class="flex items-center gap-2.5">
            <div class="flex size-9 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                    Built-in Audio Preview & Optional Trimmer
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Preview audio tracks, optionally clip any desired loop/segment, or play the entire uploaded audio file with no time limit.
                </p>
            </div>
        </div>

        <template x-if="currentAudioUrl">
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                Saved audio active
            </span>
        </template>
    </div>

    <!-- Existing saved audio playback (if editing) -->
    <template x-if="currentAudioUrl && !audioBuffer">
        <div class="mt-4 flex flex-col gap-2 rounded-lg bg-gray-50 p-3.5 dark:bg-gray-800/60 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-xs">
                <span class="font-medium text-gray-700 dark:text-gray-200">Current Saved Audio Track:</span>
                <span class="ml-1 text-gray-500 dark:text-gray-400" x-text="currentDuration ? `${formatTime(currentDuration)} (${currentDuration}s)` : 'Full Audio'"></span>
            </div>
            <audio :src="currentAudioUrl" controls class="h-9 w-full sm:w-72"></audio>
        </div>
    </template>

    <!-- Upload / Pick file to preview & trim -->
    <div class="mt-4">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            Load Audio File to Preview or Trim (MP3, WAV, M4A, OGG, FLAC)
        </label>
        <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <svg class="size-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span x-text="fileName ? 'Change Audio File' : 'Select Audio File'"></span>
                <input
                    type="file"
                    accept="audio/*"
                    class="sr-only"
                    @change="loadAudioFile($event)"
                />
            </label>

            <span x-show="fileName" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs font-mono" x-text="fileName"></span>

            <span x-show="isDecoding" class="inline-flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400">
                <svg class="size-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Decoding audio in browser...
            </span>
        </div>
    </div>

    <!-- Trimmer workspace (shown once audio is loaded) -->
    <div x-show="audioBuffer" x-cloak class="mt-5 space-y-4 rounded-xl border border-primary-200/60 bg-primary-50/20 p-4 dark:border-primary-900/40 dark:bg-primary-950/10">
        <!-- Waveform canvas -->
        <div class="relative overflow-hidden rounded-lg bg-gray-950 p-2 shadow-inner">
            <canvas
                x-ref="waveformCanvas"
                width="800"
                height="90"
                class="w-full h-[90px] block cursor-pointer"
                @click="seekFromClick($event)"
            ></canvas>

            <!-- Playhead scrubber line -->
            <div
                x-show="isPlaying"
                class="pointer-events-none absolute top-0 bottom-0 w-0.5 bg-yellow-400 shadow-sm"
                :style="`left: ${playheadPercent}%`"
            ></div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Total Track:</span>
                    <span class="font-mono font-medium text-gray-800 dark:text-gray-200" x-text="formatTime(totalDuration)"></span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Selected Duration:</span>
                    <span
                        class="font-mono font-bold text-emerald-600 dark:text-emerald-400"
                        x-text="`${formatTime(clipDuration)} (${clipDuration.toFixed(1)}s)`"
                    ></span>
                </div>
                <!-- Quick Selection Presets -->
                <div class="flex items-center gap-1.5 pl-1">
                    <button
                        type="button"
                        @click="selectFullTrack()"
                        class="rounded border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Whole Audio
                    </button>
                    <button
                        type="button"
                        @click="selectClipPreset(30)"
                        class="rounded border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        30s Preset
                    </button>
                    <button
                        type="button"
                        @click="selectClipPreset(60)"
                        class="rounded border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        60s Preset
                    </button>
                </div>
            </div>

            <!-- Loop Playback & Export Buttons -->
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="togglePlayLoop()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                >
                    <template x-if="!isPlaying">
                        <svg class="size-3.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </template>
                    <template x-if="isPlaying">
                        <svg class="size-3.5 fill-current" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </template>
                    <span x-text="isPlaying ? 'Pause Playback' : 'Play Selection'"></span>
                </button>

                <button
                    type="button"
                    @click="exportTrimmedWav()"
                    :disabled="clipDuration < 0.1 || isExporting"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-primary-500 disabled:opacity-50"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span x-text="isExporting ? 'Exporting...' : (isFullTrack ? 'Export Audio (.WAV)' : 'Export Trimmed Clip (.WAV)')"></span>
                </button>
            </div>
        </div>

        <!-- Range Inputs for Start and End Times -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2 border-t border-primary-200/40 dark:border-primary-900/30">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Selection Start Time: <span class="font-mono text-primary-600 dark:text-primary-400 font-semibold" x-text="formatTime(startTime)"></span>
                </label>
                <div class="flex items-center gap-2 mt-1">
                    <input
                        type="range"
                        min="0"
                        :max="Math.max(0, totalDuration - 0.1)"
                        step="0.1"
                        x-model.number="startTime"
                        @input="handleStartTimeChange()"
                        class="w-full accent-primary-600"
                    />
                    <input
                        type="number"
                        min="0"
                        :max="totalDuration"
                        step="0.1"
                        x-model.number="startTime"
                        @input="handleStartTimeChange()"
                        class="w-20 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-mono dark:border-gray-700 dark:bg-gray-800"
                    />
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Selection End Time: <span class="font-mono text-primary-600 dark:text-primary-400 font-semibold" x-text="formatTime(endTime)"></span>
                </label>
                <div class="flex items-center gap-2 mt-1">
                    <input
                        type="range"
                        :min="startTime + 0.1"
                        :max="totalDuration"
                        step="0.1"
                        x-model.number="endTime"
                        @input="handleEndTimeChange()"
                        class="w-full accent-primary-600"
                    />
                    <input
                        type="number"
                        :min="startTime + 0.1"
                        :max="totalDuration"
                        step="0.1"
                        x-model.number="endTime"
                        @input="handleEndTimeChange()"
                        class="w-20 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-mono dark:border-gray-700 dark:bg-gray-800"
                    />
                </div>
            </div>
        </div>

        <!-- Feedback message when exported -->
        <div x-show="exportSuccessMessage" x-cloak class="rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200">
            <span class="font-semibold">Ready!</span>
            <span x-text="exportSuccessMessage"></span>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('audioTrimmer', ({ currentAudioUrl, currentDuration }) => ({
        currentAudioUrl: currentAudioUrl || null,
        currentDuration: currentDuration || null,
        fileName: '',
        isDecoding: false,
        audioBuffer: null,
        audioContext: null,
        totalDuration: 0,
        startTime: 0.0,
        endTime: 0.0,
        clipDuration: 0.0,
        isPlaying: false,
        playheadPercent: 0,
        playheadInterval: null,
        sourceNode: null,
        playStartTime: 0,
        isExporting: false,
        exportSuccessMessage: '',

        init() {
            // Setup listener for direct FilePond uploads to auto-populate duration
            this.setupFilePondDurationListener();
        },

        setupFilePondDurationListener() {
            document.addEventListener('FilePond:addfile', (e) => {
                const file = e.detail?.file?.file;
                if (file && file.type?.startsWith('audio/')) {
                    const audio = new Audio();
                    const objectUrl = URL.createObjectURL(file);
                    audio.src = objectUrl;
                    audio.onloadedmetadata = () => {
                        const dur = Number(audio.duration.toFixed(1));
                        this.updateDurationInputs(dur);
                        URL.revokeObjectURL(objectUrl);
                    };
                }
            });
        },

        updateDurationInputs(durationVal) {
            const durationInputs = document.querySelectorAll('input[type="number"], input[name*="duration_seconds"]');
            durationInputs.forEach(input => {
                if (input.getAttribute('name')?.includes('duration_seconds') || input.id?.includes('duration_seconds')) {
                    input.value = durationVal;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        },

        get isFullTrack() {
            return this.totalDuration > 0 && Math.abs(this.clipDuration - this.totalDuration) < 0.2;
        },

        getAudioContext() {
            if (!this.audioContext) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                this.audioContext = new AudioCtx();
            }
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume();
            }
            return this.audioContext;
        },

        async loadAudioFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            this.stopPlayback();
            this.fileName = file.name;
            this.isDecoding = true;
            this.exportSuccessMessage = '';

            try {
                const arrayBuffer = await file.arrayBuffer();
                const ctx = this.getAudioContext();
                this.audioBuffer = await ctx.decodeAudioData(arrayBuffer);
                this.totalDuration = this.audioBuffer.duration;
                this.startTime = 0.0;
                // By default, select the entire uploaded track
                this.endTime = this.totalDuration;
                this.updateClipDuration();
                this.updateDurationInputs(Number(this.totalDuration.toFixed(1)));

                this.$nextTick(() => {
                    this.drawWaveform();
                });
            } catch (err) {
                console.error('Audio decode error:', err);
                alert('Could not decode audio file. Please check that the file is a valid audio format.');
            } finally {
                this.isDecoding = false;
            }
        },

        selectFullTrack() {
            this.startTime = 0.0;
            this.endTime = this.totalDuration;
            this.updateClipDuration();
            this.updateDurationInputs(Number(this.totalDuration.toFixed(1)));
            this.drawWaveform();
            if (this.isPlaying) this.restartPlayback();
        },

        selectClipPreset(seconds = 30) {
            this.startTime = 0.0;
            this.endTime = Math.min(this.totalDuration, seconds);
            this.updateClipDuration();
            this.updateDurationInputs(Number(this.clipDuration.toFixed(1)));
            this.drawWaveform();
            if (this.isPlaying) this.restartPlayback();
        },

        handleStartTimeChange() {
            if (this.startTime < 0) this.startTime = 0;
            if (this.startTime > this.totalDuration - 0.1) this.startTime = Math.max(0, this.totalDuration - 0.1);
            if (this.endTime <= this.startTime) {
                this.endTime = Math.min(this.totalDuration, this.startTime + 0.1);
            }
            this.updateClipDuration();
            this.updateDurationInputs(Number(this.clipDuration.toFixed(1)));
            this.drawWaveform();
            if (this.isPlaying) this.restartPlayback();
        },

        handleEndTimeChange() {
            if (this.endTime > this.totalDuration) this.endTime = this.totalDuration;
            if (this.endTime <= this.startTime) {
                this.startTime = Math.max(0, this.endTime - 0.1);
            }
            this.updateClipDuration();
            this.updateDurationInputs(Number(this.clipDuration.toFixed(1)));
            this.drawWaveform();
            if (this.isPlaying) this.restartPlayback();
        },

        updateClipDuration() {
            this.clipDuration = Math.max(0.1, Number((this.endTime - this.startTime).toFixed(2)));
        },

        formatTime(seconds) {
            const sec = Math.max(0, seconds || 0);
            const mins = Math.floor(sec / 60);
            const remainingSec = (sec % 60).toFixed(1);
            return `${mins}:${remainingSec.padStart(4, '0')}`;
        },

        seekFromClick(event) {
            if (!this.audioBuffer) return;
            const canvas = this.$refs.waveformCanvas;
            const rect = canvas.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const percent = Math.max(0, Math.min(1, clickX / rect.width));
            const clickedTime = percent * this.totalDuration;

            if (event.shiftKey) {
                // Shift-click moves end time
                this.endTime = Math.max(this.startTime + 0.1, Math.min(this.totalDuration, clickedTime));
            } else {
                // Click moves start time
                this.startTime = Math.max(0, Math.min(this.totalDuration - 0.1, clickedTime));
                if (this.endTime <= this.startTime) {
                    this.endTime = Math.min(this.totalDuration, this.startTime + (this.clipDuration || 30.0));
                }
            }
            this.updateClipDuration();
            this.updateDurationInputs(Number(this.clipDuration.toFixed(1)));
            this.drawWaveform();
            if (this.isPlaying) this.restartPlayback();
        },

        drawWaveform() {
            const canvas = this.$refs.waveformCanvas;
            if (!canvas || !this.audioBuffer) return;

            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;
            ctx.clearRect(0, 0, width, height);

            const data = this.audioBuffer.getChannelData(0);
            const step = Math.ceil(data.length / width);
            const amp = height / 2;

            const startX = (this.startTime / this.totalDuration) * width;
            const endX = (this.endTime / this.totalDuration) * width;

            // Draw unselected background bars
            ctx.fillStyle = '#374151'; // gray-700
            for (let i = 0; i < width; i += 2) {
                let min = 1.0;
                let max = -1.0;
                for (let j = 0; j < step; j++) {
                    const datum = data[(i * step) + j];
                    if (datum < min) min = datum;
                    if (datum > max) max = datum;
                }
                const barHeight = Math.max(2, (max - min) * amp * 0.9);
                ctx.fillRect(i, amp - barHeight / 2, 1.5, barHeight);
            }

            // Draw selected highlight region
            ctx.fillStyle = 'rgba(217, 119, 87, 0.22)';
            ctx.fillRect(startX, 0, Math.max(2, endX - startX), height);

            // Draw selected active bars
            ctx.fillStyle = '#D97757'; // primary accent
            for (let i = Math.floor(startX); i <= Math.ceil(endX); i += 2) {
                let min = 1.0;
                let max = -1.0;
                for (let j = 0; j < step; j++) {
                    const datum = data[(i * step) + j];
                    if (datum < min) min = datum;
                    if (datum > max) max = datum;
                }
                const barHeight = Math.max(2, (max - min) * amp * 0.9);
                ctx.fillRect(i, amp - barHeight / 2, 1.5, barHeight);
            }

            // Draw start and end boundaries
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(startX - 1, 0, 2, height);
            ctx.fillRect(endX - 1, 0, 2, height);
        },

        togglePlayLoop() {
            if (this.isPlaying) {
                this.stopPlayback();
            } else {
                this.startLoopPlayback();
            }
        },

        startLoopPlayback() {
            if (!this.audioBuffer) return;
            const ctx = this.getAudioContext();
            this.stopPlayback();

            this.sourceNode = ctx.createBufferSource();
            this.sourceNode.buffer = this.audioBuffer;
            this.sourceNode.loop = true;
            this.sourceNode.loopStart = this.startTime;
            this.sourceNode.loopEnd = this.endTime;
            this.sourceNode.connect(ctx.destination);

            this.sourceNode.start(0, this.startTime);
            this.isPlaying = true;
            this.playStartTime = ctx.currentTime;

            const duration = this.endTime - this.startTime;
            this.playheadInterval = setInterval(() => {
                if (!this.isPlaying) return;
                const elapsed = (ctx.currentTime - this.playStartTime) % duration;
                const currentTime = this.startTime + elapsed;
                this.playheadPercent = (currentTime / this.totalDuration) * 100;
            }, 30);
        },

        stopPlayback() {
            if (this.sourceNode) {
                try {
                    this.sourceNode.stop();
                    this.sourceNode.disconnect();
                } catch (e) {}
                this.sourceNode = null;
            }
            if (this.playheadInterval) {
                clearInterval(this.playheadInterval);
                this.playheadInterval = null;
            }
            this.isPlaying = false;
            this.playheadPercent = (this.startTime / (this.totalDuration || 1)) * 100;
        },

        restartPlayback() {
            this.stopPlayback();
            this.startLoopPlayback();
        },

        async exportTrimmedWav() {
            if (!this.audioBuffer) return;
            this.isExporting = true;
            this.stopPlayback();

            try {
                const sampleRate = this.audioBuffer.sampleRate;
                const channels = this.audioBuffer.numberOfChannels;
                const startSample = Math.floor(this.startTime * sampleRate);
                const endSample = Math.min(this.audioBuffer.length, Math.floor(this.endTime * sampleRate));
                const length = endSample - startSample;

                // Slice audio buffer channels
                const channelData = [];
                for (let c = 0; c < channels; c++) {
                    channelData.push(this.audioBuffer.getChannelData(c).subarray(startSample, endSample));
                }

                // Encode to 16-bit PCM WAV
                const wavBlob = this.encodeWAV(channelData, channels, sampleRate, length);
                const suffix = this.isFullTrack ? '_full.wav' : `_${Math.round(this.clipDuration)}s.wav`;
                const safeName = (this.fileName.replace(/\.[^/.]+$/, '') || 'soundtrack') + suffix;
                const trimmedFile = new File([wavBlob], safeName, { type: 'audio/wav' });

                // Update duration input on Filament form if present
                const durationVal = Number((this.endTime - this.startTime).toFixed(1));
                this.updateDurationInputs(durationVal);

                // Check for FilePond root to load automatically
                let autoLoaded = false;
                const filepondRoot = document.querySelector('.filepond--root');
                if (filepondRoot && window.FilePond) {
                    const pond = window.FilePond.find(filepondRoot);
                    if (pond) {
                        pond.removeFiles();
                        pond.addFile(trimmedFile);
                        autoLoaded = true;
                    }
                }

                // Also trigger instant browser download
                const downloadUrl = URL.createObjectURL(wavBlob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = safeName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(() => URL.revokeObjectURL(downloadUrl), 5000);

                this.exportSuccessMessage = autoLoaded
                    ? `Exported "${safeName}" and loaded into the Audio File field below! Duration set to ${durationVal}s.`
                    : `Exported "${safeName}" (${durationVal}s). Attach or drag this file into the Audio File upload field below.`;
            } catch (err) {
                console.error('WAV export error:', err);
                alert('Could not export WAV: ' + err.message);
            } finally {
                this.isExporting = false;
            }
        },

        encodeWAV(channelData, numChannels, sampleRate, numSamples) {
            const bytesPerSample = 2; // 16-bit PCM
            const blockAlign = numChannels * bytesPerSample;
            const byteRate = sampleRate * blockAlign;
            const dataSize = numSamples * blockAlign;
            const buffer = new ArrayBuffer(44 + dataSize);
            const view = new DataView(buffer);

            // Write RIFF header
            this.writeString(view, 0, 'RIFF');
            view.setUint32(4, 36 + dataSize, true);
            this.writeString(view, 8, 'WAVE');
            this.writeString(view, 12, 'fmt ');
            view.setUint32(16, 16, true); // PCM subchunk size
            view.setUint16(20, 1, true); // PCM format
            view.setUint16(22, numChannels, true);
            view.setUint32(24, sampleRate, true);
            view.setUint32(28, byteRate, true);
            view.setUint16(32, blockAlign, true);
            view.setUint16(34, 16, true); // bits per sample
            this.writeString(view, 36, 'data');
            view.setUint32(40, dataSize, true);

            // Interleave PCM 16-bit samples
            let offset = 44;
            for (let i = 0; i < numSamples; i++) {
                for (let ch = 0; ch < numChannels; ch++) {
                    let sample = channelData[ch][i];
                    sample = Math.max(-1, Math.min(1, sample));
                    view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7FFF, true);
                    offset += 2;
                }
            }

            return new Blob([view], { type: 'audio/wav' });
        },

        writeString(view, offset, string) {
            for (let i = 0; i < string.length; i++) {
                view.setUint8(offset + i, string.charCodeAt(i));
            }
        },
    }));
});
</script>
