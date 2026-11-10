import workletUrl from './pcmCapture.worklet.js?url';
import { bytesToBase64, createResampler, floatToPcm16, PCM_RATE, readTranscriptionEvent } from './chatAudio.js';

/**
 * Live transcription: while the visitor speaks, the microphone is streamed
 * to OpenAI's Realtime transcription over a WebSocket, so the question's
 * text is ready about half a second after they stop (and they see it being
 * written as they speak).
 *
 * The site mints a short-lived key for a transcription-only session (it
 * cannot answer or chat). One connection serves a whole voice conversation;
 * audio is sent only while the host listens. The widget's own silence
 * detection ends a turn and commits it. Anything that goes wrong resolves
 * to null, and the widget uploads the recording it made alongside instead.
 */

const OPEN_TIMEOUT_MS = 5000;
const FINAL_TIMEOUT_MS = 5000;
const workletContexts = new WeakSet();

export class LiveTranscriber {
    constructor({ endpoint, csrfToken, onPartial = () => {} }) {
        this.endpoint = endpoint;
        this.csrfToken = csrfToken;
        this.onPartial = onPartial;
        this.socket = null;
        this.opening = null;
        this.pending = [];
        this.partial = '';
        this.waiting = null;
        this.capture = null;
        this.failed = false;
    }

    /** Open the session (once). Resolves to whether it is usable. */
    open() {
        this.opening ??= this.connect().catch(() => {
            this.failed = true;

            return false;
        });

        return this.opening;
    }

    async connect() {
        const response = await fetch(this.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrfToken },
        });
        const data = response.ok ? await response.json() : null;
        if (data?.status !== 'ok' || typeof data.key !== 'string' || typeof data.url !== 'string') {
            this.failed = true;

            return false;
        }

        const socket = new WebSocket(data.url, ['realtime', `openai-insecure-api-key.${data.key}`]);
        this.socket = socket;
        await new Promise((resolve, reject) => {
            const timer = window.setTimeout(() => reject(new Error('timeout')), OPEN_TIMEOUT_MS);
            socket.addEventListener('open', () => {
                window.clearTimeout(timer);
                resolve();
            }, { once: true });
            socket.addEventListener('error', () => {
                window.clearTimeout(timer);
                reject(new Error('socket'));
            }, { once: true });
        });

        socket.addEventListener('message', (message) => this.receive(message.data));
        socket.addEventListener('close', () => {
            this.failed = true;
            this.settle(null);
        });
        socket.send(JSON.stringify({ type: 'session.update', session: data.session ?? {} }));

        // Audio captured while the connection was opening goes out now.
        for (const chunk of this.pending.splice(0)) {
            this.sendAudio(chunk);
        }

        return true;
    }

    get usable() {
        return !this.failed;
    }

    /**
     * Start streaming a turn from the microphone source. Samples are kept
     * while the connection opens, so the first words are not lost.
     */
    async attach(source, audioContext) {
        if (this.failed) {
            return false;
        }

        try {
            if (!workletContexts.has(audioContext)) {
                await audioContext.audioWorklet.addModule(workletUrl);
                workletContexts.add(audioContext);
            }
        } catch {
            this.failed = true;

            return false;
        }

        const node = new AudioWorkletNode(audioContext, 'spu-pcm-capture');
        // The worklet runs only while it is part of a rendered graph; a muted
        // gain keeps it there without making a sound.
        const silent = audioContext.createGain();
        silent.gain.value = 0;
        source.connect(node);
        node.connect(silent);
        silent.connect(audioContext.destination);

        const resample = createResampler(audioContext.sampleRate, PCM_RATE);
        node.port.onmessage = (event) => {
            const pcm = floatToPcm16(resample(event.data));
            if (!pcm.length) {
                return;
            }
            if (this.socket?.readyState === WebSocket.OPEN) {
                this.sendAudio(pcm);
            } else if (!this.failed) {
                this.pending.push(pcm);
            }
        };

        this.partial = '';
        this.capture = { node, silent, source };

        return true;
    }

    /** Stop streaming this turn (the microphone is about to close). */
    detach() {
        if (!this.capture) {
            return;
        }
        const { node, silent, source } = this.capture;
        node.port.onmessage = null;
        try {
            source.disconnect(node);
        } catch {
            // Already disconnected.
        }
        node.disconnect();
        silent.disconnect();
        this.capture = null;
    }

    /**
     * End the turn: its transcript, or null when it does not come in time
     * (the widget then uploads the recording).
     */
    commit() {
        this.detach();
        if (this.failed || this.socket?.readyState !== WebSocket.OPEN) {
            this.pending = [];

            return Promise.resolve(null);
        }

        this.socket.send(JSON.stringify({ type: 'input_audio_buffer.commit' }));

        return new Promise((resolve) => {
            const timer = window.setTimeout(() => this.settle(null), FINAL_TIMEOUT_MS);
            this.waiting = (text) => {
                window.clearTimeout(timer);
                resolve(text);
            };
        });
    }

    /** Drop the turn without a transcript (cancelled, or nothing was said). */
    discard() {
        this.detach();
        this.pending = [];
        if (this.socket?.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ type: 'input_audio_buffer.clear' }));
        }
    }

    close() {
        this.detach();
        this.settle(null);
        this.failed = true;
        try {
            this.socket?.close();
        } catch {
            // Already closed.
        }
        this.socket = null;
    }

    sendAudio(pcm) {
        this.socket.send(JSON.stringify({ type: 'input_audio_buffer.append', audio: bytesToBase64(new Uint8Array(pcm.buffer, pcm.byteOffset, pcm.byteLength)) }));
    }

    receive(raw) {
        const event = readTranscriptionEvent(raw);
        if (event.kind === 'partial' && event.text) {
            this.partial += event.text;
            this.onPartial(this.partial);
        } else if (event.kind === 'final') {
            this.partial = '';
            this.settle(event.text || null);
        } else if (event.kind === 'failed' || (event.kind === 'error' && this.waiting)) {
            this.settle(null);
        }
    }

    settle(text) {
        const waiting = this.waiting;
        this.waiting = null;
        waiting?.(text);
    }
}
